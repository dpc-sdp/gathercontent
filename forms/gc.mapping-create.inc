<?php

/**
 * @file
 * Multistep mapping form.
 */

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
    $remote_templates = $template_obj->getTemplates($project_id);
    $query = db_select('gc_mapping', 'm')
      ->distinct()
      ->fields('m', array('gc_template', 'gc_template_id'))
      ->execute();
    $local_templates = $query->fetchAllKeyed(1, 0);

    $templates = array_diff_assoc($remote_templates, $local_templates);

    if (empty($templates)) {
      continue;
    }

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
          'template' => serialize($template),
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