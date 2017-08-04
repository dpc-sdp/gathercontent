<?php

namespace Drupal\gathercontent;

/**
 * Constants specifying how to import/update nodes.
 */
class NodeUpdateMethod {
  const ALWAYS_CREATE = 'always_create';
  const UPDATE_IF_NOT_CHANGED = 'update_if_not_changed';
  const ALWAYS_UPDATE = 'always_update';
}