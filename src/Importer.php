<?php

namespace Drupal\gathercontent;

use Cheppers\GatherContent\DataTypes\Item;
use Cheppers\GatherContent\GatherContentClientInterface;
use Drupal\Component\Render\PlainTextOutput;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Language\Language;
use Drupal\Core\Language\LanguageInterface;
use Drupal\field\Entity\FieldConfig;
use Drupal\gathercontent\Entity\Mapping;
use Drupal\gathercontent\Entity\OperationItem;
use Drupal\gathercontent\Event\GatherContentEvents;
use Drupal\gathercontent\Event\PostNodeSaveEvent;
use Drupal\gathercontent\Event\PreNodeSaveEvent;
use Drupal\node\Entity\Node;
use Drupal\node\NodeInterface;
use Drupal\taxonomy\Entity\Term;
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
   * @var \Drupal\gathercontent\ImportOptions[]
   */
  protected $importOptionsArray = [];

  /**
   * DI GatherContent Client.
   */
  public function __construct(GatherContentClientInterface $client) {
    $this->client = $client;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('gathercontent.client')
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

    // Get the files corresponding to item.
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

      $reference_imported = [];
      foreach ($pane->elements as $field) {
        if (isset($mapping_data[$pane->id]['elements'][$field->id]) && !empty($mapping_data[$pane->id]['elements'][$field->id])) {
          $local_field_id = $mapping_data[$pane->id]['elements'][$field->id];
          if (isset($mapping_data[$pane->id]['type']) && ($mapping_data[$pane->id]['type'] === 'content') || !isset($mapping_data[$pane->id]['type'])) {
            $this->gc_gc_process_content_pane($entity, $local_field_id, $field, $is_pane_translatable, $language, $files, $reference_imported);
          }
          elseif (isset($mapping_data[$pane->id]['type']) && ($mapping_data[$pane->id]['type'] === 'metatag')) {
            $this->gc_gc_process_metatag_pane($entity, $local_field_id, $field, $mapping->getContentType(), $is_pane_translatable, $language);
          }
        }
      }
    }

    if (!$is_translatable && empty($entity->getTitle())) {
      $entity->setTitle($gc_item->name);
    }

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
  function gc_gc_process_metatag_pane(NodeInterface &$entity, $local_field_id, $field, $content_type, $is_translatable, $language) {
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
      throw new \Exception("Metatag module not enabled or entity doesn't support
    metatags while trying to map values with metatag content.");
    }
  }

  /**
   * Processing function for content panes.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   Object of node.
   * @param string $local_field_id
   *   ID of local Drupal field.
   * @param object $field
   *   Object of GatherContent field.
   * @param bool $is_translatable
   *   Indicator if node is translatable.
   * @param string $language
   *   Language of translation if applicable.
   * @param array $files
   *   Array of files fetched from GatherContent.
   * @param array $reference_imported
   *   Array of reference fields which are imported.
   */
  function gc_gc_process_content_pane(EntityInterface &$entity, $local_field_id, $field, $is_translatable, $language, array $files, array &$reference_imported) {
    $local_id_array = explode('||', $local_field_id);

    if (count($local_id_array) > 1) {
      $entityTypeManager = \Drupal::entityTypeManager();
      $field_info = FieldConfig::load($local_id_array[0]);
      $field_target_info = FieldConfig::load($local_id_array[1]);
      $field_name = $field_info->getName();

      $entityStorage = $entityTypeManager
        ->getStorage($field_target_info->getTargetEntityTypeId());

      $target_field_value = $entity->get($field_name)->getValue();

      if (!isset($reference_imported[$local_id_array[0]])) {
        if (!empty($target_field_value)) {
          foreach ($target_field_value as $target) {
            $deleteEntity = $entityStorage->load($target['target_id']);
            $deleteEntity->delete();
          }
        }

        $reference_imported[$local_id_array[0]] = TRUE;
        $target_field_value = [];
      }

      array_shift($local_id_array);
      if (!empty($target_field_value)) {
        $to_import = TRUE;

        foreach ($target_field_value as $target) {
          $childEntity = $entityStorage->loadByProperties([
            'id' => $target['target_id'],
            'type' => $field_target_info->getTargetBundle(),
          ]);

          if (!empty($childEntity[$target['target_id']])) {
            $check_field_name = $field_target_info->getName();
            $check_field_value = $childEntity[$target['target_id']]->get($check_field_name)->getValue();

            if (count($local_id_array) > 1 || empty($check_field_value)) {
              $this->gc_gc_process_content_pane($childEntity[$target['target_id']],
                implode('||', $local_id_array), $field, $is_translatable,
                $language, $files, $reference_imported);

              $childEntity[$target['target_id']]->save();
              $to_import = FALSE;
            }
          }
        }

        if ($to_import) {
          $childEntity = $entityStorage->create([
            'type' => $field_target_info->getTargetBundle(),
          ]);

          $this->gc_gc_process_content_pane($childEntity, implode('||', $local_id_array), $field, $is_translatable, $language, $files, $reference_imported);

          $childEntity->save();

          $target_field_value[] = [
            'target_id' => $childEntity->id(),
            'target_revision_id' => $childEntity->getRevisionId(),
          ];
        }
      }
      else {
        $childEntity = $entityStorage->create([
          'type' => $field_target_info->getTargetBundle(),
        ]);

        $this->gc_gc_process_content_pane($childEntity, implode('||', $local_id_array), $field, $is_translatable, $language, $files, $reference_imported);

        $childEntity->save();

        $target_field_value[] = [
          'target_id' => $childEntity->id(),
          'target_revision_id' => $childEntity->getRevisionId(),
        ];
      }

      $entity->set($field_name, $target_field_value);
    }
    else {
      $field_info = FieldConfig::load($local_field_id);
      if (!is_null($field_info)) {
        $is_translatable = $is_translatable && $field_info->isTranslatable();
      }

      switch ($field->type) {
        case 'files':
          $this->gc_gc_process_files_field($entity, $field_info, $field->id,
            $is_translatable, $language, $files);
          break;

        case 'choice_radio':
          $this->gc_gc_process_choice_radio_field($entity, $field_info, $is_translatable,
            $language, $field->options);
          break;

        case 'choice_checkbox':
          $this->gc_gc_process_choice_checkbox_field($entity, $field_info,
            $is_translatable, $language, $field->options);
          break;

        case 'section':
          $this->gc_gc_process_section_field($entity, $field_info, $is_translatable,
            $language, $field);
          break;

        default:
          $this->gc_gc_process_default_field($entity, $field_info, $is_translatable,
            $language, $field);
          break;
      }
    }
  }


  /**
   * Default processing function, when no other matches found, usually for text.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   Object of node.
   * @param \Drupal\field\Entity\FieldConfig $field_info
   *   Local field Info object.
   * @param bool $is_translatable
   *   Indicator if node is translatable.
   * @param string $language
   *   Language of translation if applicable.
   * @param object $field
   *   Object with field attributes.
   */
  function gc_gc_process_default_field(EntityInterface &$entity, FieldConfig $field_info, $is_translatable, $language, $field) {
    $local_field_name = $field_info->getName();
    $value = $field->value;
    $target = &$entity;
    if ($is_translatable) {
      $target = $entity->getTranslation($language);
    }

    // Title is not a field, breaks everything. Short-circuit here.
    if ($local_field_name === 'title') {
      $target->setTitle($value);
      return;
    }

    switch ($field_info->getType()) {
      case 'datetime':
        $value = strtotime($value);
        if ($value === FALSE) {
          // If we failed to convert to a timestamp, abort.
          return;
        }
        $target->{$local_field_name} = [
          'value' => gmdate(DATETIME_DATETIME_STORAGE_FORMAT, $value),
        ];
        break;

      case 'date':
        $value = strtotime($value);
        if ($value === FALSE) {
          return;
        }
        $target->{$local_field_name} = [
          'value' => gmdate(DATETIME_DATE_STORAGE_FORMAT, $value),
        ];
        break;

      default:
        // Probably some kind of text field.
        $target->{$local_field_name} = [
          'value' => $value,
          'format' => ($field->plainText ? 'plain_text' : 'basic_html'),
        ];
        break;
    }
  }

  /**
   * Processing function for section type of field.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   Object of node.
   * @param \Drupal\field\Entity\FieldConfig $field_info
   *   Local field Info object.
   * @param bool $is_translatable
   *   Indicator if node is translatable.
   * @param string $language
   *   Language of translation if applicable.
   * @param object $field
   *   Object with field attributes.
   */
  function gc_gc_process_section_field(EntityInterface &$entity, FieldConfig $field_info, $is_translatable, $language, $field) {
    $local_field_name = $field_info->getName();
    if ($is_translatable) {
      $entity->getTranslation($language)->{$local_field_name} = [
        'value' => '<h3>' . $field->title . '</h3>' . $field->subtitle,
        'format' => 'basic_html',
      ];
    }
    else {
      $entity->{$local_field_name} = [
        'value' => '<h3>' . $field->title . '</h3>' . $field->subtitle,
        'format' => 'basic_html',
      ];
    }
  }

  /**
   * Processing function for checkbox type of field.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   Object of node.
   * @param \Drupal\field\Entity\FieldConfig $field_info
   *   Local field Info object.
   * @param bool $is_translatable
   *   Indicator if node is translatable.
   * @param string $language
   *   Language of translation if applicable.
   * @param array $options
   *   Array of options.
   */
  function gc_gc_process_choice_checkbox_field(EntityInterface &$entity, FieldConfig $field_info, $is_translatable, $language, array $options) {
    $local_field_name = $field_info->getName();
    $entity->{$local_field_name} = [NULL];
    $selected_options = [];
    foreach ($options as $option) {
      if ($option['selected']) {
        if ($field_info->getType() === 'entity_reference') {
          $taxonomy = \Drupal::entityTypeManager()
            ->getStorage('taxonomy_term')
            ->loadByProperties(['gathercontent_option_ids' => $option['name']]);

          /** @var \Drupal\taxonomy\Entity\Term $term */
          $term = array_shift($taxonomy);
          $selected_options[] = $term->id();
        }
        else {
          $selected_options[] = $option['name'];
        }
      }
      if ($is_translatable) {
        $entity->getTranslation($language)->{$local_field_name} = $selected_options;
      }
      else {
        $entity->{$local_field_name} = $selected_options;
      }
    }
  }

  /**
   * Processing function for radio type of field.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   Object of node.
   * @param \Drupal\field\Entity\FieldConfig $field_info
   *   Local field Info object.
   * @param bool $is_translatable
   *   Indicator if node is translatable.
   * @param string $language
   *   Language of translation if applicable.
   * @param array $options
   *   Array of options.
   */
  public function gc_gc_process_choice_radio_field(EntityInterface &$entity, FieldConfig $field_info, $is_translatable, $language, array $options) {
    $local_field_name = $field_info->getName();
    foreach ($options as $option) {
      if (!$option['selected']) {
        continue;
      }
      if (isset($option['value'])) {
        if (empty($option['value'])) {
          continue;
        }
        // Dealing with "Other" option.
        if ($field_info->getType() === 'entity_reference') {
          // Load vocabulary id.
          if (!empty($field_info->getSetting('handler_settings')['auto_create_bundle'])) {
            $vid = $field_info->getSetting('handler_settings')['auto_create_bundle'];
          }
          else {
            $handler_settings = $field_info->getSetting('handler_settings');
            $handler_settings = reset($handler_settings);
            $vid = array_shift($handler_settings);
          }

          // Prepare confitions.
          $condition_array = [
            'name' => $option['value'],
            'vid' => $vid,
          ];
          if ($is_translatable && $language !== LanguageInterface::LANGCODE_NOT_SPECIFIED) {
            $condition_array['langcode'] = $language;
          }

          $terms = \Drupal::entityTypeManager()
            ->getStorage('taxonomy_term')
            ->loadByProperties($condition_array);
          /** @var \Drupal\taxonomy\Entity\Term $term */
          $term = array_shift($terms);
          if (empty($term)) {
            $term = Term::create([
              'vid' => $vid,
              'name' => $option['value'],
              'langcode' => $language,
            ]);
            $term->save();
          }
          if ($is_translatable && $entity->hasTranslation($language)) {
            $entity->getTranslation($language)
              ->set($local_field_name, $term->id());
          }
          else {
            $entity->set($local_field_name, $term->id());
          }
        }
        else {
          if ($is_translatable) {
            $entity->getTranslation($language)->{$local_field_name}->value = $option['value'];
          }
          else {
            $entity->{$local_field_name}->value = $option['value'];
          }
        }
      }
      else {
        // Dealing with predefined options.
        if ($field_info->getType() === 'entity_reference') {
          $terms = \Drupal::entityTypeManager()
            ->getStorage('taxonomy_term')
            ->loadByProperties(['gathercontent_option_ids' => $option['name']]);
          /** @var \Drupal\taxonomy\Entity\Term $term */
          $term = array_shift($terms);
          if (!empty($term)) {
            if ($is_translatable) {
              $entity->getTranslation($language)
                ->set($local_field_name, $term->id());
            }
            else {
              $entity->set($local_field_name, $term->id());
            }
          }
        }
        else {
          if ($is_translatable) {
            $entity->getTranslation($language)->{$local_field_name}->value = $option['name'];
          }
          else {
            $entity->{$local_field_name}->value = $option['name'];
          }
        }
      }
    }
  }

  /**
   * Processing function for file type of field.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   Object of node.
   * @param \Drupal\field\Entity\FieldConfig $field_info
   *   Local field Info object.
   * @param string $gc_field_name
   *   Name of field in GatherContent.
   * @param bool $is_translatable
   *   Indicator if node is translatable.
   * @param string $language
   *   Language of translation if applicable.
   * @param array $files
   *   Array of remote files.
   */
  public function gc_gc_process_files_field(EntityInterface &$entity, FieldConfig $field_info, $gc_field_name, $is_translatable, $language, array $files) {
    /** @var \Drupal\gathercontent\DrupalGatherContentClient $client */
    $client = \Drupal::service('gathercontent.client');
    $found_files = [];
    $local_field_name = $field_info->getName();
    /** @var \Drupal\field\Entity\FieldConfig $translatable_file_config */
    $translatable_file_config = $entity->getFieldDefinition($local_field_name);
    $third_party_settings = $translatable_file_config->get('third_party_settings');

    if (isset($third_party_settings['content_translation'])) {
      $translatable_file = $third_party_settings['content_translation']['translation_sync']['file'];
    }
    else {
      $translatable_file = NULL;
    }

    foreach ($files as $key => $file) {
      if ($file->field === $gc_field_name) {
        $drupal_files = \Drupal::entityQuery('file')
          ->condition('gc_id', $file->id)
          ->condition('filename', $file->fileName)
          ->execute();

        if (!empty($drupal_files)) {
          $drupal_file = reset($drupal_files);
          $found_files[] = ['target_id' => $drupal_file];
          unset($files[$key]);
        }
      }
      else {
        unset($files[$key]);
      }
    }

    if (!($entity->language()->getId() !== $language && $translatable_file === '0') && !empty($files)) {
      $file_dir = $translatable_file_config->getSetting('file_directory');
      $file_dir = PlainTextOutput::renderFromHtml(\Drupal::token()->replace($file_dir, []));

      $uri_scheme = $translatable_file_config->getFieldStorageDefinition()->getSetting('uri_scheme') . '://';

      $create_dir = \Drupal::service('file_system')->realpath($uri_scheme) . '/' . $file_dir;
      file_prepare_directory($create_dir, FILE_CREATE_DIRECTORY);

      $imported_files = $client->downloadFiles($files, $uri_scheme . $file_dir, $language);

      if (!empty($imported_files)) {
        foreach ($imported_files as $file) {
          $found_files[] = ['target_id' => $file];
        }

        if ($is_translatable) {
          $entity->getTranslation($language)->set($local_field_name, end($found_files));
        }
        else {
          $entity->set($local_field_name, end($found_files));
        }
      }
    }

  }


}
