<?php

/**
 * @file
 * Multistep mapping form.
 */

include_once "includes/Project.inc";
include_once "includes/Template.inc";

use GatherContent\Project;
use GatherContent\Template;

/**
 * GatherContent template import.
 *
 * In this form, we load templates from GC and create empty Mappings for them.
 *
 * @inheritdoc
 */
function gc_mapping_form_templates($form, &$form_state) {
  $pr_obj = new Project();
  $projects = $pr_obj->getProjects();

  $template_obj = new Template();

  $form = array();

  $form['projects'] = array(
    '#type' => 'vertical_tabs',
  );

  foreach ($projects as $project_id => $project) {
    $templates = $template_obj->getTemplates($project_id);
    $form['p' . $project_id] = array(
      '#type' => 'fieldset',
      '#title' => $project,
      '#group' => 'projects',
      '#tree' => TRUE,
    );
    $form['p' . $project_id]['templates'] = array(
      '#type' => 'checkboxes',
      '#title' => $project,
      '#options' => $templates,
    );
  }

  $form['submit'] = array(
    '#type' => 'submit',
    '#value' => t('Select'),
  );

  $form['close'] = array(
    '#type' => 'submit',
    '#value' => t('Close'),
  );

  return $form;
}

/**
 * Submit callback for GatherContent template import.
 *
 * @inheritdoc
 */
function gc_mapping_form_templates_submit($form, &$form_state) {
  if ($form_state['triggering_element']['#id'] == 'edit-submit') {
    // Load all projects.
    $pr_obj = new Project();
    $projects = $pr_obj->getProjects();
    foreach ($form_state['values'] as $k => $tree) {
      if (!is_array($tree)) {
        continue;
      }
      $templates = array_filter($form_state['values'][$k]['templates']);
      foreach ($templates as $template_id => $selected) {
        $tmp_obj = new Template();
        $template = $tmp_obj->getTemplate($template_id);
        $mapping_values = array(
          'gc_project_id' => $template->project_id,
          'gc_project' => $projects[$template->project_id],
          'gc_template_id' => $template_id,
          'gc_template' => $template->name,
          'created' => time(),
          'updated_gc' => $template->updated_at,
        );
        $mapping = entity_create('gc_mapping', $mapping_values);
        if (is_object($mapping)) {
          $mapping->save();
        }
      }
    }
  }

  drupal_goto('admin/config/gc/mapping');
}

/**
 * DELETE MAPPING.
 */

/**
 * Delete mapping form.
 *
 * @inheritdoc
 */
function gc_mapping_delete_form($form, &$form_state, $id) {
  $form = array();
  $form['id'] = array(
    '#type' => 'value',
    '#value' => $id,
  );

  return confirm_form(
    $form,
    t('Are you sure you want to delete this item?'),
    'admin/config/gc/mapping',
    t('This action cannot be undone.'),
    t('Delete'),
    t('Cancel')
  );
}

/**
 * Submit callback for delete mapping form.
 *
 * @inheritdoc
 */
function gc_mapping_delete_form_submit($form, &$form_state) {
  entity_delete('gc_mapping', $form_state['values']['id']);
  drupal_set_message(t('You removed mapping #@id', array('@id' => $form_state['values']['id'])));
  $form_state['redirect'] = 'admin/config/gc/mapping';
}

/**
 * EDIT MAPPING.
 */

/**
 * Edit mapping form.
 *
 * @TODO: validation, if at least one field is mapped.
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
  if (!$new) {
    $mapping_data = unserialize($mapping->data);
    $content_type = $content_types[$mapping->content_type];

    $form = array();
    $form['info'] = array(
      '#markup' => t('Project name: @name', array('@name' => $mapping->gc_project))
        . '<br>'
        . t('GatherContent template: @gc_template', array('@gc_template' => $mapping->gc_template))
        . '<br>'
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
    foreach ($template->config as $i => $fieldset) {
      if ($fieldset->hidden === FALSE) {
        $form['mapping'][$fieldset->name] = array(
          '#type' => 'fieldset',
          '#title' => $fieldset->label,
          '#collapsible' => TRUE,
          '#collapsed' => ($i === 0 ? FALSE : TRUE),
        );

        foreach ($fieldset->elements as $gc_field) {
          $d_fields = _filter_fields($gc_field, $mapping->content_type);
          $form['mapping'][$fieldset->name][$gc_field->name] = array(
            '#type' => 'select',
            '#options' => $d_fields,
            '#title' => (isset($gc_field->label) ? $gc_field->label : $gc_field->title),
            '#default_value' => isset($mapping_data[$gc_field->name]) ? $mapping_data[$gc_field->name] : NULL,
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
      '#states' => array(
        'invisible' => array(
          ':input[name="gc_template"]' => array('value' => ''),
        ),
      ),
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
          );

          if ($i === 0) {
            $form['mapping'][$fieldset->name]['#prefix'] = '<div id="edit-mapping">';
          }

          if (end($template->config) === $fieldset) {
            $form['mapping'][$fieldset->name]['#suffix'] = '</div>';
          }

          foreach ($fieldset->elements as $gc_field) {
            $d_fields = _filter_fields($gc_field, $form_state['values']['content_type']);
            $form['mapping'][$fieldset->name][$gc_field->name] = array(
              '#type' => 'select',
              '#options' => $d_fields,
              '#title' => (isset($gc_field->label) ? $gc_field->label : $gc_field->title),
              '#empty_option' => t('Don\'t map'),
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
    $used = array();
    foreach ($form_state['values'] as $key => $value) {
      if (!in_array($key, $non_data_elements)) {
        if (!in_array($value, $used)) {
          $mapping_data[$key] = $value;
          $used[] = $value;
        }
        else {
          form_set_error($key, t('Each field can be mapped just once'));
        }
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
    $mapping->save();

    $tmp = new Template();
    $template = $tmp->getTemplate($mapping->gc_template_id);

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
      drupal_set_message(t('Mapping #@id has been created.',
        array('@id' => $form_state['values']['id'])));
    }
    else {
      drupal_set_message(t('Mapping #@id has been updated.',
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

  if ($gc_field->type === 'text' && $gc_field->plain_text) {
    $fields['title'] = 'Title';
  }

  return $fields;
}