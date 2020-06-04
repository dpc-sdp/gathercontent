<?php

namespace Drupal\gathercontent_upload\Event;

use Drupal\Core\Entity\EntityInterface;
use Symfony\Component\EventDispatcher\Event;

/**
 * Wraps a post node upload event for event listeners.
 */
class PostNodeUploadEvent extends Event {

  /**
   * Node object.
   *
   * @var \Drupal\node\NodeInterface
   */
  protected $node;

  /**
   * Source fields.
   *
   * @var array
   */
  protected $gathercontentValues;

  /**
   * Constructs a post node upload event object.
   *
   * @param \Drupal\Core\Entity\EntityInterface $node
   *   Entity object.
   * @param array $gathercontentValues
   *   Source fields representing object in GatherContent.
   */
  public function __construct(EntityInterface $node, array $gathercontentValues) {
    $this->node = $node;
    $this->gathercontentValues = $gathercontentValues;
  }

  /**
   * Gets the node object.
   *
   * @return \Drupal\node\NodeInterface
   *   The node object.
   */
  public function getNode() {
    return $this->node;
  }

  /**
   * Gets the array of source fields.
   *
   * @return array
   *   Source fields.
   */
  public function getGathercontentValues() {
    return $this->gathercontentValues;
  }

}
