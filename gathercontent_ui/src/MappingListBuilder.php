<?php

namespace Drupal\gathercontent_ui;

use Cheppers\GatherContent\GatherContentClientInterface;
use Drupal\Core\Config\Entity\ConfigEntityListBuilder;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\gathercontent\DrupalGatherContentClient;
use Symfony\Component\HttpFoundation\Request;

/**
 * Provides a listing of GatherContent Mapping entities.
 */
class MappingListBuilder extends ConfigEntityListBuilder {

  use StringTranslationTrait;

  /**
   * Templates array.
   *
   * @var array
   */
  protected $templates;

  /**
   * Client for querying the GatherContent API.
   *
   * @var \Drupal\gathercontent\DrupalGatherContentClient
   */
  protected $client;

  /**
   * Entity query service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Request service.
   *
   * @var \Symfony\Component\HttpFoundation\Request
   */
  protected $request;

  /**
   * {@inheritdoc}
   */
  public function __construct(
    EntityTypeInterface $entity_type,
    EntityStorageInterface $storage,
    GatherContentClientInterface $client,
    EntityTypeManagerInterface $entityTypeManager,
    Request $request
  ) {
    parent::__construct($entity_type, $storage);
    $this->client = $client;
    $this->entityTypeManager = $entityTypeManager;
    $this->request = $request;
  }

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type) {
    return new static(
      $entity_type,
      $container->get('entity_type.manager')->getStorage($entity_type->id()),
      $container->get('gathercontent.client'),
      $container->get('entity_type.manager'),
      $container->get('request_stack')->getCurrentRequest()
    );
  }

  /**
   * {@inheritdoc}
   */
  public function load() {
    $entity_query = $this->entityTypeManager->getStorage('gathercontent_mapping')
      ->getQuery();
    $query_string = $this->request->query;
    $headers = $this->buildHeader();

    $entity_query->pager(100);
    if ($query_string->has('order')) {
      foreach ($headers as $header) {
        if (is_array($header) && $header['data'] === $query_string->get('order')) {
          $sort = 'ASC';
          if ($query_string->has('sort') && $query_string->get('sort') === 'asc' || $query_string->get('sort') === 'desc') {
            $sort = mb_strtoupper($query_string->get('sort'));
          }
          $entity_query->sort($header['field'], $sort);
        }
      }
    }
    $entity_query->tableSort($headers);
    $entity_ids = $entity_query->execute();

    return $this->storage->loadMultiple($entity_ids);
  }

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    return [
      'gathercontent_project' => [
        'data' => $this->t('GatherContent Project'),
        'field' => 'gathercontent_project',
        'specifier' => 'gathercontent_project',
      ],
      'gathercontent_template' => [
        'data' => $this->t('GatherContent Template'),
        'field' => 'gathercontent_template',
        'specifier' => 'gathercontent_template',
      ],
      'entity_type' => [
        'data' => $this->t('Entity type'),
        'field' => 'entity_type',
        'specifier' => 'entity_type',
      ],
      'content_type_name' => [
        'data' => $this->t('Bundle'),
        'field' => 'content_type_name',
        'specifier' => 'content_type_name',
      ],
      'updated_gathercontent' => [
        'data' => $this->t('Last updated in GatherContent'),
      ],
      'updated_drupal' => [
        'data' => $this->t('Updated in Drupal'),
        'field' => 'updated_drupal',
        'specifier' => 'updated_drupal',
      ],
    ] + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    /** @var \Drupal\gathercontent\Entity\Mapping $entity */
    $exists = isset($this->templates[$entity->getGathercontentTemplateId()]);
    $row['project'] = $entity->getGathercontentProject();
    $row['gathercontent_template'] = $entity->getGathercontentTemplate();
    $row['entity_type'] = $entity->getFormattedEntityType();
    $row['content_type'] = $entity->getFormattedContentType();
    $row['updated_gathercontent'] = ($exists ? \Drupal::service('date.formatter')
      ->format($this->templates[$entity->getGathercontentTemplateId()], 'custom', 'M d, Y - H:i') : $this->t("Deleted"));
    $row['updated_drupal'] = $entity->getFormatterUpdatedDrupal();
    $row = $row + parent::buildRow($entity);
    return $row;
  }

  /**
   * {@inheritdoc}
   */
  public function render() {
    $account_id = DrupalGatherContentClient::getAccountId();
    if (!$account_id) {
      return parent::render();
    }

    $entityStorage = $this->entityTypeManager->getStorage('gathercontent_mapping');
    $projects = $this->client->getActiveProjects($account_id);

    foreach ($projects['data'] as $project) {
      $mappings = $entityStorage->loadByProperties([
        'gathercontent_project_id' => $project->id,
      ]);

      if (!$mappings) {
        continue;
      }

      $remote_templates = $this->client->templatesGet($project->id);
      foreach ($remote_templates['data'] as $remote_template) {
        $this->templates[$remote_template->id] = strtotime($remote_template->updatedAt);
      }
    }

    return parent::render();
  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultOperations(EntityInterface $entity) {
    $exists = isset($this->templates[$entity->getGathercontentTemplateId()]);
    $operations = [];
    if ($exists && $entity->access('update') && $entity->hasLinkTemplate('edit-form')) {
      $operations['edit'] = [
        'title' => $entity->hasMapping() ? $this->t('Edit') : $this->t('Create'),
        'weight' => 10,
        'url' => $entity->toUrl('edit-form'),
      ];
    }
    if ($entity->access('delete') && $entity->hasLinkTemplate('delete-form')) {
      $operations['delete'] = [
        'title' => $this->t('Delete'),
        'weight' => 100,
        'url' => $entity->toUrl('delete-form'),
      ];
    }
    return $operations;
  }

}
