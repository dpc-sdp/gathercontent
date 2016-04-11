<?php

/**
 * Field handler to present a link to delete a mytype record.
 */
class views_handler_field_node_gc_status extends views_handler_field {

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
    return '<div style="width:20px; height: 20px; float: left; margin-right: 5px; background: ' .
    $this->contents[$gc_id]['status']->data->color . ';"></div>' .
    $this->contents[$gc_id]['status']->data->name;
  }

}
