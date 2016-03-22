<?php

include_once "includes/Project.inc";
include_once "includes/Content.inc";

use GatherContent\Content;
use GatherContent\Project;

/**
 * Multistep form function.
 *
 * @inheritdoc
 */
function gc_import_form($step = NULL) {
  // Define array for ctools multistep wizard.
  $form_info = array(
    'id' => 'gc_import',
    'path' => "admin/config/content/gc/import/%step",
    'show trail' => FALSE,
    'show back' => FALSE,
    'show cancel' => FALSE,
    'show return' => FALSE,
    'next callback' => 'gc_import_wizard_next',
    'finish callback' => 'gc_import_wizard_finish',

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
    drupal_goto("admin/config/content/gc/mapping");
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
  $project_obj = new Project();
  $projects = $project_obj->getProjects();

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
  );

  $form['import'] = array(
    '#prefix' => '<div id="edit-import">',
    '#suffix' => '</div>',
  );

  if (isset($form_state['values']['project'])) {
    $content_obj = new Content();
    $content = $content_obj->getContents($form_state['values']['project']);

    $content_table = array();
    $query = new EntityFieldQuery();
    $result = $query->entityCondition('entity_type', 'gc_mapping')
      ->propertyCondition('gc_project_id', $form_state['values']['project'])
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
        $mapping_array[] = $mapping->gc_template_id;
      }

      foreach ($content as $item) {
        // If template is not empty, we have mapped template and item
        // isn't synced yet.
        if (!is_null($item->template_id)
          && $item->template_id != 'null'
          && in_array($item->template_id, $mapping_array)
        ) {
          $content_table[$item->id] = array(
            'status' => $item->status->data->name,
            'title' => $item->name,
            'updated' => $item->updated_at->date,
          );
        }
      }

      $header = array(
        'status' => t('Status'),
        'title' => t('Title'),
        'updated' => t('Updated in GatherContent'),
      );

      $form['import']['content'] = array(
        '#type' => 'tableselect',
        '#header' => $header,
        '#options' => $content_table,
      );
    }
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
 * Submit handler for `gc_import` form.
 *
 * @inheritdoc
 */
function gc_import_form_select_submit($form, &$form_state) {
  $form_state['object']->project_id = $form_state['values']['project'];
  $form_state['object']->nodes = array_filter($form_state['values']['content']);
}

/**
 * Smg.
 *
 * @inheritdoc
 */
function gc_import_form_confirm($form, &$form_state) {
  $form['title'] = array(
    '#markup' => '<h2>Do you really want to import following items?</h2>',
  );

  $header = array(
    'status' => t('Status'),
    'title' => t('Title'),
    'updated' => t('Updated in GatherContent'),
  );

  $options = array();

  foreach ($form_state['object']->nodes as $node) {
    $content_obj = new Content();
    $content = $content_obj->getContent($node);

    $options[$content->id] = array(
      'status' => $content->status->data->name,
      'title' => $content->name,
      'updated' => $content->updated_at->date,
    );
  }

  $table = theme('table', array('header' => $header, 'rows' => $options));
  $form['table'] = array(
    '#markup' => render($table),
  );

  return $form;
}

/**
 * Grdg.
 *
 * @inheritdoc
 */
function gc_import_form_confirm_submit($form, &$form_state) {
  $operations = array();

  foreach ($form_state['object']->nodes as $value) {
    $operations[] = array('gc_import_process', array($value));
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
 */
function gc_import_process($content_id, &$context) {
  _gc_fetcher($content_id);
}

/**
 * Finished callback.
 *
 * @inheritdoc
 */
function gc_import_finished($success, $results, $operations) {
  if ($success) {
    drupal_set_message(t('Content was updated successfuly"'));
    drupal_goto('/admin/content');
  }
  else {
    // An error occurred.
    // $operations contains the operations that remained unprocessed.
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