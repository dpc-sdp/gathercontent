<?php

/**
 * Implements hook_views_default_views().
 */
function gc_views_default_views() {
  $view = new view();
  $view->name = 'mapping';
  $view->description = '';
  $view->tag = 'default';
  $view->base_table = 'gc_mapping';
  $view->human_name = 'mapping';
  $view->core = 7;
  $view->api_version = '3.0';
  $view->disabled = FALSE; /* Edit this to true to make a default view disabled initially */

  /* Display: Master */
  $handler = $view->new_display('default', 'Master', 'default');
  $handler->display->display_options['title'] = 'Mapping';
  $handler->display->display_options['use_more_always'] = FALSE;
  $handler->display->display_options['access']['type'] = 'none';
  $handler->display->display_options['cache']['type'] = 'none';
  $handler->display->display_options['query']['type'] = 'views_query';
  $handler->display->display_options['exposed_form']['type'] = 'basic';
  $handler->display->display_options['pager']['type'] = 'full';
  $handler->display->display_options['pager']['options']['items_per_page'] = '10';
  $handler->display->display_options['style_plugin'] = 'table';
  $handler->display->display_options['style_options']['columns'] = array(
    'gc_project' => 'gc_project',
    'gc_template' => 'gc_template',
    'content_type_name' => 'content_type_name',
    'updated_drupal' => 'updated_drupal',
    'updated_gc' => 'updated_gc',
    'edit_mapping' => 'edit_mapping',
    'delete_mapping' => 'edit_mapping',
  );
  $handler->display->display_options['style_options']['default'] = '-1';
  $handler->display->display_options['style_options']['info'] = array(
    'gc_project' => array(
      'sortable' => 1,
      'default_sort_order' => 'asc',
      'align' => '',
      'separator' => '',
      'empty_column' => 0,
    ),
    'gc_template' => array(
      'sortable' => 1,
      'default_sort_order' => 'asc',
      'align' => '',
      'separator' => '',
      'empty_column' => 0,
    ),
    'content_type_name' => array(
      'sortable' => 1,
      'default_sort_order' => 'asc',
      'align' => '',
      'separator' => '',
      'empty_column' => 0,
    ),
    'updated_drupal' => array(
      'sortable' => 1,
      'default_sort_order' => 'asc',
      'align' => '',
      'separator' => '',
      'empty_column' => 0,
    ),
    'updated_gc' => array(
      'sortable' => 1,
      'default_sort_order' => 'asc',
      'align' => '',
      'separator' => '',
      'empty_column' => 0,
    ),
    'edit_mapping' => array(
      'align' => '',
      'separator' => '',
      'empty_column' => 0,
    ),
    'delete_mapping' => array(
      'align' => '',
      'separator' => '',
      'empty_column' => 0,
    ),
  );
  /* Field: Mapping: GC Project */
  $handler->display->display_options['fields']['gc_project']['id'] = 'gc_project';
  $handler->display->display_options['fields']['gc_project']['table'] = 'gc_mapping';
  $handler->display->display_options['fields']['gc_project']['field'] = 'gc_project';
  $handler->display->display_options['fields']['gc_project']['label'] = 'Project';
  /* Field: Mapping: GC Template */
  $handler->display->display_options['fields']['gc_template']['id'] = 'gc_template';
  $handler->display->display_options['fields']['gc_template']['table'] = 'gc_mapping';
  $handler->display->display_options['fields']['gc_template']['field'] = 'gc_template';
  $handler->display->display_options['fields']['gc_template']['label'] = 'GatherContent Template';
  /* Field: Mapping: Content type name */
  $handler->display->display_options['fields']['content_type_name']['id'] = 'content_type_name';
  $handler->display->display_options['fields']['content_type_name']['table'] = 'gc_mapping';
  $handler->display->display_options['fields']['content_type_name']['field'] = 'content_type_name';
  $handler->display->display_options['fields']['content_type_name']['label'] = 'Content type';
  /* Field: Mapping: Updated (Drupal) */
  $handler->display->display_options['fields']['updated_drupal']['id'] = 'updated_drupal';
  $handler->display->display_options['fields']['updated_drupal']['table'] = 'gc_mapping';
  $handler->display->display_options['fields']['updated_drupal']['field'] = 'updated_drupal';
  $handler->display->display_options['fields']['updated_drupal']['empty'] = 'Never';
  $handler->display->display_options['fields']['updated_drupal']['date_format'] = 'custom';
  $handler->display->display_options['fields']['updated_drupal']['custom_date_format'] = 'd.m.Y H:i:s';
  $handler->display->display_options['fields']['updated_drupal']['second_date_format'] = 'long';
  /* Field: Mapping: Updated (GC) */
  $handler->display->display_options['fields']['updated_gc']['id'] = 'updated_gc';
  $handler->display->display_options['fields']['updated_gc']['table'] = 'gc_mapping';
  $handler->display->display_options['fields']['updated_gc']['field'] = 'updated_gc';
  $handler->display->display_options['fields']['updated_gc']['label'] = 'Updated (GatherContent)';
  $handler->display->display_options['fields']['updated_gc']['date_format'] = 'custom';
  $handler->display->display_options['fields']['updated_gc']['custom_date_format'] = 'd.m.Y H:i:s';
  $handler->display->display_options['fields']['updated_gc']['second_date_format'] = 'long';
  /* Field: Mapping: Edit link */
  $handler->display->display_options['fields']['edit_mapping']['id'] = 'edit_mapping';
  $handler->display->display_options['fields']['edit_mapping']['table'] = 'gc_mapping';
  $handler->display->display_options['fields']['edit_mapping']['field'] = 'edit_mapping';
  $handler->display->display_options['fields']['edit_mapping']['label'] = 'Operations';
  /* Field: Mapping: Delete link */
  $handler->display->display_options['fields']['delete_mapping']['id'] = 'delete_mapping';
  $handler->display->display_options['fields']['delete_mapping']['table'] = 'gc_mapping';
  $handler->display->display_options['fields']['delete_mapping']['field'] = 'delete_mapping';
  $handler->display->display_options['fields']['delete_mapping']['label'] = '';
  $handler->display->display_options['fields']['delete_mapping']['element_label_colon'] = FALSE;

  /* Display: Page */
  $handler = $view->new_display('page', 'Page', 'page');
  $handler->display->display_options['path'] = 'admin/config/content/gc/mapping';
  $handler->display->display_options['menu']['type'] = 'normal';
  $handler->display->display_options['menu']['title'] = 'Mapping';
  $handler->display->display_options['menu']['weight'] = '0';
  $handler->display->display_options['menu']['name'] = 'management';
  $handler->display->display_options['menu']['context'] = 0;
  $handler->display->display_options['menu']['context_only_inline'] = 0;

  $views[$view->name] = $view;

  return $views;
}