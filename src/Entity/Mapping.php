<?php

namespace Drupal\gathercontent\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBase;

/**
 * Defines the GatherContent Mapping entity.
 *
 * @ConfigEntityType(
 *   id = "gathercontent_mapping",
 *   label = @Translation("GatherContent Mapping"),
 *   handlers = {
 *     "list_builder" = "Drupal\gathercontent\MappingListBuilder",
 *     "form" = {
 *       "default" = "Drupal\gathercontent\Form\MappingImportForm",
 *       "add" = "Drupal\gathercontent\Form\MappingImportForm",
 *       "edit" = "Drupal\gathercontent\Form\MappingEditForm",
 *       "delete" = "Drupal\gathercontent\Form\MappingDeleteForm"
 *     },
 *     "route_provider" = {
 *       "html" = "Drupal\gathercontent\MappingHtmlRouteProvider",
 *     },
 *   },
 *   config_prefix = "gathercontent_mapping",
 *   admin_permission = "administer site configuration",
 *   entity_keys = {
 *     "id" = "id",
 *     "uuid" = "uuid"
 *   },
 *   links = {
 *     "canonical" = "/admin/config/gathercontent/mapping/{gathercontent_mapping}",
 *     "add-form" = "/admin/config/gathercontent/mapping/create",
 *     "edit-form" = "/admin/config/gathercontent/mapping/{gathercontent_mapping}/edit",
 *     "delete-form" = "/admin/config/gathercontent/mapping/{gathercontent_mapping}/delete",
 *     "collection" = "/admin/config/gathercontent/mapping"
 *   }
 * )
 */
class Mapping extends ConfigEntityBase implements MappingInterface {

  /**
   * The GatherContent Mapping ID.
   *
   * @var string
   */
  protected $id;

  /**
   * The GatherContent Project ID.
   *
   * @var integer
   */
  protected $gathercontent_project_id;

  /**
   * The GatherContent Project name.
   *
   * @var string
   */
  protected $gathercontent_project;

  /**
   * The GatherContent Template ID.
   *
   * @var integer
   */
  protected $gathercontent_template_id;

  /**
   * The GatherContent Template name.
   *
   * @var string
   */
  protected $gathercontent_template;

  /**
   * Content type machine name.
   *
   * @var string
   */
  protected $content_type;

  /**
   * Content type name.
   *
   * @var string
   */
  protected $content_type_name;

  /**
   * Timestamp of mapping update in Drupal.
   *
   * @var string
   */
  protected $updated_drupal;

  /**
   * Mapping data.
   *
   * @var string
   */
  protected $data;

  /**
   * Template during latest update.
   *
   * @var string
   */
  protected $template;

  /**
   * @param $projectID
   * @param $templateID
   *
   * @return FALSE|\Drupal\gathercontent\Entity\Mapping
   */
  public static function exists($projectID, $templateID) {
    $mappings = self::loadMultiple();
    foreach ($mappings as $mapping) {
      /** @var Mapping $mapping */
      if ($mapping->getGathercontentTemplateId() === $templateID && $mapping->getGathercontentProjectId() === $projectID) {
        return $mapping;
      }
    }
    return FALSE;
  }

  /**
   * @return int
   */
  public function getGathercontentTemplateId() {
    return $this->get('gathercontent_template_id');
  }

  /**
   * @param int $gathercontent_template_id
   */
  public function setGathercontentTemplateId($gathercontent_template_id) {
    $this->gathercontent_template_id = $gathercontent_template_id;
  }

  /**
   * @return int
   */
  public function getGathercontentProjectId() {
    return $this->get('gathercontent_project_id');
  }

  /**
   * @param int $gathercontent_project_id
   */
  public function setGathercontentProjectId($gathercontent_project_id) {
    $this->gathercontent_project_id = $gathercontent_project_id;
  }

  /**
   * @return string
   */
  public function getGathercontentProject() {
    return $this->get('gathercontent_project');
  }

  /**
   * @param string $gathercontent_project
   */
  public function setGathercontentProject($gathercontent_project) {
    $this->gathercontent_project = $gathercontent_project;
  }

  /**
   * @return string
   */
  public function getGathercontentTemplate() {
    return $this->get('gathercontent_template');
  }

  /**
   * @param string $gathercontent_template
   */
  public function setGathercontentTemplate($gathercontent_template) {
    $this->gathercontent_template = $gathercontent_template;
  }

  /**
   * @return string
   */
  public function getContentType() {
    return $this->get('content_type');
  }

  /**
   * @param string $content_type
   */
  public function setContentType($content_type) {
    $this->content_type = $content_type;
  }

  /**
   * @return string
   */
  public function getContentTypeName() {
    return $this->get('content_type_name');
  }

  /**
   * @param string $content_type_name
   */
  public function setContentTypeName($content_type_name) {
    $this->content_type_name = $content_type_name;
  }

  /**
   * @return string
   */
  public function getUpdatedDrupal() {
    return $this->get('updated_drupal');
  }

  /**
   * @param string $updated_drupal
   */
  public function setUpdatedDrupal($updated_drupal) {
    $this->updated_drupal = $updated_drupal;
  }

  /**
   * @return string
   */
  public function getFormattedContentType() {
    $content_type = $this->get('content_type_name');
    if (!empty($content_type)) {
      return $content_type;
    }
    else {
      return t('None');
    }
  }

  /**
   * @return string
   */
  public function getFormatterUpdatedDrupal() {
    $updated_drupal = $this->get('updated_drupal');
    if (!empty($updated_drupal)) {
      return \Drupal::service('date.formatter')
        ->format($updated_drupal, 'custom', 'M d, Y - H:i');
    }
    else {
      return t('Never');
    }
  }

  /**
   * @return mixed
   */
  public function getTemplate() {
    return $this->get('template');
  }

  /**
   * @param string $template
   */
  public function setTemplate($template) {
    $this->template = $template;
  }

  /**
   * @return string
   */
  public function getData() {
    return $this->get('data');
  }

  /**
   * @param string $data
   */
  public function setData($data) {
    $this->data = $data;
  }

  /**
   * @return bool
   */
  public function hasMapping() {
    return !empty($this->get('data'));
  }
}