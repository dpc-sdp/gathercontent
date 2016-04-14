<?php

/**
 * Implements hook_views_default_views().
 */
function gc_views_default_views() {
  $view = new view();
  $view->name = 'import_result_page';
  $view->description = '';
  $view->tag = 'default';
  $view->base_table = 'node';
  $view->human_name = 'Import result page';
  $view->core = 7;
  $view->api_version = '3.0';
  $view->disabled = FALSE; /* Edit this to true to make a default view disabled initially */

  /* Display: Master */
  $handler = $view->new_display('default', 'Master', 'default');
  $handler->display->display_options['title'] = 'Import result page';
  $handler->display->display_options['use_more_always'] = FALSE;
  $handler->display->display_options['access']['type'] = 'perm';
  $handler->display->display_options['cache']['type'] = 'none';
  $handler->display->display_options['query']['type'] = 'views_query';
  $handler->display->display_options['exposed_form']['type'] = 'basic';
  $handler->display->display_options['pager']['type'] = 'none';
  $handler->display->display_options['style_plugin'] = 'table';
  /* Relationship: Content: Mapping used for GatherContent. */
  $handler->display->display_options['relationships']['gc_mapping_id']['id'] = 'gc_mapping_id';
  $handler->display->display_options['relationships']['gc_mapping_id']['table'] = 'node';
  $handler->display->display_options['relationships']['gc_mapping_id']['field'] = 'gc_mapping_id';
  $handler->display->display_options['relationships']['gc_mapping_id']['label'] = 'Default label for relationship';
  /* Field: Content: GC Status */
  $handler->display->display_options['fields']['gc_status']['id'] = 'gc_status';
  $handler->display->display_options['fields']['gc_status']['table'] = 'node';
  $handler->display->display_options['fields']['gc_status']['field'] = 'gc_status';
  $handler->display->display_options['fields']['gc_status']['label'] = 'Status';
  /* Field: Content: Title */
  $handler->display->display_options['fields']['title']['id'] = 'title';
  $handler->display->display_options['fields']['title']['table'] = 'node';
  $handler->display->display_options['fields']['title']['field'] = 'title';
  $handler->display->display_options['fields']['title']['label'] = 'Item name';
  $handler->display->display_options['fields']['title']['alter']['word_boundary'] = FALSE;
  $handler->display->display_options['fields']['title']['alter']['ellipsis'] = FALSE;
  $handler->display->display_options['fields']['title']['link_to_node'] = FALSE;
  /* Field: Content: GC Import status */
  $handler->display->display_options['fields']['gc_import_status']['id'] = 'gc_import_status';
  $handler->display->display_options['fields']['gc_import_status']['table'] = 'node';
  $handler->display->display_options['fields']['gc_import_status']['field'] = 'gc_import_status';
  $handler->display->display_options['fields']['gc_import_status']['label'] = 'Import status';
  $handler->display->display_options['fields']['gc_import_status']['empty'] = 'Import succesful';
  /* Field: Mapping: GC Template */
  $handler->display->display_options['fields']['gc_template']['id'] = 'gc_template';
  $handler->display->display_options['fields']['gc_template']['table'] = 'gc_mapping';
  $handler->display->display_options['fields']['gc_template']['field'] = 'gc_template';
  $handler->display->display_options['fields']['gc_template']['relationship'] = 'gc_mapping_id';
  $handler->display->display_options['fields']['gc_template']['label'] = 'Template name';
  /* Field: Content: GC Link */
  $handler->display->display_options['fields']['gc_link']['id'] = 'gc_link';
  $handler->display->display_options['fields']['gc_link']['table'] = 'node';
  $handler->display->display_options['fields']['gc_link']['field'] = 'gc_link';
  $handler->display->display_options['fields']['gc_link']['label'] = 'Open in GatherContent';
  /* Field: Content: Link */
  $handler->display->display_options['fields']['view_node']['id'] = 'view_node';
  $handler->display->display_options['fields']['view_node']['table'] = 'views_entity_node';
  $handler->display->display_options['fields']['view_node']['field'] = 'view_node';
  $handler->display->display_options['fields']['view_node']['label'] = 'Open in Drupal';
  $handler->display->display_options['fields']['view_node']['text'] = 'Open';
  /* Sort criterion: Content: Post date */
  $handler->display->display_options['sorts']['created']['id'] = 'created';
  $handler->display->display_options['sorts']['created']['table'] = 'node';
  $handler->display->display_options['sorts']['created']['field'] = 'created';
  $handler->display->display_options['sorts']['created']['order'] = 'DESC';
  /* Contextual filter: Content: Nid */
  $handler->display->display_options['arguments']['nid']['id'] = 'nid';
  $handler->display->display_options['arguments']['nid']['table'] = 'node';
  $handler->display->display_options['arguments']['nid']['field'] = 'nid';
  $handler->display->display_options['arguments']['nid']['default_action'] = 'not found';
  $handler->display->display_options['arguments']['nid']['exception']['value'] = '';
  $handler->display->display_options['arguments']['nid']['default_argument_type'] = 'fixed';
  $handler->display->display_options['arguments']['nid']['default_argument_skip_url'] = TRUE;
  $handler->display->display_options['arguments']['nid']['summary']['number_of_records'] = '0';
  $handler->display->display_options['arguments']['nid']['summary']['format'] = 'default_summary';
  $handler->display->display_options['arguments']['nid']['summary_options']['items_per_page'] = '25';
  $handler->display->display_options['arguments']['nid']['specify_validation'] = TRUE;
  $handler->display->display_options['arguments']['nid']['validate']['type'] = 'node';
  $handler->display->display_options['arguments']['nid']['validate_options']['nid_type'] = 'nids';
  $handler->display->display_options['arguments']['nid']['break_phrase'] = TRUE;
  /* Filter criterion: Content: Published */
  $handler->display->display_options['filters']['status']['id'] = 'status';
  $handler->display->display_options['filters']['status']['table'] = 'node';
  $handler->display->display_options['filters']['status']['field'] = 'status';
  $handler->display->display_options['filters']['status']['value'] = 1;
  $handler->display->display_options['filters']['status']['group'] = 1;
  $handler->display->display_options['filters']['status']['expose']['operator'] = FALSE;

  /* Display: Page */
  $handler = $view->new_display('page', 'Page', 'page');
  $handler->display->display_options['path'] = 'admin/config/gc/import/result/%';

  $views[$view->name] = $view;

  $view = new view();
  $view->name = 'mapping';
  $view->description = '';
  $view->tag = 'gc';
  $view->base_table = 'gc_mapping';
  $view->human_name = 'Mappings';
  $view->core = 7;
  $view->api_version = '3.0';
  $view->disabled = FALSE; /* Edit this to true to make a default view disabled initially */

  /* Display: Master */
  $handler = $view->new_display('default', 'Master', 'default');
  $handler->display->display_options['title'] = 'Mappings';
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
    'gc_updated' => 'gc_updated',
    'updated_drupal' => 'updated_drupal',
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
    'gc_updated' => array(
      'sortable' => 0,
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
    'edit_mapping' => array(
      'align' => '',
      'separator' => '&emsp;',
      'empty_column' => 0,
    ),
    'delete_mapping' => array(
      'align' => '',
      'separator' => '',
      'empty_column' => 0,
    ),
  );
  $handler->display->display_options['style_options']['empty_table'] = TRUE;
  /* No results behavior: Global: Text area */
  $handler->display->display_options['empty']['area']['id'] = 'area';
  $handler->display->display_options['empty']['area']['table'] = 'views';
  $handler->display->display_options['empty']['area']['field'] = 'area';
  $handler->display->display_options['empty']['area']['empty'] = TRUE;
  $handler->display->display_options['empty']['area']['content'] = 'No mappings were created.';
  $handler->display->display_options['empty']['area']['format'] = 'plain_text';
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
  $handler->display->display_options['fields']['content_type_name']['empty'] = 'None';
  /* Field: Mapping: Last updated in GatherContent */
  $handler->display->display_options['fields']['gc_updated']['id'] = 'gc_updated';
  $handler->display->display_options['fields']['gc_updated']['table'] = 'gc_mapping';
  $handler->display->display_options['fields']['gc_updated']['field'] = 'gc_updated';
  $handler->display->display_options['fields']['gc_updated']['date_format'] = 'custom';
  $handler->display->display_options['fields']['gc_updated']['custom_date_format'] = 'F d, Y - H:i';
  $handler->display->display_options['fields']['gc_updated']['second_date_format'] = 'long';
  /* Field: Mapping: Updated (Drupal) */
  $handler->display->display_options['fields']['updated_drupal']['id'] = 'updated_drupal';
  $handler->display->display_options['fields']['updated_drupal']['table'] = 'gc_mapping';
  $handler->display->display_options['fields']['updated_drupal']['field'] = 'updated_drupal';
  $handler->display->display_options['fields']['updated_drupal']['empty'] = 'Never';
  $handler->display->display_options['fields']['updated_drupal']['date_format'] = 'custom';
  $handler->display->display_options['fields']['updated_drupal']['custom_date_format'] = 'F d, Y - H:i';
  $handler->display->display_options['fields']['updated_drupal']['second_date_format'] = 'long';
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
  $handler->display->display_options['path'] = 'admin/config/gc/mapping';
  $handler->display->display_options['menu']['type'] = 'normal';
  $handler->display->display_options['menu']['title'] = 'Mappings';
  $handler->display->display_options['menu']['weight'] = '0';
  $handler->display->display_options['menu']['name'] = 'management';
  $handler->display->display_options['menu']['context'] = 0;
  $handler->display->display_options['menu']['context_only_inline'] = 0;
  $translatables['mapping'] = array(
    t('Master'),
    t('Mappings'),
    t('more'),
    t('Apply'),
    t('Reset'),
    t('Sort by'),
    t('Asc'),
    t('Desc'),
    t('Items per page'),
    t('- All -'),
    t('Offset'),
    t('« first'),
    t('‹ previous'),
    t('next ›'),
    t('last »'),
    t('No mappings were created.'),
    t('Project'),
    t('GatherContent Template'),
    t('Content type'),
    t('None'),
    t('Last updated in GatherContent'),
    t('Updated (Drupal)'),
    t('Never'),
    t('Operations'),
    t('Page'),
  );

  $views[$view->name] = $view;

  $view = new view();
  $view->name = 'update';
  $view->description = '';
  $view->tag = 'default';
  $view->base_table = 'node';
  $view->human_name = 'Update';
  $view->core = 7;
  $view->api_version = '3.0';
  $view->disabled = FALSE; /* Edit this to true to make a default view disabled initially */

  /* Display: Master */
  $handler = $view->new_display('default', 'Master', 'default');
  $handler->display->display_options['title'] = 'Update result';
  $handler->display->display_options['use_more_always'] = FALSE;
  $handler->display->display_options['access']['type'] = 'perm';
  $handler->display->display_options['cache']['type'] = 'none';
  $handler->display->display_options['query']['type'] = 'views_query';
  $handler->display->display_options['exposed_form']['type'] = 'basic';
  $handler->display->display_options['pager']['type'] = 'full';
  $handler->display->display_options['pager']['options']['items_per_page'] = '50';
  $handler->display->display_options['style_plugin'] = 'table';
  /* Relationship: Content: Mapping used for GatherContent. */
  $handler->display->display_options['relationships']['gc_mapping_id']['id'] = 'gc_mapping_id';
  $handler->display->display_options['relationships']['gc_mapping_id']['table'] = 'node';
  $handler->display->display_options['relationships']['gc_mapping_id']['field'] = 'gc_mapping_id';
  /* Field: Bulk operations: Content */
  $handler->display->display_options['fields']['views_bulk_operations']['id'] = 'views_bulk_operations';
  $handler->display->display_options['fields']['views_bulk_operations']['table'] = 'node';
  $handler->display->display_options['fields']['views_bulk_operations']['field'] = 'views_bulk_operations';
  $handler->display->display_options['fields']['views_bulk_operations']['vbo_settings']['display_type'] = '0';
  $handler->display->display_options['fields']['views_bulk_operations']['vbo_settings']['enable_select_all_pages'] = 1;
  $handler->display->display_options['fields']['views_bulk_operations']['vbo_settings']['row_clickable'] = 1;
  $handler->display->display_options['fields']['views_bulk_operations']['vbo_settings']['force_single'] = 0;
  $handler->display->display_options['fields']['views_bulk_operations']['vbo_settings']['entity_load_capacity'] = '10';
  $handler->display->display_options['fields']['views_bulk_operations']['vbo_operations'] = array(
    'action::gc_update' => array(
      'selected' => 1,
      'postpone_processing' => 0,
      'skip_confirmation' => 0,
      'override_label' => 0,
      'label' => '',
    ),
  );
  /* Field: Content: GC Status */
  $handler->display->display_options['fields']['gc_status']['id'] = 'gc_status';
  $handler->display->display_options['fields']['gc_status']['table'] = 'node';
  $handler->display->display_options['fields']['gc_status']['field'] = 'gc_status';
  $handler->display->display_options['fields']['gc_status']['label'] = 'Status';
  /* Field: Mapping: GC Project */
  $handler->display->display_options['fields']['gc_project']['id'] = 'gc_project';
  $handler->display->display_options['fields']['gc_project']['table'] = 'gc_mapping';
  $handler->display->display_options['fields']['gc_project']['field'] = 'gc_project';
  $handler->display->display_options['fields']['gc_project']['relationship'] = 'gc_mapping_id';
  $handler->display->display_options['fields']['gc_project']['label'] = 'GatherContent Project';
  /* Field: Content: Title */
  $handler->display->display_options['fields']['title']['id'] = 'title';
  $handler->display->display_options['fields']['title']['table'] = 'node';
  $handler->display->display_options['fields']['title']['field'] = 'title';
  $handler->display->display_options['fields']['title']['label'] = 'Drupal Item Name';
  $handler->display->display_options['fields']['title']['alter']['word_boundary'] = FALSE;
  $handler->display->display_options['fields']['title']['alter']['ellipsis'] = FALSE;
  $handler->display->display_options['fields']['title']['link_to_node'] = FALSE;
  /* Field: Content: GC Title */
  $handler->display->display_options['fields']['gc_title']['id'] = 'gc_title';
  $handler->display->display_options['fields']['gc_title']['table'] = 'node';
  $handler->display->display_options['fields']['gc_title']['field'] = 'gc_title';
  $handler->display->display_options['fields']['gc_title']['label'] = 'GatherContent Item name';
  /* Field: Content: Updated date */
  $handler->display->display_options['fields']['changed']['id'] = 'changed';
  $handler->display->display_options['fields']['changed']['table'] = 'node';
  $handler->display->display_options['fields']['changed']['field'] = 'changed';
  $handler->display->display_options['fields']['changed']['label'] = 'Last updated in Drupal';
  $handler->display->display_options['fields']['changed']['date_format'] = 'custom';
  $handler->display->display_options['fields']['changed']['custom_date_format'] = 'F d, Y - H:i';
  $handler->display->display_options['fields']['changed']['second_date_format'] = 'long';
  /* Field: Content: Last updated in GatherContent */
  $handler->display->display_options['fields']['gc_updated1']['id'] = 'gc_updated1';
  $handler->display->display_options['fields']['gc_updated1']['table'] = 'node';
  $handler->display->display_options['fields']['gc_updated1']['field'] = 'gc_updated1';
  $handler->display->display_options['fields']['gc_updated1']['date_format'] = 'custom';
  $handler->display->display_options['fields']['gc_updated1']['custom_date_format'] = 'F d, Y - H:i';
  $handler->display->display_options['fields']['gc_updated1']['second_date_format'] = 'long';
  /* Field: Mapping: Content type name */
  $handler->display->display_options['fields']['content_type_name']['id'] = 'content_type_name';
  $handler->display->display_options['fields']['content_type_name']['table'] = 'gc_mapping';
  $handler->display->display_options['fields']['content_type_name']['field'] = 'content_type_name';
  $handler->display->display_options['fields']['content_type_name']['relationship'] = 'gc_mapping_id';
  /* Field: Mapping: GC Template */
  $handler->display->display_options['fields']['gc_template']['id'] = 'gc_template';
  $handler->display->display_options['fields']['gc_template']['table'] = 'gc_mapping';
  $handler->display->display_options['fields']['gc_template']['field'] = 'gc_template';
  $handler->display->display_options['fields']['gc_template']['relationship'] = 'gc_mapping_id';
  $handler->display->display_options['fields']['gc_template']['label'] = 'GatherContent Template';
  /* Field: Content: Link */
  $handler->display->display_options['fields']['view_node']['id'] = 'view_node';
  $handler->display->display_options['fields']['view_node']['table'] = 'views_entity_node';
  $handler->display->display_options['fields']['view_node']['field'] = 'view_node';
  $handler->display->display_options['fields']['view_node']['label'] = 'Open in Drupal';
  $handler->display->display_options['fields']['view_node']['text'] = 'Open';
  /* Field: Content: GC Link */
  $handler->display->display_options['fields']['gc_link']['id'] = 'gc_link';
  $handler->display->display_options['fields']['gc_link']['table'] = 'node';
  $handler->display->display_options['fields']['gc_link']['field'] = 'gc_link';
  $handler->display->display_options['fields']['gc_link']['label'] = 'Open in GatherContent';
  /* Sort criterion: Content: Post date */
  $handler->display->display_options['sorts']['created']['id'] = 'created';
  $handler->display->display_options['sorts']['created']['table'] = 'node';
  $handler->display->display_options['sorts']['created']['field'] = 'created';
  $handler->display->display_options['sorts']['created']['order'] = 'DESC';
  /* Filter criterion: Content: Published */
  $handler->display->display_options['filters']['status']['id'] = 'status';
  $handler->display->display_options['filters']['status']['table'] = 'node';
  $handler->display->display_options['filters']['status']['field'] = 'status';
  $handler->display->display_options['filters']['status']['value'] = 1;
  $handler->display->display_options['filters']['status']['group'] = 1;
  $handler->display->display_options['filters']['status']['expose']['operator'] = FALSE;
  /* Filter criterion: Mapping: GC Project */
  $handler->display->display_options['filters']['gc_project']['id'] = 'gc_project';
  $handler->display->display_options['filters']['gc_project']['table'] = 'gc_mapping';
  $handler->display->display_options['filters']['gc_project']['field'] = 'gc_project';
  $handler->display->display_options['filters']['gc_project']['relationship'] = 'gc_mapping_id';
  $handler->display->display_options['filters']['gc_project']['exposed'] = TRUE;
  $handler->display->display_options['filters']['gc_project']['expose']['operator_id'] = 'gc_project_op';
  $handler->display->display_options['filters']['gc_project']['expose']['label'] = 'GatherContent Project';
  $handler->display->display_options['filters']['gc_project']['expose']['operator'] = 'gc_project_op';
  $handler->display->display_options['filters']['gc_project']['expose']['identifier'] = 'gc_project';
  $handler->display->display_options['filters']['gc_project']['expose']['remember_roles'] = array(
    2 => '2',
    1 => 0,
    3 => 0,
  );
  /* Filter criterion: Mapping: GC Template */
  $handler->display->display_options['filters']['gc_template']['id'] = 'gc_template';
  $handler->display->display_options['filters']['gc_template']['table'] = 'gc_mapping';
  $handler->display->display_options['filters']['gc_template']['field'] = 'gc_template';
  $handler->display->display_options['filters']['gc_template']['relationship'] = 'gc_mapping_id';
  $handler->display->display_options['filters']['gc_template']['exposed'] = TRUE;
  $handler->display->display_options['filters']['gc_template']['expose']['operator_id'] = 'gc_template_op';
  $handler->display->display_options['filters']['gc_template']['expose']['label'] = 'GatherContent Template';
  $handler->display->display_options['filters']['gc_template']['expose']['operator'] = 'gc_template_op';
  $handler->display->display_options['filters']['gc_template']['expose']['identifier'] = 'gc_template';
  $handler->display->display_options['filters']['gc_template']['expose']['remember_roles'] = array(
    2 => '2',
    1 => 0,
    3 => 0,
  );
  /* Filter criterion: Content: Title */
  $handler->display->display_options['filters']['title']['id'] = 'title';
  $handler->display->display_options['filters']['title']['table'] = 'node';
  $handler->display->display_options['filters']['title']['field'] = 'title';
  $handler->display->display_options['filters']['title']['operator'] = 'contains';
  $handler->display->display_options['filters']['title']['exposed'] = TRUE;
  $handler->display->display_options['filters']['title']['expose']['operator_id'] = 'title_op';
  $handler->display->display_options['filters']['title']['expose']['label'] = 'Filter by item name';
  $handler->display->display_options['filters']['title']['expose']['operator'] = 'title_op';
  $handler->display->display_options['filters']['title']['expose']['identifier'] = 'title';
  $handler->display->display_options['filters']['title']['expose']['remember_roles'] = array(
    2 => '2',
    1 => 0,
    3 => 0,
  );

  /* Display: Page */
  $handler = $view->new_display('page', 'Page', 'page');
  $handler->display->display_options['defaults']['title'] = FALSE;
  $handler->display->display_options['title'] = 'Update';
  $handler->display->display_options['path'] = 'admin/content/update';

  /* Display: Block */
  $handler = $view->new_display('block', 'Block', 'block_1');
  $handler->display->display_options['defaults']['title'] = FALSE;
  $handler->display->display_options['defaults']['fields'] = FALSE;
  /* Field: Content: GC Status */
  $handler->display->display_options['fields']['gc_status']['id'] = 'gc_status';
  $handler->display->display_options['fields']['gc_status']['table'] = 'node';
  $handler->display->display_options['fields']['gc_status']['field'] = 'gc_status';
  $handler->display->display_options['fields']['gc_status']['label'] = 'Status';
  /* Field: Mapping: GC Project */
  $handler->display->display_options['fields']['gc_project']['id'] = 'gc_project';
  $handler->display->display_options['fields']['gc_project']['table'] = 'gc_mapping';
  $handler->display->display_options['fields']['gc_project']['field'] = 'gc_project';
  $handler->display->display_options['fields']['gc_project']['relationship'] = 'gc_mapping_id';
  $handler->display->display_options['fields']['gc_project']['label'] = 'GatherContent Project';
  /* Field: Content: Title */
  $handler->display->display_options['fields']['title']['id'] = 'title';
  $handler->display->display_options['fields']['title']['table'] = 'node';
  $handler->display->display_options['fields']['title']['field'] = 'title';
  $handler->display->display_options['fields']['title']['label'] = 'Drupal Item Name';
  $handler->display->display_options['fields']['title']['alter']['word_boundary'] = FALSE;
  $handler->display->display_options['fields']['title']['alter']['ellipsis'] = FALSE;
  $handler->display->display_options['fields']['title']['link_to_node'] = FALSE;
  /* Field: Content: GC Title */
  $handler->display->display_options['fields']['gc_title']['id'] = 'gc_title';
  $handler->display->display_options['fields']['gc_title']['table'] = 'node';
  $handler->display->display_options['fields']['gc_title']['field'] = 'gc_title';
  $handler->display->display_options['fields']['gc_title']['label'] = 'GatherContent Item name';
  /* Field: Content: Updated date */
  $handler->display->display_options['fields']['changed']['id'] = 'changed';
  $handler->display->display_options['fields']['changed']['table'] = 'node';
  $handler->display->display_options['fields']['changed']['field'] = 'changed';
  $handler->display->display_options['fields']['changed']['label'] = 'Last updated in Drupal';
  $handler->display->display_options['fields']['changed']['date_format'] = 'long';
  $handler->display->display_options['fields']['changed']['second_date_format'] = 'long';
  /* Field: Content: Last updated in GatherContent */
  $handler->display->display_options['fields']['gc_updated1']['id'] = 'gc_updated1';
  $handler->display->display_options['fields']['gc_updated1']['table'] = 'node';
  $handler->display->display_options['fields']['gc_updated1']['field'] = 'gc_updated1';
  $handler->display->display_options['fields']['gc_updated1']['date_format'] = 'custom';
  $handler->display->display_options['fields']['gc_updated1']['custom_date_format'] = 'F d, Y - H:i';
  $handler->display->display_options['fields']['gc_updated1']['second_date_format'] = 'long';
  /* Field: Mapping: Content type name */
  $handler->display->display_options['fields']['content_type_name']['id'] = 'content_type_name';
  $handler->display->display_options['fields']['content_type_name']['table'] = 'gc_mapping';
  $handler->display->display_options['fields']['content_type_name']['field'] = 'content_type_name';
  $handler->display->display_options['fields']['content_type_name']['relationship'] = 'gc_mapping_id';
  /* Field: Mapping: GC Template */
  $handler->display->display_options['fields']['gc_template']['id'] = 'gc_template';
  $handler->display->display_options['fields']['gc_template']['table'] = 'gc_mapping';
  $handler->display->display_options['fields']['gc_template']['field'] = 'gc_template';
  $handler->display->display_options['fields']['gc_template']['relationship'] = 'gc_mapping_id';
  $handler->display->display_options['fields']['gc_template']['label'] = 'GatherContent Template';
  /* Field: Content: Link */
  $handler->display->display_options['fields']['view_node']['id'] = 'view_node';
  $handler->display->display_options['fields']['view_node']['table'] = 'views_entity_node';
  $handler->display->display_options['fields']['view_node']['field'] = 'view_node';
  $handler->display->display_options['fields']['view_node']['label'] = 'Open in Drupal';
  $handler->display->display_options['fields']['view_node']['text'] = 'Open';
  /* Field: Content: GC Link */
  $handler->display->display_options['fields']['gc_link']['id'] = 'gc_link';
  $handler->display->display_options['fields']['gc_link']['table'] = 'node';
  $handler->display->display_options['fields']['gc_link']['field'] = 'gc_link';
  $handler->display->display_options['fields']['gc_link']['label'] = 'Open in GatherContent';
  $handler->display->display_options['defaults']['arguments'] = FALSE;
  /* Contextual filter: Content: Nid */
  $handler->display->display_options['arguments']['nid']['id'] = 'nid';
  $handler->display->display_options['arguments']['nid']['table'] = 'node';
  $handler->display->display_options['arguments']['nid']['field'] = 'nid';
  $handler->display->display_options['arguments']['nid']['default_action'] = 'not found';
  $handler->display->display_options['arguments']['nid']['default_argument_type'] = 'fixed';
  $handler->display->display_options['arguments']['nid']['summary']['number_of_records'] = '0';
  $handler->display->display_options['arguments']['nid']['summary']['format'] = 'default_summary';
  $handler->display->display_options['arguments']['nid']['summary_options']['items_per_page'] = '25';
  $handler->display->display_options['arguments']['nid']['specify_validation'] = TRUE;
  $handler->display->display_options['arguments']['nid']['validate']['type'] = 'node';
  $handler->display->display_options['arguments']['nid']['validate_options']['nid_type'] = 'nids';
  $handler->display->display_options['arguments']['nid']['break_phrase'] = TRUE;
  $handler->display->display_options['defaults']['filter_groups'] = FALSE;
  $handler->display->display_options['defaults']['filters'] = FALSE;
  /* Filter criterion: Content: Published */
  $handler->display->display_options['filters']['status']['id'] = 'status';
  $handler->display->display_options['filters']['status']['table'] = 'node';
  $handler->display->display_options['filters']['status']['field'] = 'status';
  $handler->display->display_options['filters']['status']['value'] = 1;
  $handler->display->display_options['filters']['status']['group'] = 1;
  $handler->display->display_options['filters']['status']['expose']['operator'] = FALSE;

  /* Display: Page */
  $handler = $view->new_display('page', 'Page', 'page_1');
  $handler->display->display_options['defaults']['title'] = FALSE;
  $handler->display->display_options['title'] = 'Update result';
  $handler->display->display_options['defaults']['fields'] = FALSE;
  /* Field: Content: GC Status */
  $handler->display->display_options['fields']['gc_status']['id'] = 'gc_status';
  $handler->display->display_options['fields']['gc_status']['table'] = 'node';
  $handler->display->display_options['fields']['gc_status']['field'] = 'gc_status';
  $handler->display->display_options['fields']['gc_status']['label'] = 'Status';
  /* Field: Content: Title */
  $handler->display->display_options['fields']['title']['id'] = 'title';
  $handler->display->display_options['fields']['title']['table'] = 'node';
  $handler->display->display_options['fields']['title']['field'] = 'title';
  $handler->display->display_options['fields']['title']['label'] = 'Item Name';
  $handler->display->display_options['fields']['title']['alter']['word_boundary'] = FALSE;
  $handler->display->display_options['fields']['title']['alter']['ellipsis'] = FALSE;
  $handler->display->display_options['fields']['title']['link_to_node'] = FALSE;
  /* Field: Content: GC Import status */
  $handler->display->display_options['fields']['gc_import_status']['id'] = 'gc_import_status';
  $handler->display->display_options['fields']['gc_import_status']['table'] = 'node';
  $handler->display->display_options['fields']['gc_import_status']['field'] = 'gc_import_status';
  $handler->display->display_options['fields']['gc_import_status']['label'] = 'Update status';
  /* Field: Mapping: GC Template */
  $handler->display->display_options['fields']['gc_template']['id'] = 'gc_template';
  $handler->display->display_options['fields']['gc_template']['table'] = 'gc_mapping';
  $handler->display->display_options['fields']['gc_template']['field'] = 'gc_template';
  $handler->display->display_options['fields']['gc_template']['relationship'] = 'gc_mapping_id';
  $handler->display->display_options['fields']['gc_template']['label'] = 'GatherContent Template';
  /* Field: Content: Link */
  $handler->display->display_options['fields']['view_node']['id'] = 'view_node';
  $handler->display->display_options['fields']['view_node']['table'] = 'views_entity_node';
  $handler->display->display_options['fields']['view_node']['field'] = 'view_node';
  $handler->display->display_options['fields']['view_node']['label'] = 'Open in Drupal';
  $handler->display->display_options['fields']['view_node']['text'] = 'Open';
  /* Field: Content: GC Link */
  $handler->display->display_options['fields']['gc_link']['id'] = 'gc_link';
  $handler->display->display_options['fields']['gc_link']['table'] = 'node';
  $handler->display->display_options['fields']['gc_link']['field'] = 'gc_link';
  $handler->display->display_options['fields']['gc_link']['label'] = 'Open in GatherContent';
  $handler->display->display_options['defaults']['arguments'] = FALSE;
  /* Contextual filter: Content: Nid */
  $handler->display->display_options['arguments']['nid']['id'] = 'nid';
  $handler->display->display_options['arguments']['nid']['table'] = 'node';
  $handler->display->display_options['arguments']['nid']['field'] = 'nid';
  $handler->display->display_options['arguments']['nid']['default_action'] = 'not found';
  $handler->display->display_options['arguments']['nid']['default_argument_type'] = 'fixed';
  $handler->display->display_options['arguments']['nid']['summary']['number_of_records'] = '0';
  $handler->display->display_options['arguments']['nid']['summary']['format'] = 'default_summary';
  $handler->display->display_options['arguments']['nid']['summary_options']['items_per_page'] = '25';
  $handler->display->display_options['arguments']['nid']['specify_validation'] = TRUE;
  $handler->display->display_options['arguments']['nid']['validate']['type'] = 'node';
  $handler->display->display_options['arguments']['nid']['validate_options']['nid_type'] = 'nids';
  $handler->display->display_options['arguments']['nid']['break_phrase'] = TRUE;
  $handler->display->display_options['defaults']['filter_groups'] = FALSE;
  $handler->display->display_options['defaults']['filters'] = FALSE;
  /* Filter criterion: Content: Published */
  $handler->display->display_options['filters']['status']['id'] = 'status';
  $handler->display->display_options['filters']['status']['table'] = 'node';
  $handler->display->display_options['filters']['status']['field'] = 'status';
  $handler->display->display_options['filters']['status']['value'] = 1;
  $handler->display->display_options['filters']['status']['group'] = 1;
  $handler->display->display_options['filters']['status']['expose']['operator'] = FALSE;
  $handler->display->display_options['path'] = 'admin/content/update/result/%';
  $translatables['update'] = array(
    t('Master'),
    t('Update result'),
    t('more'),
    t('Apply'),
    t('Reset'),
    t('Sort by'),
    t('Asc'),
    t('Desc'),
    t('Items per page'),
    t('- All -'),
    t('Offset'),
    t('« first'),
    t('‹ previous'),
    t('next ›'),
    t('last »'),
    t('Mapping used for GatherContent.'),
    t('Content'),
    t('- Choose an operation -'),
    t('Status'),
    t('GatherContent Project'),
    t('Drupal Item Name'),
    t('GatherContent Item name'),
    t('Last updated in Drupal'),
    t('Last updated in GatherContent'),
    t('Content type name'),
    t('GatherContent Template'),
    t('Open in Drupal'),
    t('Open'),
    t('Open in GatherContent'),
    t('Filter by item name'),
    t('Page'),
    t('Update'),
    t('Block'),
    t('All'),
    t('Item Name'),
    t('Update status'),
  );

  $views[$view->name] = $view;

  return $views;
}
