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
 * Multistep form function.
 *
 * @inheritdoc
 */
function gc_mapping_form($step = NULL) {
  // Define array for ctools multistep wizard.
  $form_info = array(
    'id' => 'gc_mapping',
    'path' => "admin/config/gc/mapping/create/%step",
    'show trail' => FALSE,
    'show back' => FALSE,
    'show cancel' => FALSE,
    'show return' => FALSE,
    'next callback' => 'gc_mapping_wizard_next',
    'finish callback' => 'gc_mapping_wizard_finish',

    // Define forms order.
    'order' => array(
      'project' => t('Select project'),
      'mapping' => t('Map fields'),
    ),

    // Define forms.
    'forms' => array(
      'project' => array(
        'form id' => 'gc_mapping_form_project',
      ),
      'mapping' => array(
        'form id' => 'gc_mapping_form_mapping',
      ),
    ),
  );

  $object_id = 1;

  if (empty($step)) {
    // We reset the form when $step is NULL because that means they have
    // for whatever reason started over.
    gc_mapping_cache_clear($object_id);
    $step = 'project';
  }

  // This automatically gets defaults if there wasn't anything saved.
  $object = gc_mapping_cache_get($object_id);

  // Live $form_state changes.
  $form_state = array(
    // Put our object and ID into the form state cache so we can easily find it.
    'object_id' => $object_id,
    'object' => &$object,
  );

  // Send this all off to our form. This is like drupal_get_form only wizardy.
  ctools_include('wizard');
  $form = ctools_wizard_multistep_form($form_info, $step, $form_state);
  $output = drupal_render($form);

  if ($output === FALSE || !empty($form_state['complete'])) {
    drupal_set_message(t('New mapping has been saved.'));
    drupal_goto("admin/config/gc/mapping");
  }

  return $output;
}

/**
 * Clears the wizard cache.
 *
 * @param int $id
 *   Cache ID.
 */
function gc_mapping_cache_clear($id) {
  ctools_include('object-cache');
  ctools_object_cache_clear('gc_mapping', $id);
}

/**
 * Stores our little cache so that we can retain data from form to form.
 *
 * @param int $id
 *   Cache ID.
 * @param object $object
 *   Object with form values.
 */
function gc_mapping_cache_set($id, $object) {
  ctools_include('object-cache');
  ctools_object_cache_set('gc_mapping', $id, $object);
}

/**
 * Gets the current object from the cache, or default.
 *
 * @param int $id
 *   Cache ID.
 *
 * @return object
 *   Cache with stored stuff.
 */
function gc_mapping_cache_get($id) {
  ctools_include('object-cache');
  $object = ctools_object_cache_get('gc_mapping', $id);
  if (!$object) {
    // Create a default object.
    $object = new stdClass();
  }

  return $object;
}

/**
 * Handles the 'next' click on the add/edit pane form wizard.
 *
 * All we need to do is store the updated pane in the cache.
 */
function gc_mapping_wizard_next(&$form_state) {
  gc_mapping_cache_set($form_state['object_id'], $form_state['object']);
}

/**
 * Handles the 'finish' click on teh add/edit pane form wizard.
 *
 * All we need to do is set a flag so the return can handle adding
 * the pane.
 */
