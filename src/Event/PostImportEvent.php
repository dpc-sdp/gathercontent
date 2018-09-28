<?php

namespace Drupal\gathercontent\Event;

use Symfony\Component\EventDispatcher\Event;

/**
 * Wraps a post import event for event listeners.
 */
class PostImportEvent extends Event {

  /**
   * Array of arrays with successfully imported nids and their gc_ids.
   *
   * @var array
   */
  protected $successNodes;

  /**
   * Array of arrays with unsuccessfully imported nids and their gc_ids.
   *
   * @var array
   */
  protected $unsuccessNodes;

  /**
   * Constructs a post import event object.
   *
   * @param array $success
   *   Array of arrays with successfully imported nids and their gc_ids.
   * @param array $unsuccess
   *   Array of arrays with unsuccessfully imported nids and their gc_ids.
   */
  public function __construct(array $success, array $unsuccess) {
    $this->successNodes = $success;
    $this->unsuccessNodes = $unsuccess;
  }

  /**
   * Get array of arrays with successfully imported nodes.
   *
   * @return array
   *   Array of arrays with successfully imported nids and their gc_ids.
   */
  public function getSuccessNodes() {
    return $this->successNodes;
  }

  /**
   * Get array of arrays with unsuccessfully imported nodes.
   *
   * @return array
   *   Array of arrays with unsuccessfully imported nids and their gc_ids.
   */
  public function getUnsuccessNodes() {
    return $this->unsuccessNodes;
  }

}
