<?php

namespace Drupal\gathercontent\Import\ContentProcess;

use Drupal\Component\Render\PlainTextOutput;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\field\Entity\FieldConfig;
use Drupal\taxonomy\Entity\Term;

/**
 * The ContentProcessor sets the necessary fields of the entity.
 */
class ContentProcessor {

  /**
   * Store the already imported entity references (used in recursion).
   *
   * @var array
   */
  protected $importedReferences = [];

  /**
   * ContentProcessor constructor.
   */
  public function __construct() {
    $this->init();
  }

  /**
   * Initialize member variables.
   */
  public function init() {
    $this->importedReferences = [];
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
  public function processContentPane(EntityInterface &$entity, $local_field_id, $field, $is_translatable, $language, array $files) {
    $local_id_array = explode('||', $local_field_id);

    if (count($local_id_array) > 1) {
      $entityTypeManager = \Drupal::entityTypeManager();
      $field_info = FieldConfig::load($local_id_array[0]);
      $field_target_info = FieldConfig::load($local_id_array[1]);
      $field_name = $field_info->getName();

      $entityStorage = $entityTypeManager
        ->getStorage($field_target_info->getTargetEntityTypeId());

      $target_field_value = $entity->get($field_name)->getValue();

      if (!isset($this->importedReferences[$local_id_array[0]])) {
        if (!empty($target_field_value)) {
          foreach ($target_field_value as $target) {
            $deleteEntity = $entityStorage->load($target['target_id']);
            $deleteEntity->delete();
          }
        }

        $this->importedReferences[$local_id_array[0]] = TRUE;
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
              $this->processContentPane($childEntity[$target['target_id']],
                implode('||', $local_id_array), $field, $is_translatable,
                $language, $files);

              $childEntity[$target['target_id']]->save();
              $to_import = FALSE;
            }
          }
        }

        if ($to_import) {
          $childEntity = $entityStorage->create([
            'type' => $field_target_info->getTargetBundle(),
          ]);

          $this->processContentPane($childEntity, implode('||', $local_id_array), $field, $is_translatable, $language, $files);

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

        $this->processContentPane($childEntity, implode('||', $local_id_array), $field, $is_translatable, $language, $files);

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
          $this->processFilesField($entity, $field_info, $field->id,
            $is_translatable, $language, $files);
          break;

        case 'choice_radio':
          $this->processChoiceRadioField($entity, $field_info, $is_translatable,
            $language, $field->options);
          break;

        case 'choice_checkbox':
          $this->processChoiceCheckboxField($entity, $field_info,
            $is_translatable, $language, $field->options);
          break;

        case 'section':
          $this->processSectionField($entity, $field_info, $is_translatable,
            $language, $field);
          break;

        default:
          $this->processDefaultField($entity, $field_info, $is_translatable,
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
  protected function processDefaultField(EntityInterface &$entity, FieldConfig $field_info, $is_translatable, $language, $field) {
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
  protected function processSectionField(EntityInterface &$entity, FieldConfig $field_info, $is_translatable, $language, $field) {
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
  protected function processChoiceCheckboxField(EntityInterface &$entity, FieldConfig $field_info, $is_translatable, $language, array $options) {
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
  protected function processChoiceRadioField(EntityInterface &$entity, FieldConfig $field_info, $is_translatable, $language, array $options) {
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
  protected function processFilesField(EntityInterface &$entity, FieldConfig $field_info, $gc_field_name, $is_translatable, $language, array $files) {
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
