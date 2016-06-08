<?php

namespace Drupal\gathercontent;

use Drupal\Core\Config\Entity\ConfigEntityInterface;

/**
 * Provides an interface for defining GatherContent Mapping entities.
 */
interface GathercontentMappingInterface extends ConfigEntityInterface {

  public function getGathercontentProjectId();

  public function getGathercontentProject();

  public function getGathercontentTemplateId();

  public function getGathercontentTemplate();

  public function getContentType();

  public function getContentTypeName();

  public function getUpdatedDrupal();

  public function getTemplate();

  public function getData();
}
