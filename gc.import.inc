<?php
/**
 * @file
 * Content importing for GatherContent module.
 */

include_once 'includes/Project.inc';
include_once 'includes/Content.inc';
include_once 'includes/Template.inc';

use GatherContent\Content;
use GatherContent\Project;
use GatherContent\Template;

/**
 * Multistep form function.
 *
 * @inheritdoc
 */
function gc_import_form($step = NULL) {
  // Define array for ctools multistep wizard.
  $form_info = array(
    'id' => 'gc_import',
    'path' => "admin/config/gc/import/%step",
    'show trail' => FALSE,
    'show back' => TRUE,
    'show cancel' => FALSE,
    'show return' => FALSE,
    'next callback' => 'gc_import_wizard_next',
    'finish callback' => 'gc_import_wizard_finish',
    'finish text' => t('Import'),

    // Define forms order.
    'order' => array(
      'select' => t('Select content'),
      'confirm' => t('Confirmation'),
    ),

    // Define forms.
    'forms' => array(
      'select' => array(
        'form id' => 'gc_import_form_select',
      ),
      'confirm' => array(
        'form id' => 'gc_import_form_confirm',
      ),
    ),
  );

  $object_id = 1;

  if (empty($step)) {
    // We reset the form when $step is NULL because that means they have
    // for whatever reason started over.
    gc_import_cache_clear($object_id);
    $step = 'select';
  }

  // This automatically gets defaults if there wasn't anything saved.
  $object = gc_import_cache_get($object_id);

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
function gc_import_cache_clear($id) {
  ctools_include('object-cache');
  ctools_object_cache_clear('gc_import', $id);
}

/**
 * Stores our little cache so that we can retain data from form to form.
 *
 * @param int $id
 *   Cache ID.
 * @param object $object
 *   Object with form values.
 */
function gc_import_cache_set($id, $object) {
  ctools_include('object-cache');
  ctools_object_cache_set('gc_import', $id, $object);
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
function gc_import_cache_get($id) {
  ctools_include('object-cache');
  $object = ctools_object_cache_get('gc_import', $id);
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
function gc_import_wizard_next(&$form_state) {
  gc_import_cache_set($form_state['object_id'], $form_state['object']);
}

/**
 * Import form.
 *
 * @inheritdoc
 */
function gc_import_form_select($form, &$form_state) {
  $gc_module_path = drupal_get_path('module', 'gc');
  $query = db_select('gc_mapping', 'm')
    ->distinct()
    ->fields('m', array('gc_project', 'gc_project_id'))
    ->isNotNull('data')
    ->execute();
  $projects = $query->fetchAllKeyed(1, 0);

  $form['project'] = array(
    '#type' => 'select',
    '#title' => t('Select project'),
    '#options' => $projects,
    '#empty_option' => t('- Select -'),
    '#required' => TRUE,
    '#ajax' => array(
      'callback' => 'gc_import_form_select_fetch_content',
      'wrapper' => 'edit-import',
      'method' => 'replace',
      'effect' => 'fade',
    ),
    '#default_value' => isset($form_state['object']->project_id) ? $form_state['object']->project_id : 0,
    '#description' => t('You can only see projects with mapped templates in the dropdown.'),
  );

  $form['import'] = array(
    '#prefix' => '<div id="edit-import">',
    '#suffix' => '</div>',
    '#attached' => array(
      'js' => array(
        $gc_module_path . '/js/gc-filter.js',
      ),
    ),
  );

  $form['menu'] = array(
    '#type' => 'value',
  );

  if (isset($form_state['values']['project']) || isset($form_state['object']->project_id)) {
    $project_id = isset($form_state['values']['project']) ? $form_state['values']['project'] : $form_state['object']->project_id;
    $content_obj = new Content();
    $content = $content_obj->getContents($project_id);

    $content_table = array();
    $query = new EntityFieldQuery();
    $result = $query->entityCondition('entity_type', 'gc_mapping')
      ->propertyCondition('gc_project_id', $project_id)
      ->execute();

    $query = new EntityFieldQuery();
    $nodes = $query->entityCondition('entity_type', 'node')
      ->propertyCondition('gc_mapping_id', NULL, 'IS NOT')
      ->execute();

    if (isset($result['gc_mapping'])) {
      $mapping_ids = array_keys($result['gc_mapping']);
      $mappings = entity_load('gc_mapping', $mapping_ids);
      $mapping_array = array();
      foreach ($mappings as $mapping) {
        if (!empty($mapping->data)) {
          $mapping_array[$mapping->gc_template_id] = array(
            'gc_template' => $mapping->gc_template,
            'ct' => $mapping->content_type,
          );
        }
      }

      $tmp_obj = new Template();
      $templates = $tmp_obj->getTemplates($project_id);
      foreach ($content as $item) {
        // If template is not empty, we have mapped template and item
        // isn't synced yet.
        if (!is_null($item->template_id)
          && $item->template_id != 'null'
          && isset($mapping_array[$item->template_id])
        ) {
          $content_table[$item->id] = array(
            'status' => array(
              'data' => '<div class="gc-item--status--color" style="background: ' . $item->status->data->color . ';"></div>' . $item->status->data->name,
              'class' => array('gc-item', 'status-item'),
            ),
            'title' => array(
              'data' => $item->name,
              'class' => array('gc-item', 'gc-item--name'),
            ),
            'updated' => array(
              'data' => date('F d, Y - H:i', strtotime($item->updated_at->date)),
              'class' => array('gc-item', 'gc-item-date'),
              'data-date' => date('Y-m-d.H:i:s', strtotime($item->updated_at->date)),
            ),
            'template' => array(
              'data' => $mapping_array[$item->template_id]['gc_template'],
              'class' => array('template-name-item'),
            ),
            'menu' => array(
              'data' => array(
                '#type' => 'select',
                '#options' =>
                  array(
                    0 => t("- Don't create menu item -"),
                    -1 => t("Parent being imported"),
                  ) + menu_parent_options(menu_get_menus(), $mapping_array[$item->template_id]['ct']),
                '#title' => t('Menu'),
                '#title_display' => 'invisible',
                '#name' => "menu[$item->id]",
              ),
            ),
          );
        }
      }

      $header = array(
        'status' => t('Status'),
        'title' => t('Item Name'),
        'updated' => t('Last updated in GatherContent'),
        'template' => t('GatherContent Template Name'),
        'menu' => t('Menu'),
      );

      $form['import']['content'] = array(
        '#type' => 'gc_tableselect',
        '#header' => $header,
        '#options' => $content_table,
        '#empty' => t('No content available.'),
        '#filterwrapper' => array(
          'filter_wrapper' => array('gc-table--filter-wrapper', 'clearfix'),
          'counter_wrapper' => array('gc-table--counter', 'clearfix'),
        ),
        '#filterdescription' => t('You can only see items with mapped templates in the table.'),
        '#default_value' => isset($form_state['object']->nodes) ? $form_state['object']->nodes : NULL,
      );
    }

    $form['import']['back_button'] = array(
      '#type' => 'markup',
      '#markup' => l(t('Back'), '/admin/config/gc/import', array('attributes' => array('class' => array('button')))),
    );
  }

  return $form;
}

/**
 * AJAX callback for fetching content from GatherContent.
 *
 * @inheritdoc
 */
function gc_import_form_select_fetch_content($form, &$form_state) {
  return $form['import'];
}

/**
 * Validation function for import select screen.
 *
 * We are validating, if every item with "parent being imported" menu has
 * parent in current import selection.
 *
 * @inheritdoc
 */
function gc_import_form_select_validate($form, &$form_state) {
  if ($form_state['triggering_element']['#type'] === 'submit') {
    $stack = array();
    $import_content = array_filter($form_state['values']['content']);
    $menu = array_intersect_key(array_filter($form_state['values']['menu']), array_filter($form_state['values']['content']));
    if (!empty($import_content) && !empty($menu)) {

      foreach ($import_content as $k => $value) {
        if ((isset($menu[$value]) && $menu[$value] != -1) || !isset($menu[$value])) {
          $stack[] = $value;
          unset($import_content[$k]);
        }
      }

      if (!empty($import_content)) {
        // Load all by project_id.
        $first = reset($import_content);
        $content_obj = new Content();
        $content = $content_obj->getContent($first);

        $contents_source = $content_obj->getContents($content->project_id);
        $content = array();

        foreach ($contents_source as $value) {
          $content[$value->id] = $value;
        }

        $num_of_repeats = 0;
        $size = count($import_content);

        while (!empty($import_content)) {
          $current = reset($import_content);
          if (in_array($content[$current]->parent_id, $stack)) {
            $stack[] = $current;
            array_shift($import_content);
          }
          else {
            array_shift($import_content);
            array_push($import_content, $current);
            $num_of_repeats++;
            if ($num_of_repeats >= ($size * $size)) {
              form_set_error('form', t("Please check your menu selection, some of items don't have parent in import."));
              $import_content = array();
            }
          }
        }
      }
    }
  }
}

/**
 * Submit handler for `gc_import` form.
 *
 * @inheritdoc
 */
function gc_import_form_select_submit($form, &$form_state) {
  $form_state['object']->project_id = $form_state['values']['project'];
  $form_state['object']->nodes = array_filter($form_state['values']['content']);
  $form_state['object']->menu = array_intersect_key(array_filter($form_state['values']['menu']), array_filter($form_state['values']['content']));
}

/**
 * Smg.
 *
 * @inheritdoc
 */
function gc_import_form_confirm($form, &$form_state) {
  $form['title'] = array(
    'form_title' => array(
      '#type' => 'html_tag',
      '#tag' => 'h2',
      '#value' => format_plural(count($form_state['object']->nodes),
        'Confirm import selection (@count item)',
        'Confirm import selection (@count items)'
      ),
    ),
    'form_description' => array(
      '#type' => 'html_tag',
      '#tag' => 'p',
      '#value' => t('Please review your import selection before importing.'),
    ),
  );

  $header = array(
    'status' => t('Status'),
    'title' => t('Item name'),
    'template' => t('GatherContent Template'),
  );

  $options = array();

  $tmp_obj = new Template();
  $templates = $tmp_obj->getTemplates($form_state['object']->project_id);

  foreach ($form_state['object']->nodes as $node) {
    $content_obj = new Content();
    $content = $content_obj->getContent($node);

    $options[$content->id] = array(
      'status' => '<div style="width:20px; height: 20px; float: left; margin-right: 5px; background: ' . $content->status->data->color . ';"></div>' . $content->status->data->name,
      'title' => $content->name,
      'template' => $templates[$content->template_id],
    );
  }

  $table = theme('table', array('header' => $header, 'rows' => $options));
  $form['table'] = array(
    '#markup' => render($table),
  );

  $options = array();
  $project_obj = new Project();
  $statuses = $project_obj->getStatuses($form_state['object']->project_id);
  foreach ($statuses as $status) {
    $options[$status->id] = $status->name;
  }

  $form['status'] = array(
    '#type' => 'select',
    '#options' => $options,
    '#title' => t('After successful import change status to:'),
    '#empty_option' => t("- Don't change status -"),
  );

  return $form;
}

/**
 * Grdg.
 *
 * @inheritdoc
 */
function gc_import_form_confirm_submit($form, &$form_state) {
  $form_state['object']->status = $form_state['values']['status'];
  $uuid = _gc_uuid_generate();

  $operation = entity_create('gc_operation', array(
    'uuid' => $uuid,
    'type' => 'import',
  ));
  entity_save('gc_operation', $operation);

  $operations = array();

  $stack = array();
  $import_content = $form_state['object']->nodes;

  foreach ($import_content as $k => $value) {
    if ((isset($form_state['object']->menu[$value]) && $form_state['object']->menu[$value] != -1) || !isset($form_state['object']->menu[$value])) {
      $parent_menu_item = isset($form_state['object']->menu[$value]) ? $form_state['object']->menu[$value] : NULL;
      $operations[] = array(
        'gc_import_process',
        array($value, $form_state['object']->status, $uuid, $parent_menu_item),
      );
      $stack[] = $value;
      unset($import_content[$k]);
    }
  }

  // If there are any items
  if (!empty($import_content)) {
    // Load all by project_id.
    $first = reset($import_content);
    $content_obj = new Content();
    $content = $content_obj->getContent($first);

    $contents_source = $content_obj->getContents($content->project_id);
    $content = array();

    foreach ($contents_source as $value) {
      $content[$value->id] = $value;
    }

    while (!empty($import_content)) {
      $current = reset($import_content);
      if (in_array($content[$current]->parent_id, $stack)) {
        $parent_menu_item = 'node:' . $content[$current]->parent_id;
        $operations[] = array(
          'gc_import_process',
          array(
            $current,
            $form_state['object']->status,
            $uuid,
            $parent_menu_item,
          ),
        );
        $stack[] = $current;
        array_shift($import_content);
      }
      else {
        array_shift($import_content);
        array_push($import_content, $current);
      }
    }
  }

  $batch = array(
    'title' => t('Importing content ...'),
    'operations' => $operations,
    'finished' => 'gc_import_finished',
    'file' => drupal_get_path('module', 'gc') . '/gc.import.inc',
    'init_message' => t('Import is starting ...'),
    'progress_message' => t('Processed @current out of @total.'),
    'error_message' => t('An error occurred during processing'),
  );

  batch_set($batch);
}

/**
 * Finish callback for multistep form.
 *
 * This is never going to be called because we are redirecting after batch.
 *
 * @inheritdoc
 */
function gc_import_wizard_finish($form, &$form_state) {
}

/**
 * Batch operation callback.
 *
 * We are doing real import thing here.
 *
 * @param int $content_id
 *   ID of content we want to import.
 * @param int $status_id
 *   ID of status, if 0 then we don't want to change status.
 * @param string $uuid
 *   UUID of operation.
 * @param int|null $parent_menu_item
 *   ID of mlid.
 * @param array $context
 *   Context of operation.
 */
function gc_import_process($content_id, $status_id, $uuid, $parent_menu_item = NULL, &$context) {
  if (($nid = _gc_fetcher($content_id, $uuid, $status_id, NULL, $parent_menu_item)) !== FALSE) {
    if ($status_id != 0) {
      // Change status.
      $content_obj = new Content();
      $content_obj->updateStatus($content_id, $status_id);
    }
  };
  $context['results']['uuid'] = $uuid;
}

/**
 * Finished callback.
 *
 * @inheritdoc
 */
function gc_import_finished($success, $results, $operations) {
  if ($success) {
    // Select all items with uuid.
    $query = new EntityFieldQuery();
    $result = $query->entityCondition('entity_type', 'gc_operation_item')
      ->propertyCondition('operation_uuid', $results['uuid'])
      ->execute();
    if (isset($result['gc_operation_item'])) {
      $operation_items = entity_load('gc_operation_item', array_keys($result['gc_operation_item']));
      $success_counter = 0;
      foreach ($operation_items as $operation_item) {
        if ($operation_item->status === 'Success') {
          $success_counter++;
        }
      }
      $unsuccessful = count($result['gc_operation_item']) - $success_counter;
      drupal_set_message(format_plural($success_counter, '1 item was imported successfully.', '@count items were imported successfully.'));
      if ($unsuccessful > 0) {
        drupal_set_message(format_plural($unsuccessful, '1 item was not imported. Check errors below.', '@count items were not imported. Check errors below.'), 'error');
      }
    }
    drupal_goto('admin/config/gc/import/result/' . $results['uuid']);
  }
  else {
    $error_operation = reset($operations);
    drupal_set_message(
      t('An error occurred while processing @operation with arguments : @args',
        array(
          '@operation' => $error_operation[0],
          '@args' => print_r($error_operation[0], TRUE),
        )
      ),
      'error'
    );
  }
}
