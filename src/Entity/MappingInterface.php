<?php

namespace Drupal\gathercontent\Entity;

use Drupal\Core\Config\Entity\ConfigEntityInterface;

/**
 * Provides an interface for defining GatherContent Mapping entities.
 */
interface MappingInterface extends ConfigEntityInterface {

  /**
   * Getter for GatherContent project ID property.
   *
   * @return int
   *   GatherContent project ID.
   */
  public function getGathercontentProjectId();

  /**
   * Getter for GatherContent project property.
   *
   * @return string
   *   GatherContent project name.
   */
  public function getGathercontentProject();

  /**
   * Getter for GatherContent template ID property.
   *
   * @return int
   *   GatherContent template ID.
   */
  public function getGathercontentTemplateId();

  /**
   * Getter for GatherContent template property.
   *
   * @return string
   *   GatherContent template name.
   */
  public function getGathercontentTemplate();

  /**
   * Getter for content type machine name.
   *
   * @return string
   *   Content type machine name.
   */
  public function getContentType();

  /**
   * Getter for content type human name.
   *
   * @return string
   *   Content type human name.
   */
  public function getContentTypeName();

  /**
   * Getter for GatherContent template serialized object.
   *
   * @return string
   *   Serialized GatherContent template.
   */
  public function getTemplate();

  /**
   * Getter for mapping data.
   *
   * @return string
   *   Serialized object of mapping.
   */
  public function getData();

}
