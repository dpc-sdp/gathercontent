<?php

namespace Drupal\gathercontent_upload\Export;

use Cheppers\GatherContent\DataTypes\Item;
use Cheppers\GatherContent\GatherContentClientInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Language\Language;
use Drupal\field\Entity\FieldConfig;
use Drupal\gathercontent\Entity\Mapping;
use Drupal\gathercontent_upload\Event\GatherUploadContentEvents;
use Drupal\gathercontent_upload\Event\PostNodeUploadEvent;
use Drupal\gathercontent_upload\Event\PreNodeUploadEvent;
use Drupal\node\NodeInterface;
use Exception;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class for handling import/update logic from GatherContent to Drupal.
 */
class Exporter implements ContainerInjectionInterface {

  /**
   * Drupal GatherContent Client.
   *
   * @var \Drupal\gathercontent\DrupalGatherContentClient
   */
  protected $client;

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
   * Don't forget to add a finished callback and the operations array.
   */
  public static function getBasicExportBatch() {
    return [
      'title' => t('Uploading content ...'),
      'init_message' => t('Upload is starting ...'),
      'error_message' => t('An error occurred during processing'),
      'progress_message' => t('Processed @current out of @total.'),
      'progressive' => TRUE,
    ];
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
  public function export(Item $gc_item, NodeInterface $entity) {
    $mapping = $this->getMapping($gc_item);
    $mapping_data = unserialize($mapping->getData());

    if (empty($mapping_data)) {
      throw new Exception("Mapping data is empty.");
    }

    foreach ($gc_item->config as &$pane) {
      $is_translatable = \Drupal::moduleHandler()->moduleExists('content_translation')
        && \Drupal::service('content_translation.manager')
          ->isEnabled('node', $mapping->getContentType())
        && isset($mapping_data[$pane->id]['language'])
        && ($mapping_data[$pane->id]['language'] != Language::LANGCODE_NOT_SPECIFIED);
      if ($is_translatable) {
        $language = $mapping_data[$pane->id]['language'];
      }
      else {
        $language = Language::LANGCODE_NOT_SPECIFIED;
      }

      $exported_fields = [];
      foreach ($pane->elements as &$field) {
        if (isset($mapping_data[$pane->id]['elements'][$field->id])
          && !empty($mapping_data[$pane->id]['elements'][$field->id])
        ) {
          $local_field_id = $mapping_data[$pane->id]['elements'][$field->id];
          if ((isset($mapping_data[$pane->id]['type']) && $mapping_data[$pane->id]['type'] === 'content') || !isset($mapping_data[$pane->id]['type'])) {
            $local_id_array = explode('||', $local_field_id);
            $id_count = count($local_id_array);
            $entityTypeManager = \Drupal::entityTypeManager();

            $current_entity = $entity;
            $current_field_name = FieldConfig::load($local_id_array[0])->getName();

            for ($i = 0; $i < $id_count - 1; $i++) {
              $local_id = $local_id_array[$i];
              $field_info = FieldConfig::load($local_id);
              $current_field_name = $field_info->getName();
              $target_field_value = $current_entity->get($current_field_name)->getValue();

              if (!empty($target_field_value)) {
                $field_target_info = FieldConfig::load($local_id_array[$i + 1]);
                $entityStorage = $entityTypeManager
                  ->getStorage($field_target_info->getTargetEntityTypeId());
                $child_field_name = $field_target_info->getName();

                foreach ($target_field_value as $target) {
                  $export_key = $target['target_id'] . '_' . $child_field_name;

                  if (!empty($exported_fields[$export_key])) {
                    continue;
                  }

                  $child_entity = $entityStorage->loadByProperties([
                    'id' => $target['target_id'],
                    'type' => $field_target_info->getTargetBundle(),
                  ]);

                  if (!empty($child_entity[$target['target_id']])) {
                    $current_entity = $child_entity[$target['target_id']];
                    $current_field_name = $child_field_name;

                    if ($i == ($id_count - 2)) {
                      $exported_fields[$export_key] = TRUE;
                    }
                    break;
                  }
                }
              }
            }

            $this->gc_gc_process_set_fields($field, $current_entity, $is_translatable, $language, $current_field_name);
          }
          elseif ($mapping_data[$pane->id]['type'] === 'metatag') {
            if (\Drupal::moduleHandler()->moduleExists('metatag') && check_metatag($entity->getType())) {
              $metatag_fields = get_metatag_fields($entity->getType());
              foreach ($metatag_fields as $metatag_field) {
                if ($is_translatable) {
                  $field->value = $entity->getTranslation($language)->{$metatag_field}->value();
                }
                else {
                  $field->value = $entity->{$metatag_field}->value();
                }
              }

            }
          }
        }
      }
    }
    $event = \Drupal::service('event_dispatcher')
      ->dispatch(GatherUploadContentEvents::PRE_NODE_UPLOAD, new PreNodeUploadEvent($entity, $gc_item->config));

    /** @var \Drupal\gathercontent_upload\Event\PreNodeUploadEvent $event */
    $config = $event->getGathercontentValues();
    $this->client->itemSavePost($gc_item->id, $config);

    \Drupal::service('event_dispatcher')
      ->dispatch(GatherUploadContentEvents::POST_NODE_UPLOAD, new PostNodeUploadEvent($entity, $config));

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
   * Set value of the field.
   *
   * @param $field
   *   Field object.
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   Entity object.
   * @param bool $is_translatable
   *   Translatable bool.
   * @param string $language
   *   Language string.
   * @param string $local_field_name
   *   Field Name.
   */
  protected function gc_gc_process_set_fields(&$field, EntityInterface $entity, $is_translatable, $language, $local_field_name) {
    switch ($field->type) {
      case 'files':
        // There is currently no API for manipulating with files.
        break;

      case 'choice_radio':
        /** @var \Cheppers\GatherContent\DataTypes\ElementRadio $field */

        $option_names = [];

        foreach ($field->options as &$option) {
          // Set selected to false for each option.
          $option['selected'] = FALSE;
          $option_names[] = $option['name'];
        }

        // Fetch local selected option.
        if ($is_translatable) {
          $selected = $entity->getTranslation($language)->{$local_field_name}->value;
        }
        else {
          $selected = $entity->{$local_field_name}->value;
        }

        if (!in_array($selected, $option_names)) {
          // If it's other, then find that option in remote.
          foreach ($field->options as &$option) {
            if (isset($option['value'])) {
              $option['selected'] = TRUE;
              $option['value'] = $selected;
            }
          }
        }
        else {
          // If it's checkbox, find it by remote option name,
          // which should be same.
          foreach ($field->options as &$option) {
            if ($option['name'] == $selected) {
              $option['selected'] = TRUE;
            }
          }
        }
        break;

      case 'choice_checkbox':
        /** @var \Cheppers\GatherContent\DataTypes\ElementCheckbox $field */

        foreach ($field->options as &$option) {
          // Set selected to false for each option.
          $option['selected'] = FALSE;
        }

        // Fetch local selected option.
        if ($is_translatable) {
          $selected = $entity->getTranslation($language)->{$local_field_name}->value;
        }
        else {
          $selected = $entity->{$local_field_name}->value;
        }

        // If it's checkbox, find it by remote option name,
        // which should be same.
        foreach ($field->options as &$option) {
          if (isset($selected[$option['name']])) {
            $option['selected'] = TRUE;
          }
        }
        break;

      case 'section':
        // We don't upload this because this field shouldn't be
        // edited.
        break;

      default:
        if ($local_field_name === 'title') {
          if ($is_translatable) {
            $field->value = $entity->getTranslation($language)
              ->getTitle();
          }
          else {
            $field->value = $entity->getTitle();
          }
        }
        else {
          if ($is_translatable) {
            $field->value = $entity->getTranslation($language)->{$local_field_name}->value;
          }
          else {
            $field->value = $entity->{$local_field_name}->value;
          }
        }
        break;
    }
  }

}
