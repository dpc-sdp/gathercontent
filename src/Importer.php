<?php

namespace Drupal\gathercontent;

use Drupal\Component\Render\PlainTextOutput;
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

class Importer {

  /**
   * Function for fetching, creating and updating content from GatherContent.
   *
   * @param int $gc_id
   *   ID of GatherContent piece of content.
   * @param string $uuid
   *   UUID of \Drupal\gathercontent\Entity\Operation.
   * @param bool $drupal_status
   *   Drupal status - published/unpublished.
   * @param string $node_update_method
   *   Name of the node update method.
   * @param int|null $status
   *   ID of status from GatherContent.
   * @param string|null $parent_menu_item
   *   Parent menu item ID if we want to create menu item.
   *
   * @return bool
   *   Return nid if operation was successful.
   */
  function _gc_fetcher($gc_id, $uuid, $drupal_status, $node_update_method, $status = NULL, $parent_menu_item = NULL) {
    $user = \Drupal::currentUser();
    /** @var \Cheppers\GatherContent\GatherContentClientInterface $client */
    $client = \Drupal::service('gathercontent.client');

    $tsid = NULL;

    /** @var \Cheppers\GatherContent\DataTypes\Item $content */
    $content = $client->itemGet($gc_id);

    if (!empty($status)) {
      $status = $client->projectStatusGet($content->projectId, $status);
    }

    $template = $client->templateGet($content->templateId);

    $operation_item = \Drupal::entityTypeManager()
      ->getStorage('gathercontent_operation_item')
      ->create([
        'operation_uuid' => $uuid,
        'item_status' => (!empty($status) ? $status->name : $content->status->name),
        'item_status_color' => (!empty($status) ? $status->color : $content->status->color),
        'template_name' => $template->name,
        'item_name' => $content->name,
        'gc_id' => $gc_id,
      ]);

    $mapping_id = \Drupal::entityQuery('gathercontent_mapping')
      ->condition('gathercontent_project_id', $content->projectId)
      ->condition('gathercontent_template_id', $content->templateId)
      ->execute();

    if (!empty($mapping_id)) {
      $mapping = Mapping::load(reset($mapping_id));
      // If mapping exists, start mapping remote fields to local ones.
      $mapping_data = unserialize($mapping->getData());
      if (empty($mapping_data)) {
        return FALSE;
      }

      $mapping_data_copy = $mapping_data;
      $first = array_shift($mapping_data_copy);
      $content_type = $mapping->getContentType();
      $langcode = isset($first['language']) ? $first['language'] : Language::LANGCODE_NOT_SPECIFIED;

      $entity = $this->gc_get_destination_node($gc_id, $node_update_method, $content_type, $langcode);

      $entity->set('gc_id', $gc_id);
      $entity->set('gc_mapping_id', $mapping->id());
      $entity->setOwnerId($user->id());

      if ($entity->isNew()) {
        $entity->setPublished($drupal_status);
      }

      if ($entity !== FALSE) {
        /** @var \Drupal\node\NodeInterface $entity */
        try {
          $files = $client->itemFilesGet($gc_id);
          $is_translatable = \Drupal::moduleHandler()
              ->moduleExists('content_translation')
            && \Drupal::service('content_translation.manager')
              ->isEnabled('node', $mapping->getContentType());
          foreach ($content->config as $pane) {
            $is_translatable &= isset($mapping_data[$pane->id]['language'])
              && ($mapping_data[$pane->id]['language'] != Language::LANGCODE_NOT_SPECIFIED);
            if ($is_translatable) {
              $language = $mapping_data[$pane->id]['language'];
              if (!$entity->hasTranslation($language)) {
                $entity->addTranslation($language);
                if ($entity->isNew()) {
                  $entity->getTranslation($language)->setPublished($drupal_status);
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
                  $this->gc_gc_process_content_pane($entity, $local_field_id, $field, $is_translatable, $language, $files, $reference_imported);
                }
                elseif (isset($mapping_data[$pane->id]['type']) && ($mapping_data[$pane->id]['type'] === 'metatag')) {
                  $this->gc_gc_process_metatag_pane($entity, $local_field_id, $field, $mapping->getContentType(), $is_translatable, $language);
                }
              }
            }
          }

          if (!$is_translatable && empty($entity->getTitle())) {
            $entity->setTitle($content->name);
          }

          \Drupal::service('event_dispatcher')
            ->dispatch(GatherContentEvents::PRE_NODE_SAVE, new PreNodeSaveEvent($entity, $content, $files));
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
                  gc_create_menu_link($entity->id(), $localized_entity->getTitle(), $parent_menu_item, $langcode, $original_link_id);
                }
              }
            }
            else {
              gc_create_menu_link($entity->id(), $entity->getTitle(), $parent_menu_item);
            }
          }

