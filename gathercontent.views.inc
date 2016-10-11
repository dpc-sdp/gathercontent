<?php

/**
 * @file
 * Contains gathercontent\gathercontent.views.inc..
 *
 * Provide a custom views field data that isn't tied to any other module. */

/**
 * Implements hook_views_data().
 */
function gathercontent_views_data() {
  // @TODO: rewrite
  $data['views']['table']['group'] = t('Custom Global');
  $data['views']['table']['join'] = array(
    // #global is a special flag which allows a table to appear all the time.
    '#global' => array(),
  );

  $data['views']['gathercontent_content_link'] = array(
    'title' => t('Gathercontent content link'),
    'help' => t(''),
    'field' => array(
      'id' => 'gathercontent_content_link',
    ),
  );

  $data['views']['gathercontent_status_color_field'] = array(
    'title' => t('Gathercontent status color'),
    'help' => t(''),
    'field' => array(
      'id' => 'gathercontent_status_color_field',
    ),
  );

  $data['views']['content_link'] = array(
    'title' => t('Content link'),
    'help' => t(''),
    'field' => array(
      'id' => 'content_link',
    ),
  );

  return $data;
}
