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