          \Drupal::service('event_dispatcher')
            ->dispatch(GatherContentEvents::POST_NODE_SAVE, new PostNodeSaveEvent($entity, $content, $files));

          $operation_item->status = "Success";
          $operation_item->nid = $entity->id();
          $operation_item->save();
          return $entity->id();
        }
        catch (\Exception $e) {
          \Drupal::logger('gc_import')->error(print_r($e, TRUE), []);
          $operation_item->status = "Operation failed:" . $e->getMessage();
          $operation_item->save();
          return FALSE;
        }
      }
      else {
        $operation_item->status = "System error, please contact you administrator.";
        $operation_item->save();
        return FALSE;
      }
    }
    else {
      $operation_item->status = "Operation failed: Template not mapped.";
      $operation_item->save();
      return FALSE;
    }
  }

  /**
   * Get Node object based on type of update.
   *
   * @param int $gc_id
   *   ID of item in GatherContent.
   * @param string $node_update_method
   *   Name of the node update method.
   * @param int $node_type_id
   *   ID of the node type.
   * @param string $langcode
   *   Language of translation if applicable.
   *
   * @return \Drupal\node\NodeInterface
   *   Return loaded node.
   */
  function gc_get_destination_node($gc_id, $node_update_method, $node_type_id, $langcode) {
    switch ($node_update_method) {
      case 'update_if_not_changed';
        $result = \Drupal::entityQuery('node')
          ->condition('gc_id', $gc_id)
          ->sort('created', 'DESC')
          ->range(0, 1)
          ->execute();

        if ($result) {
          $node = Node::load(reset($result));
          $query_result = \Drupal::entityQuery('gathercontent_operation_item')
            ->condition('gc_id', $gc_id)
            ->sort('changed', 'DESC')
            ->range(0, 1)
            ->execute();

          $operation = OperationItem::load(reset($query_result));

          if ($node->getChangedTime() === $operation->getChangedTime()) {
            return $node;
          }
        }

        break;

      case 'always_update';
        $result = \Drupal::entityQuery('node')
          ->condition('gc_id', $gc_id)
          ->sort('created', 'DESC')
          ->range(0, 1)
          ->execute();

        if ($result) {
          return Node::load(reset($result));
        }

        break;

    }

    return Node::create([
      'type' => $node_type_id,
      'langcode' => $langcode,
    ]);
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
   *   Array of reference fields which are tempered with.
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

      if (!empty($target_field_value)) {
        foreach ($target_field_value as $target) {
          $childEntity = $entityStorage->loadByProperties([
            'id' => $target['target_id'],
            'type' => $field_target_info->getTargetBundle(),
          ]);

          if (!empty($childEntity[$target['target_id']])) {
            array_shift($local_id_array);
            $this->gc_gc_process_content_pane($childEntity[$target['target_id']],
              implode('||', $local_id_array), $field, $is_translatable,
              $language, $files, $reference_imported);

            $childEntity[$target['target_id']]->save();
          }
          else {
            $childEntity = $entityStorage->create([
              'type' => $field_target_info->getTargetBundle(),
            ]);

            array_shift($local_id_array);
            $this->gc_gc_process_content_pane($childEntity, implode('||', $local_id_array), $field, $is_translatable, $language, $files, $reference_imported);

            $childEntity->save();

            $target_field_value[] = [
              'target_id' => $childEntity->id(),
              'target_revision_id' => $childEntity->getRevisionId(),
            ];
          }
        }
      }
      else {
        $childEntity = $entityStorage->create([
          'type' => $field_target_info->getTargetBundle(),
        ]);

        array_shift($local_id_array);
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
