<?php

/**
 * @file
 * Definition of views_handler_field_node_link_edit.
 */

/**
 * Field handler to present a link node edit.
 *
 * @ingroup views_field_handlers
 */
class views_handler_field_gc_operation_item_gc_link extends views_handler_field_node_link {

  /**
   * Renders the link.
   */
  function render_link($entity, $values) {
    $this->options['alter']['make_link'] = TRUE;
    // @FIXME
// Could not extract the default value because it is either indeterminate, or
// not scalar. You'll need to provide a default value in
// config/install/gc.settings.yml and config/schema/gc.schema.yml.
$this->options['alter']['path'] = 'http://' . \Drupal::config('gc.settings')->get('gc_urlkey') . '.gathercontent.com/item/' . $entity->gc_id;

    $text = !empty($this->options['text']) ? $this->options['text'] : t('Open');
    return $text;
  }

}
