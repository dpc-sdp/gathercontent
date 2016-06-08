<?php

namespace Drupal\gathercontent;

use Drupal\Core\Config\Entity\ConfigEntityListBuilder;
use Drupal\Core\Entity\EntityInterface;
use Drupal\gathercontent\DAO\Project;
use Drupal\gathercontent\DAO\Template;
use Drupal\gathercontent\Entity\GathercontentMapping;

/**
 * Provides a listing of GatherContent Mapping entities.
 */
class GathercontentMappingListBuilder extends ConfigEntityListBuilder {

  protected $templates;

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header['project'] = $this->t('GatherContent Project');
    $header['gathercontent_template'] = $this->t('GatherContent Template');
    $header['content_type'] = $this->t('Content type');
    $header['updated_gathercontent'] = $this->t('Last updated in GatherContent');
    $header['updated_drupal'] = $this->t('Updated in Drupal');
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    /** @var $entity GathercontentMapping */
    $exists = isset($this->templates[$entity->getGathercontentTemplateId()]);
    $row['project'] = $entity->getGathercontentProject();
    $row['gathercontent_template'] = $entity->getGathercontentTemplate();
    $row['content_type'] = $entity->getFormattedContentType();
    $row['updated_gathercontent'] = ($exists ? \Drupal::service('date.formatter')
      ->format($this->templates[$entity->getGathercontentTemplateId()], 'custom', 'M d, Y - H:i') : t("Deleted"));
    $row['updated_drupal'] = $entity->getFormatterUpdatedDrupal();
    if ($exists) {
      $row = $row + parent::buildRow($entity);
    }
    return $row;
  }

  /**
   * @inheritdoc
   */
  public function render() {
    $project_obj = new Project();
    $projects = $project_obj->getProjectObjects();
    $temp_obj = new Template();
    foreach ($projects as $project) {
      $remote_templates = $temp_obj->getTemplatesObject($project->id);
      foreach ($remote_templates as $remote_template) {
        $this->templates[$remote_template->id] = $remote_template->updated_at;
      }
    }
    return parent::render();
  }

  public function getDefaultOperations(EntityInterface $entity) {
    if ($entity->access('update') && $entity->hasLinkTemplate('edit-form')) {
      $operations['edit'] = array(
        'title' => $entity->hasMapping() ? $this->t('Edit') : $this->t('Setup'),
        'weight' => 10,
        'url' => $entity->urlInfo('edit-form'),
      );
    }
    if ($entity->access('delete') && $entity->hasLinkTemplate('delete-form')) {
      $operations['delete'] = array(
        'title' => $this->t('Delete'),
        'weight' => 100,
        'url' => $entity->urlInfo('delete-form'),
      );
    }
    return $operations;
  }

}
