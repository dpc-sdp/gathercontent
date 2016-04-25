<?php

/**
 * @file
 * Multistep mapping form.
 */

use GatherContent\Template;

/**
 * EDIT MAPPING.
 */

/**
 * Edit mapping form.
 *
 * @inheritdoc
 */
function gc_mapping_edit_form($form, &$form_state, $id) {
  $mapping_entity = entity_load('gc_mapping', array($id));
  $mapping = reset($mapping_entity);
  $content_types = node_type_get_names();
  $tmp = new Template();
  $template = $tmp->getTemplate($mapping->gc_template_id);
  $new = empty($mapping->data);
  $form = array();
  $form['form_description'] = array(
    '#type' => 'html_tag',
    '#tag' => 'i',
    '#value' => t('Please map your GatherContent Template fields to your Drupal 
    Content Type Fields. Please note that a GatherContent field can only be 
    mapped to a single Drupal field. So each field can only be mapped to once.'),
  );
  if (!$new) {
    $mapping_data = unserialize($mapping->data);
    $content_type = $content_types[$mapping->content_type];

    $form['info'] = array(
      '#markup' =>
        '<div class="project-name">' .
        t('Project name: @name', array('@name' => $mapping->gc_project))
        . '</div>'
        . '<div class="gather-content">'
        . t('GatherContent template: @gc_template', array('@gc_template' => $mapping->gc_template))
        . '</div>'
        . '<div class="drupal-content-type">'
        . t('Drupal content type: @content_type', array('@content_type' => $content_type))
        . '</div>',

    );
    $form['id'] = array(
      '#type' => 'value',
      '#value' => $id,
    );
    $form['updated'] = array(
      '#type' => 'value',
      '#value' => $template->updated_at,
    );

    $form['mapping'] = array(
      '#prefix' => '<div id="edit-mapping">',
      '#suffix' => '</div>',
    );

    foreach ($template->config as $i => $fieldset) {
      if ($fieldset->hidden === FALSE) {
        $form['mapping'][$fieldset->name] = array(
          '#type' => 'fieldset',
          '#title' => $fieldset->label,
          '#collapsible' => TRUE,
          '#collapsed' => ($i === 0 ? FALSE : TRUE),
          '#tree' => TRUE,
        );

        if (module_exists('metatag')) {
          $form['mapping'][$fieldset->name]['type'] = array(
            '#type' => 'select',
            '#options' => array(
              'content' => t('Content'),
              'metatag' => t('Metatag'),
            ),
            '#title' => t('Type'),
            '#default_value' => (isset($mapping_data[$fieldset->name]['type']) || isset($form_state['values'][$fieldset->name]['type'])) ? (isset($form_state['values'][$fieldset->name]['type']) ? $form_state['values'][$fieldset->name]['type'] : $mapping_data[$fieldset->name]['type']) : NULL,
            '#ajax' => array(
              'callback' => 'gc_mapping_form_mapping_get_table',
              'wrapper' => 'edit-mapping',
              'method' => 'replace',
              'effect' => 'fade',
            ),
          );
        }

        if (module_exists('entity_translation') && module_exists('title')
          && entity_translation_node_supported_type($mapping->content_type)
          && title_field_replacement_enabled('node', $mapping->content_type, 'title')
        ) {
          $form['mapping'][$fieldset->name]['language'] = array(
            '#type' => 'select',
            '#options' => array('und' => t('None')) + locale_language_list('name'),
            '#title' => t('Language'),
            '#default_value' => isset($mapping_data[$fieldset->name]['language']) ? $mapping_data[$fieldset->name]['language'] : NULL,
          );
        }

        foreach ($fieldset->elements as $gc_field) {
          $d_fields = array();
          if (isset($form_state['values'][$fieldset->name]['type']) && $form_state['triggering_element']['#name'] !== 'content_type') {
            if ($form_state['triggering_element']['#array_parents'][1] === $fieldset->name && $form_state['values'][$fieldset->name]['type'] === 'content') {
              $d_fields = _filter_fields($gc_field, $mapping->content_type);
            }
            elseif ($form_state['triggering_element']['#array_parents'][1] === $fieldset->name && $form_state['values'][$fieldset->name]['type'] === 'metatag') {
              $d_fields = _filter_metatag($gc_field);
            }
          }
          else {
            $d_fields = _filter_fields($gc_field, $mapping->content_type);
          }
          $form['mapping'][$fieldset->name]['elements'][$gc_field->name] = array(
            '#type' => 'select',
            '#options' => $d_fields,
            '#title' => (isset($gc_field->label) ? $gc_field->label : $gc_field->title),
            '#default_value' => isset($mapping_data[$fieldset->name]['elements'][$gc_field->name]) ? $mapping_data[$fieldset->name]['elements'][$gc_field->name] : NULL,
            '#empty_option' => t('- Select -'),
          );
        }
      }
    }
  }
  else {
    $form = array();
    $form['info'] = array(
      '#markup' => t('Project name: @name', array('@name' => $mapping->gc_project))
        . '<br>'
        . t('GatherContent template: @gc_template', array('@gc_template' => $mapping->gc_template)),
    );
    $form['id'] = array(
      '#type' => 'value',
      '#value' => $id,
    );
    $form['updated'] = array(
      '#type' => 'value',
      '#value' => $template->updated_at,
    );

    $form['content_type'] = array(
      '#type' => 'select',
      '#title' => t('Drupal Content Types'),
      '#options' => $content_types,
      '#required' => TRUE,
      '#ajax' => array(
        'callback' => 'gc_mapping_form_mapping_get_table',
        'wrapper' => 'edit-mapping',
        'method' => 'replace',
        'effect' => 'fade',
      ),
      '#default_value' => (isset($form_state['values']['content_type']) ? $form_state['values']['content_type'] : NULL),
    );

    $form['mapping'] = array(
      '#prefix' => '<div id="edit-mapping">',
      '#suffix' => '</div>',
    );

    if (isset($form_state['values']['content_type'])) {
      foreach ($template->config as $i => $fieldset) {
        if ($fieldset->hidden === FALSE) {
          $form['mapping'][$fieldset->name] = array(
            '#type' => 'fieldset',
            '#title' => $fieldset->label,
            '#collapsible' => TRUE,
            '#collapsed' => ($i === 0 ? FALSE : TRUE),
            '#tree' => TRUE,
          );

          if ($i === 0) {
            $form['mapping'][$fieldset->name]['#prefix'] = '<div id="edit-mapping">';
          }
          if (end($template->config) === $fieldset) {
            $form['mapping'][$fieldset->name]['#suffix'] = '</div>';
          }

          if (module_exists('metatag')) {
            $form['mapping'][$fieldset->name]['type'] = array(
              '#type' => 'select',
              '#options' => array(
                'content' => t('Content'),
                'metatag' => t('Metatag'),
              ),
              '#title' => t('Type'),
              '#default_value' => isset($form_state['values'][$fieldset->name]['type']) ? $form_state['values'][$fieldset->name]['type'] : 'content',
              '#ajax' => array(
                'callback' => 'gc_mapping_form_mapping_get_table',
                'wrapper' => 'edit-mapping',
                'method' => 'replace',
                'effect' => 'fade',
              ),
            );
          }

          if (module_exists('entity_translation') && module_exists('title')
            && entity_translation_node_supported_type($form_state['values']['content_type'])
            && title_field_replacement_enabled('node', $form_state['values']['content_type'], 'title')
          ) {
            $form['mapping'][$fieldset->name]['language'] = array(
              '#type' => 'select',
              '#options' => array('und' => t('None')) + locale_language_list('name'),
              '#title' => t('Language'),
              '#default_value' => isset($form_state['values'][$fieldset->name]['language']) ? $form_state['values'][$fieldset->name]['language'] : 'und',
            );
          }

          foreach ($fieldset->elements as $gc_field) {
            $d_fields = array();
            if (isset($form_state['values']['type']) && $form_state['triggering_element']['#name'] !== 'content_type') {
              if ($form_state['triggering_element']['#array_parents'][1] === $fieldset->name && $form_state['values']['type'] === 'content') {
                $d_fields = _filter_fields($gc_field, $form_state['values']['content_type']);
              }
              elseif ($form_state['triggering_element']['#array_parents'][1] === $fieldset->name && $form_state['values']['type'] === 'metatag') {
                $d_fields = _filter_metatag($gc_field);
              }
            }
            else {
              $d_fields = _filter_fields($gc_field, $form_state['values']['content_type']);
            }
            $form['mapping'][$fieldset->name]['elements'][$gc_field->name] = array(
              '#type' => 'select',
              '#options' => $d_fields,
              '#title' => (isset($gc_field->label) ? $gc_field->label : $gc_field->title),
              '#empty_option' => t("Don't map"),
              '#default_value' => isset($form_state['values'][$fieldset->name]['elements'][$gc_field->name]) ? $form_state['values'][$fieldset->name]['elements'][$gc_field->name] : NULL,
            );
          }
        }
      }
    }
  }

  $form['submit'] = array(
    '#type' => 'submit',
    '#value' => ($new ? t('Create mapping') : t('Update mapping')),
  );

  $form['cancel'] = array(
    '#type' => 'submit',
    '#value' => t('Cancel'),
  );
  return $form;
}

/**
 * Ajax callback for mapping multistep form.
 *
 * @return array
 *   Array of form elements.
 *
 * @inheritdoc
 */
function gc_mapping_form_mapping_get_table($form, &$form_state) {
  return $form['mapping'];
}

/**
 * Validation callback for edit mapping form.
 *
 * In this function, we validate if:
 * - title of title_field is mapped
 * - each field is used only once, if we are mapping single language
 * content type or page
 * OR
 * - each field is used only once per fieldset, if we are mapping multilingual
 * - each language is used only once except `und`.
 *
 * @inheritdoc
 */
function gc_mapping_edit_form_validate($form, &$form_state) {
  // @TODO: check, if title is mapped.
  if ($form_state['triggering_element']['#id'] == 'edit-submit') {
    $form_definition_elements = array(
      'return',
      'form_build_id',
      'form_token',
      'form_id',
      'op',
    );
    $non_data_elements = array_merge($form_definition_elements, array(
      'gc_template',
      'content_type',
      'id',
      'updated',
    ));

    $mapping_data = array();
    foreach ($form_state['values'] as $key => $value) {
      if (!in_array($key, $non_data_elements) && substr_compare($key, 'tab', 0, 3) === 0) {
        $mapping_data[$key] = $value;
      }
    }
    // Check if is translatable.
    $mapping_entity = entity_load('gc_mapping', array($form_state['values']['id']));
    $mapping = reset($mapping_entity);
    $content_type = (empty($mapping->content_type) ? $form_state['values']['content_type'] : $mapping->content_type);
    $translatable = module_exists('entity_translation') && module_exists('title')
      && entity_translation_node_supported_type($content_type)
      && title_field_replacement_enabled('node', $content_type, 'title');
    // If translatable, check, if each language if used only once.
    $content_lang = array();
    $metatag_lang = array();
    if ($translatable) {
      foreach ($mapping_data as $tab_id => $tab) {
        if ($tab['language'] != 'und') {
          if (!in_array($tab['language'], ${$tab['type'] . '_lang'})) {
            ${$tab['type'] . '_type'}[] = $tab['language'];
          }
          else {
            form_set_error($tab_id . '[language]', t('Each language can be used only once'));
          }
        }
      }
    }

    $content_fields = array();
    $metatag_fields = array();
    if ($translatable) {
      foreach ($content_lang as $lang) {
        $content_fields[$lang] = array();
      }
      foreach ($metatag_lang as $lang) {
        $metatag_fields[$lang] = array();
      }
      $content_fields['und'] = $metatag_fields['und'] = array();
    }
    foreach ($mapping_data as $tab_id => $tab) {
      foreach ($tab['elements'] as $k => $element) {
        if ($translatable) {
          if (!in_array($element, ${$tab['type'] . '_fields'}[$tab['language']])) {
            ${$tab['type'] . '_fields'}[$tab['language']][] = $element;
          }
          else {
            form_set_error($tab_id, t('Each field can be mapped only once.'));
          }
        }
        else {
          if (!in_array($element, ${$tab['type'] . '_fields'})) {
            ${$tab['type'] . '_fields'}[] = $element;
          }
          else {
            form_set_error($tab_id, t('Each field can be mapped only once.'));
          }
        }
      }
    }
  }
}

/**
 * Submit callback for edit mapping form.
 *
 * @inheritdoc
 */
function gc_mapping_edit_form_submit($form, &$form_state) {
  if ($form_state['triggering_element']['#id'] == 'edit-submit') {
    $form_definition_elements = array(
      'return',
      'form_build_id',
      'form_token',
      'form_id',
      'op',
    );
    $non_data_elements = array_merge($form_definition_elements, array(
      'gc_template',
      'content_type',
      'id',
      'updated',
    ));

    $mapping_data = array();
    foreach ($form_state['values'] as $key => $value) {
      if (!in_array($key, $non_data_elements) && substr_compare($key, 'tab', 0, 3) === 0) {
        $mapping_data[$key] = $value;
      }
    }

    $mapping_entity = entity_load('gc_mapping', array($form_state['values']['id']));
    $mapping = reset($mapping_entity);
    $new = empty($mapping->data);
    if ($new) {
      $mapping->content_type = $form_state['values']['content_type'];
    }
    $mapping->data = serialize($mapping_data);
    $mapping->updated_drupal = time();
    $mapping->updated_gc = $form_state['values']['updated'];

    $tmp = new Template();
    $template = $tmp->getTemplate($mapping->gc_template_id);

    $mapping->template = serialize($template);
    $mapping->save();

    // We need to modify field for checkboxes and field instance for radios.
    foreach ($template->config as $i => $fieldset) {
      if ($fieldset->hidden === FALSE) {
        foreach ($fieldset->elements as $gc_field) {
          if ($gc_field->type === 'choice_checkbox') {
            if (!empty($mapping_data[$gc_field->name])) {
              $local_options = array();
              foreach ($gc_field->options as $option) {
                $local_options[$option->name] = $option->label;
              }

              $field_data = array(
                'field_name' => $mapping_data[$gc_field->name],
                'settings' => array(
                  'allowed_values' => $local_options,
                ),
              );
              try {
                field_update_field($field_data);
              }
              catch (Exception $e) {
                // Log something.
              }
            }
          }
          elseif ($gc_field->type === 'choice_radio') {
            if (!empty($mapping_data[$gc_field->name])) {
              $local_options = array();
              foreach ($gc_field->options as $option) {
                if ($option != end($gc_field->options)) {
                  $local_options[] = $option->name . "|" . $option->label;
                }
              }
              $instance = field_read_instance('node', $mapping_data[$gc_field->name], $mapping->content_type);
              // Make the change.
              $instance['widget']['settings']['available_options'] = implode("\n", $local_options);
              // Save the instance.
              field_update_instance($instance);
            }
          }
        }
      }
    }
    if ($new) {
      drupal_set_message(t('Mapping has been created.',
        array('@id' => $form_state['values']['id'])));
    }
    else {
      drupal_set_message(t('Mapping has been updated.',
        array('@id' => $form_state['values']['id'])));
    }
  }
  drupal_goto('admin/config/gc/mapping');
}

/**
 * Helper function.
 *
 * Use for filtering only equivalent fields.
 *
 * @param object $gc_field
 *   Type of field in GatherContent.
 * @param string $content_type
 *   Name of Drupal content type.
 *
 * @return array
 *   Associative array with equivalent fields.
 */
function _filter_fields($gc_field, $content_type) {
  $mapping_array = array(
    'files' => array(
      'file',
      'image',
    ),
    'section' => array(
      'text_long',
    ),
    'text' => array(
      'text',
      'text_long',
      'text_with_summary',
    ),
    'choice_radio' => array(
      'text',
    ),
    'choice_checkbox' => array(
      'list_text',
    ),
  );

  $instances = field_info_instances('node', $content_type);
  $fields = array();
  // Fields.
  foreach ($instances as $name => $instance) {
    $field = field_info_field($instance['field_name']);
    if (in_array($field['type'], $mapping_array[$gc_field->type])) {
      // Constrains:
      // - do not map plain text (Drupal) to rich text (GC).
      // - do not map radios (GC) to text (Drupal),
      // if widget isn't provided by select_or_other module.
      // - do not map section (GC) to plain text (Drupal).
      switch ($gc_field->type) {
        case 'text':
          if ((!$instance['settings']['text_processing'] && !$gc_field->plain_text) || $instance['widget']['module'] === 'select_or_other') {
            continue 2;
          }
          break;

        case 'choise_radio':
          if ($instance['widget']['module'] !== 'select_or_other') {
            continue 2;
          }
          break;

        case 'section':
          if (!$instance['settings']['text_processing']) {
            continue 2;
          }
          break;
      }
      $fields[$instance['field_name']] = $instance['label'];
    }
  }

  if ($gc_field->type === 'text'
    && $gc_field->plain_text
    && (!module_exists('title') || !title_field_replacement_enabled('node', $content_type, 'title'))
  ) {
    $fields['title'] = 'Title';
  }

  return $fields;
}

/**
 * Return only supported metatag fields.
 *
 * @param object $gc_field
 *   Object of field from GatherContent.
 *
 * @return array
 *   Array of supported metatag fields.
 */
function _filter_metatag($gc_field) {
  if ($gc_field->type === 'text' && $gc_field->plain_text) {
    return array(
      'title' => t('Title'),
      'description' => t('Description'),
      'abstract' => t('Abstract'),
      'keywords' => t('Keywords'),
    );
  }
  else {
    return array();
  }
}
