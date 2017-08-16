<?php

namespace Drupal\gathercontent\Import;

use Cheppers\GatherContent\DataTypes\Item;
use Cheppers\GatherContent\GatherContentClientInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Language\Language;
use Drupal\field\Entity\FieldConfig;
use Drupal\gathercontent\Entity\Mapping;
use Drupal\gathercontent\Event\GatherContentEvents;
use Drupal\gathercontent\Event\PostNodeSaveEvent;
use Drupal\gathercontent\Event\PreNodeSaveEvent;
use Drupal\gathercontent\Import\ContentProcess\ContentProcessor;
use Drupal\node\NodeInterface;
use Exception;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class for handling import/update logic from GatherContent to Drupal.
 */
class Importer implements ContainerInjectionInterface {

  /**
   * Drupal GatherContent Client.
   *
   * @var \Drupal\gathercontent\DrupalGatherContentClient
   */
  protected $client;

  /**
   * Options that will apply for the imported node given as a key.
   *
   * It looks like this:
   * [
   *   gc_id1 => ImportOptions1,
   *   gc_id2 => ImportOptions2,
   * ];
   *
   * The ImportOption should always be set before importing an item.
   *
   * @var \Drupal\gathercontent\Import\ImportOptions[]
   */
  protected $importOptionsArray = [];

  /**
   * The ContentProcessor fills the nodes with imported content.
   *
   * @var \Drupal\gathercontent\Import\ContentProcess\ContentProcessor
   */
  protected $contentProcessor;