function gc_mapping_wizard_finish(&$form_state) {
  $data = $form_state['object'];
  $tmp = new Template();
  $template = $tmp->getTemplate($data->gc_template);

  $mapping_values = array(
    'gc_project_id' => $data->project_id,
    'gc_project' => $data->projects[$data->project_id],
    'gc_template_id' => $data->gc_template,
    'gc_template' => $template->name,
    'content_type' => $data->content_type,
    'created' => time(),
    'updated_gc' => $template->updated_at,
    'data' => serialize($data->mapping),
  );
  $mapping = entity_create('gc_mapping', $mapping_values);
  if (is_object($mapping)) {
    $mapping->save();
  }
  else {
    watchdog('gathercontent', 'Mapping cannot be created with following values: @values',
      array('@values' => print_r($mapping_values, TRUE)), WATCHDOG_ERROR);
  }

  // We need to modify field for checkboxes and field instance for radios.
  foreach ($template->config as $i => $fieldset) {
    if ($fieldset->hidden === FALSE) {
      foreach ($fieldset->elements as $gc_field) {
        if ($gc_field->type === 'choice_checkbox') {
          if (!empty($data->mapping[$gc_field->name])) {
            $local_options = array();
            foreach ($gc_field->options as $option) {
              $local_options[$option->name] = $option->label;
            }

            $field_data = array(
              'field_name' => $data->mapping[$gc_field->name],
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
          if (!empty($data->mapping[$gc_field->name])) {
            $local_options = array();
            foreach ($gc_field->options as $option) {
              if ($option != end($gc_field->options)) {
                $local_options[] = $option->name . "|" . $option->label;
              }
            }
            $instance = field_read_instance('node', $data->mapping[$gc_field->name], $data->content_type);
            // Make the change.
            $instance['widget']['settings']['available_options'] = implode("\n", $local_options);
            // Save the instance.
            field_update_instance($instance);
          }
        }
      }
    }
  }
}

/**
 * First step of multistep mapping form.
 *
 * In this form, we load projects associated by GC user accounts and user
 * for of them for mapping.
 *
 * @inheritdoc
 */
function gc_mapping_form_project($form, &$form_state) {
  $project = new Project();
  $projects = $project->getProjects();
  $form_state['object']->projects = $projects;

  $form['project_id'] = array(
    '#type' => 'radios',
    '#title' => t('Select project'),
    '#options' => $projects,
    '#required' => TRUE,
  );

  return $form;
}

/**
 * Submit callback for project select step.
 *
 * @inheritdoc
 */
function gc_mapping_form_project_submit($form, &$form_state) {
  $form_state['object']->project_id = $form_state['values']['project_id'];
}

/**
 * Second step in multistep form.
 *
 * In this form, user will map fields from GC template to Drupal content types.
 *
 * @inheritdoc
 */
function gc_mapping_form_mapping($form, &$form_state) {
  $project_id = $form_state['object']->project_id;

  $template_obj = new Template();
  $templates = $template_obj->getTemplates($project_id);

  $content_types_original = node_type_get_types();
  $content_types = array();
  foreach ($content_types_original as $ct) {
    $content_types[$ct->type] = $ct->name;
  }

  $form['gc_template'] = array(
    '#type' => 'select',
    '#title' => t('GatherContent template'),
    '#options' => $templates,
    '#required' => TRUE,
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
  );

  $form['mapping'] = array(
    '#prefix' => '<div id="edit-mapping">',
    '#suffix' => '</div>',
  );

  if (isset($form_state['values']['gc_template']) && isset($form_state['values']['content_type'])) {
    $tmp = new Template();
    $template = $tmp->getTemplate($form_state['values']['gc_template']);

    foreach ($template->config as $i => $fieldset) {
      if ($fieldset->hidden === FALSE) {
        $form['mapping'][$fieldset->name] = array(
          '#type' => 'fieldset',
          '#title' => $fieldset->label,
          '#collapsible' => TRUE,
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
            '#empty_option' => t('- Select -'),
          );
        }
      }
    }
  }

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
 * Submit callback for mapping form.
 *
 * @inheritdoc
 */
function gc_mapping_form_mapping_submit($form, &$form_state) {
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
  ));
  foreach ($form_state['values'] as $key => $value) {
    if (!in_array($key, $non_data_elements)
    ) {
      $form_state['object']->mapping[$key] = $value;
    }
  }
  $form_state['object']->gc_template = $form_state['values']['gc_template'];
  $form_state['object']->content_type = $form_state['values']['content_type'];
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
  // @TODO: add checks for field format and widgets
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
          if (!$instance['settings']['text_processing'] && !$gc_field->plain_text) {
            continue;
          }
          break;

        case 'choise_radio':
          if ($instance['widget']['module'] !== 'select_or_other') {
            continue;
          }
          break;

        case 'section':
          if (!$instance['settings']['text_processing']) {
            continue;
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
  $mapping_data = unserialize($mapping->data);
  $content_types = node_type_get_names();
  $content_type = $content_types[$mapping->content_type];

  $tmp = new Template();
  $template = $tmp->getTemplate($mapping->gc_template_id);

  $form = array();
  $form['info'] = array(
    '#markup' => t('Project name: @name', array('@name' => $mapping->gc_project))
      . '<br>'
      . t('GatherContent template: @gc_template', array('@gc_template' => $mapping->gc_template))
      . '<br>'
      . t('Drupal content type: @content_type', array('@content_type' => $content_type)),
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

  $form['submit'] = array(
    '#type' => 'submit',
    '#value' => t('Update mapping'),
  );

  $form['cancel'] = array(
    '#type' => 'submit',
    '#value' => t('Cancel'),
  );
  return $form;
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
      if (!in_array($key, $non_data_elements)
      ) {
        $mapping_data[$key] = $value;
      }
    }

    $mapping_entity = entity_load('gc_mapping', array($form_state['values']['id']));
    $mapping = reset($mapping_entity);
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

    drupal_set_message(t('Mapping #@id has been updated.',
      array('@id' => $form_state['values']['id'])));
  }
  drupal_goto('admin/config/gc/mapping');
}
