<?php

namespace Drupal\gathercontent;

/**
 * A class for storing and serializing the import/update options of a node.
 */
class ImportOptions {

  /**
   * Decides how to import the node.
   *
   * @var string
   *
   * @see \Drupal\gathercontent\NodeUpdateMethod
   */
  public $nodeUpdateMethod = NodeUpdateMethod::ALWAYS_UPDATE;

  /**
   * Decides whether to publish the imported node.
   *
   * @var bool
   */
  public $publish = FALSE;

  /**
   * ID of a GatherContent status.
   *
   * If set, status of the imported node will be updated both in GatherContent and Drupal.
   *
   * @var int
   */
  public $newStatus = NULL;

  /**
   * ID of a Drupal menu item.
   *
   * If set, imported node will be a menu item.
   *
   * @var string
   */
  public $parentMenuItem = NULL;

  /**
   * Getter $nodeUpdateMethod.
   */
  public function getNodeUpdateMethod() {
    return $this->nodeUpdateMethod;
  }

  /**
   * Setter $nodeUpdateMethod.
   */
  public function setNodeUpdateMethod($nodeUpdateMethod) {
    $this->nodeUpdateMethod = $nodeUpdateMethod;
    return $this;
  }

  /**
   * Getter $publish.
   */
  public function getPublish() {
    return $this->publish;
  }

  /**
   * Setter $publish.
   */
  public function setPublish($publish) {
    $this->publish = $publish;
    return $this;
  }

  /**
   * Getter $newStatus.
   */
  public function getNewStatus() {
    return $this->newStatus;
  }

  /**
   * Setter $newStatus.
   */
  public function setNewStatus($new_status) {
    $this->newStatus = $new_status;
    return $this;
  }

  /**
   * Getter $parentMenuItem.
   */
  public function getParentMenuItem() {
    return $this->parentMenuItem;
  }

  /**
   * Setter $parentMenuItem.
   */
  public function setParentMenuItem($parent_menu_item) {
    $this->parentMenuItem = $parent_menu_item;
    return $this;
  }

}
