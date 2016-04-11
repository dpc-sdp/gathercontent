<?php

/**
 * Implements hook_views_data().
 */
function gc_views_data() {
  $data['gc_mapping']['edit_mapping'] = array(
    'field' => array(
      'title' => t('Edit link'),
      'help' => t('Provide a simple link to edit the mapping.'),
      'handler' => 'views_handler_field_mapping_link_edit',
      'click sortable' => FALSE,
    ),
  );

  $data['gc_mapping']['delete_mapping'] = array(
    'field' => array(
      'title' => t('Delete link'),
      'help' => t('Provide a simple link to delete the mapping.'),
      'handler' => 'views_handler_field_mapping_link_delete',
      'click sortable' => FALSE,
    ),
  );
  return $data;
}

/**
 * Implements hook_views_data_alter().
 */
function gc_views_data_alter(&$data) {
  $data['node']['gc_id'] = array(
    'title' => t('GC ID'),
    'help' => t('The ID of content in GatherContent'),
    'field' => array(
      'handler' => 'views_handler_field_numeric',
    ),
  );
  $data['node']['gc_mapping_id'] = array(
    'title' => t('GC Mapping ID'),
    'help' => t('The ID of GatherContent mapping'),
    'field' => array(
      'handler' => 'views_handler_field_numeric',
    ),
    'relationship' => array(
      'base' => 'gc_mapping',
      'base field' => 'id',
      'field' => 'gc_mapping_id',
      'handler' => 'views_handler_relationship',
      'label' => t('Mapping used for GatherContent.'),
      'title' => t('Mapping used for GatherContent.'),
      'help' => t('Mapping used for GatherContent.'),
    ),
  );
  $data['node']['gc_import_status'] = array(
    'title' => t('GC Import status'),
    'help' => t('Status of import from GatherContent'),
    'field' => array(
      'handler' => 'views_handler_field',
    ),
  );
  $data['node']['gc_status'] = array(
    'title' => t('GC Status'),
    'help' => t('Status of content in GatherContent'),
    'field' => array(
      'handler' => 'views_handler_field_node_gc_status',
    ),
  );
  $data['node']['gc_title'] = array(
    'title' => t('GC Title'),
    'help' => t('Title of content in GatherContent'),
    'field' => array(
      'handler' => 'views_handler_field_node_gc_title',
    ),
  );
  $data['node']['gc_link'] = array(
    'title' => t('GC Link'),
    'help' => t('Link to content in GatherContent'),
    'field' => array(
      'handler' => 'views_handler_field_node_gc_link',
      'click sortable' => FALSE,
    ),
  );
  $data['node']['gc_updated1'] = array(
    'title' => t('Last updated in GatherContent'),
    'help' => t('Last updated in GatherContent'),
    'field' => array(
      'handler' => 'views_handler_field_node_gc_updated',
      'click sortable' => TRUE,
    ),
  );
  $data['gc_mapping']['gc_updated'] = array(
    'title' => t('Last updated in GatherContent'),
    'help' => t('Last updated in GatherContent'),
    'field' => array(
      'handler' => 'views_handler_field_mapping_gc_updated',
      'click sortable' => TRUE,
    ),
  );
}
