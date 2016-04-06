<?php

/**
 * Field handler to present a link to delete a mytype record.
 */
class views_handler_field_node_gc_title extends views_handler_field {

  function construct() {
    parent::construct();
    $this->additional_fields['gc_id'] = 'gc_id';
  }

  function query() {
    $this->ensure_my_table();
    $this->add_additional_fields();
  }

  function render($values) {
    $gc_id = $values->{$this->aliases['gc_id']};
    return $this->view->field['gc_status']->contents[$gc_id]['name'];
  }

}