  /**
   * DI GatherContent Client.
   */
  public function __construct(GatherContentClientInterface $client, ContentProcessor $contentProcessor) {
    $this->client = $client;
    $this->contentProcessor = $contentProcessor;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('gathercontent.client'),
      $container->get('gathercontent.content_processor')
    );
  }

  /**
   * Getter GatherContentClient.
   */
  public function getClient() {
    return $this->client;
  }

  /**
   * Getter ImportOptions.
   */
  public function getImportOption(int $gc_id) {
    return $this->importOptionsArray[$gc_id];
  }

  /**
   * Setter ImportOptions.
   */
  public function setImportOption(int $gc_id, ImportOptions $import_options) {
    $this->importOptionsArray[$gc_id] = $import_options;
    return $this;
  }

  /**
   * Don't forget to add a finished callback and the operations array.
   */
  public static function getBasicImportBatch() {
    return [
      'title' => t('Importing'),
      'init_message' => t('Starting import'),
      'error_message' => t('An error occurred during processing'),
      'progress_message' => t('Processed @current out of @total.'),
      'progressive' => TRUE,
    ];
  }

  /**
   * Update item's status based on ImportOptions. Upload new status to GC.
   */
  public function updateStatus(Item $item) {
    $status_id = $this->getImportOption($item->id)->getNewStatus();

    if (!is_int($status_id)) {
      // User does not want to update status.
      return;
    }

    $status = $this->client->projectStatusGet($item->projectId, $status_id);

    // Update only if status exists.
    if ($status !== NULL) {
      // Update status on GC.
      $this->client->itemChooseStatusPost($item->id, $status_id);
      // Update status of item (will be uploaded to Drupal on import).
      $item->status = $status;
    }
  }

  /**
   * Import a single GatherContent item to Drupal.
   *
   * Before calling this function make sure to set the import options for the item.
   *
   * This function is a replacement for the old _gc_fetcher function.
   *
   * The caller (e.g. batch processes) should handle the thrown exceptions.
   *
   * @return int
   *   The ID of the imported entity.
   */
  public function import(Item $gc_item) {
    list($entity, $is_translatable) = $this->createNode($gc_item);
    \Drupal::service('event_dispatcher')
      ->dispatch(GatherContentEvents::PRE_NODE_SAVE, new PreNodeSaveEvent($entity, $gc_item, $files));
    $entity->save();

    // Create menu link items.
    $menu_link_defaults = menu_ui_get_menu_link_defaults($entity);
    if (!(bool) $menu_link_defaults['id']) {
      if ($is_translatable) {
        $languages = $entity->getTranslationLanguages();
        $original_link_id = NULL;
        foreach ($languages as $langcode => $language) {
          $localized_entity = $entity->hasTranslation($langcode) ? $entity->getTranslation($langcode) : NULL;
          if (!is_null($localized_entity)) {
            gc_create_menu_link($entity->id(), $localized_entity->getTitle(), $this->getImportOption($gc_item->id)->getParentMenuItem(), $langcode, $original_link_id);
          }
        }
      }
      else {
        gc_create_menu_link($entity->id(), $entity->getTitle(), $this->getImportOption($gc_item->id)->getParentMenuItem());
      }
    }

    \Drupal::service('event_dispatcher')
      ->dispatch(GatherContentEvents::POST_NODE_SAVE, new PostNodeSaveEvent($entity, $gc_item, $files));
    return $entity->id();
  }

  /**
   * Create a Drupal node filled with the properties of the GC item.
   */
  public function createNode(Item $gc_item) {
    $user = \Drupal::currentUser();
    $mapping = $this->getMapping($gc_item);
    $mapping_data = unserialize($mapping->getData());

    if (empty($mapping_data)) {
      throw new Exception("Mapping data is empty.");
    }

    $mapping_data_copy = $mapping_data;
    $first = array_shift($mapping_data_copy);
    $content_type = $mapping->getContentType();

    $langcode = isset($first['language']) ? $first['language'] : Language::LANGCODE_NOT_SPECIFIED;

    // Create a Drupal entity corresponding to GC item.
    $entity = NodeUpdateMethod::getDestinationNode($gc_item->id, $this->getImportOption($gc_item->id)->getNodeUpdateMethod(), $content_type, $langcode);

    $entity->set('gc_id', $gc_item->id);
    $entity->set('gc_mapping_id', $mapping->id());
    $entity->setOwnerId($user->id());

    if ($entity->isNew()) {
      $entity->setPublished($this->getImportOption($gc_item->id)->getPublish());
    }

    if ($entity === FALSE) {
      throw new Exception("System error, please contact you administrator.");
    }

    // Get the files corresponding to GC item.
    $files = $this->client->itemFilesGet($gc_item->id);

    $is_translatable = \Drupal::moduleHandler()
      ->moduleExists('content_translation')
      && \Drupal::service('content_translation.manager')
        ->isEnabled('node', $mapping->getContentType());

    foreach ($gc_item->config as $pane) {
      $is_pane_translatable = $is_translatable && isset($mapping_data[$pane->id]['language'])
        && ($mapping_data[$pane->id]['language'] != Language::LANGCODE_NOT_SPECIFIED);

      if ($is_pane_translatable) {
        $language = $mapping_data[$pane->id]['language'];
        if (!$entity->hasTranslation($language)) {
          $entity->addTranslation($language);
          if ($entity->isNew()) {
            $entity->getTranslation($language)->setPublished($this->getImportOption($gc_item->id)->getPublish());
          }
        }
      }
      else {
        $language = Language::LANGCODE_NOT_SPECIFIED;
      }

      foreach ($pane->elements as $field) {
        if (isset($mapping_data[$pane->id]['elements'][$field->id]) && !empty($mapping_data[$pane->id]['elements'][$field->id])) {
          $local_field_id = $mapping_data[$pane->id]['elements'][$field->id];
          if (isset($mapping_data[$pane->id]['type']) && ($mapping_data[$pane->id]['type'] === 'content') || !isset($mapping_data[$pane->id]['type'])) {
            $this->contentProcessor->processContentPane($entity, $local_field_id, $field, $is_pane_translatable, $language, $files);
          }
          elseif (isset($mapping_data[$pane->id]['type']) && ($mapping_data[$pane->id]['type'] === 'metatag')) {
            $this->processMetatagPane($entity, $local_field_id, $field, $mapping->getContentType(), $is_pane_translatable, $language);
          }
        }
      }
    }

    if (!$is_translatable && empty($entity->getTitle())) {
      $entity->setTitle($gc_item->name);
    }

    return [$entity, $is_translatable];
  }

  /**
   * Return the mapping associated with the given Item.
   */
  public function getMapping(Item $gc_item) {
    $mapping_id = \Drupal::entityQuery('gathercontent_mapping')
      ->condition('gathercontent_project_id', $gc_item->projectId)
      ->condition('gathercontent_template_id', $gc_item->templateId)
      ->execute();

    if (empty($mapping_id)) {
      throw new Exception("Operation failed: Template not mapped.");
    }

    $mapping_id = reset($mapping_id);
    $mapping = Mapping::load($mapping_id);

    if ($mapping === NULL) {
      throw new Exception("No mapping found with id: $mapping_id");
    }

    return $mapping;
  }

  /**
   * Processing function for metatag panes.
   *
   * @param \Drupal\node\NodeInterface $entity
   *   Object of node.
   * @param string $local_field_id
   *   ID of local Drupal field.
   * @param object $field
   *   Object of GatherContent field.
   * @param string $content_type
   *   Name of Content type, we are mapping to.
   * @param bool $is_translatable
   *   Indicator if node is translatable.
   * @param string $language
   *   Language of translation if applicable.
   *
   * @throws \Exception
   *   If content save fails, exceptions is thrown.
   */
  public function processMetatagPane(NodeInterface &$entity, $local_field_id, $field, $content_type, $is_translatable, $language) {
    if (\Drupal::moduleHandler()->moduleExists('metatag') && check_metatag($content_type)) {
      $field_info = FieldConfig::load($local_field_id);
      $local_field_name = $field_info->getName();
      $metatag_fields = get_metatag_fields($content_type);

      foreach ($metatag_fields as $metatag_field) {
        if ($is_translatable) {
          $current_value = unserialize($entity->getTranslation($language)->{$metatag_field}->value);
          $current_value[$local_field_name] = $field->value;
          $entity->getTranslation($language)->{$metatag_field}->value = serialize($current_value);
        }
        else {
          $current_value = unserialize($entity->{$metatag_field}->value);
          $current_value[$local_field_name] = $field->value;
          $entity->{$metatag_field}->value = serialize($current_value);
        }
      }
    }
    else {
      throw new Exception("Metatag module not enabled or entity doesn't support
    metatags while trying to map values with metatag content.");
    }
  }

}
