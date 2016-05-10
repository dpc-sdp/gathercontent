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
class views_handler_field_node_gc_link extends views_handler_field_node_link {

  /**
   * Renders the link.
   */
  function render_link($entity, $values) {
    if (isset($this->view->field['gc_status']->contents[$entity->gc_id]['name'])) {
      $this->options['alter']['make_link'] = TRUE;
      $this->options['alter']['path'] = 'http://' . variable_get('gc_urlkey') . '.gathercontent.com/item/' . $entity->gc_id;

      $text = !empty($this->options['text']) ? $this->options['text'] : t('Open');
    }
    else {
      $text = t('deleted in GatherContent');
    }
    return $text;
  }

}
