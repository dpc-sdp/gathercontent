<?php

namespace Drupal\gathercontent_upload\Export;

use Drupal\Core\Database\Connection;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\migrate\Plugin\MigrationPluginManagerInterface;
use Drupal\migrate\Row;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class for handling import/update logic from GatherContent to Drupal.
 */
class MigrateUpdater implements ContainerInjectionInterface {

  /**
   * Migration service.
   *
   * @var \Drupal\migrate\Plugin\MigrationPluginManagerInterface
   */
  protected $migrationService;

  /**
   * Database.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * DI GatherContent Client.
   */
  public function __construct(
    MigrationPluginManagerInterface $migrationService,
    Connection $database
  ) {
    $this->migrationService = $migrationService;
    $this->database = $database;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('plugin.manager.migration'),
      $container->get('database')
    );
  }

  /**
   * Update/create Migrate API's ID Mapping.
   *
   * @param array $context
   *   Batch context.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  public function updateIdMap(array $context = []) {
    if (empty($context['results']['mappings'])) {
      return;
    }

    foreach ($context['results']['mappings'] as $mapping) {
      $this->processMappings($mapping);
    }
  }

  protected function processMappings(array $mapping) {
    $migrationIds = $mapping['mapping']->getMigrations();

    foreach ($migrationIds as $migrationId) {
      $this->processMigration($migrationId, $mapping['gcIds']);
    }
  }

  protected function processMigration(string $migrationId, array $gcIds) {
    /** @var \Drupal\migrate\Plugin\MigrationInterface $migration */
    $migration = $this->migrationService->createInstance($migrationId);
    $source = $migration->getSourcePlugin();
    $source->rewind();

    while ($source->valid()) {
      $row = $source->current();
      $sourceId = $row->getSourceIdValues();
      $sourceId = $sourceId['id'];

      if (empty($gcIds[$sourceId])) {
        $source->next();
        continue;
      }

      $this->processEntity($migration, $gcIds[$sourceId], $row);

      $source->next();
    }
  }

  protected function processEntity(MigrationInterface $migration, array $entities, Row $row) {
    $destinationConfiguration = $migration->getDestinationConfiguration();
    $plugin = explode(':', $destinationConfiguration['plugin']);
    $idMap = $migration->getIdMap();

    /** @var \Drupal\Core\Entity\EntityInterface $entity */
    foreach ($entities as $entity) {
      if ($plugin[1] !== $entity->getEntityTypeId()) {
        continue;
      }

      $destinationIds = [$entity->id()];
      if ($entity->getEntityTypeId() === 'paragraph') {
        $destinationIds[] = $entity->getRevisionId();
      }
      $idMap->saveIdMapping($row, $destinationIds);

      $this->processLanguages($migration, $entity, $row, $destinationIds);
    }
  }

  protected function processLanguages(MigrationInterface $migration, EntityInterface $entity, Row $row, array $destinationIds) {
    $idMap = $migration->getIdMap();
    $languages = $entity->getTranslationLanguages();
    $sourceId = $row->getSourceIdValues();
    $sourceId = $sourceId['id'];

    foreach ($languages as $language) {
      $result = $this->database->select('gathercontent_entity_mapping')
        ->fields('gathercontent_entity_mapping', [
          'entity_id',
          'entity_type',
        ])
        ->condition('entity_id', $entity->id())
        ->condition('entity_type', $entity->getEntityTypeId())
        ->condition('langcode', $language->getId())
        ->execute()
        ->fetchAll();

      if (empty($result)) {
        $this->database->insert('gathercontent_entity_mapping')
          ->fields([
            'entity_id' => $entity->id(),
            'entity_type' => $entity->getEntityTypeId(),
            'gc_id' => $sourceId,
            'migration_id' => $migration->id(),
            'langcode' => $language->getId(),
          ])
          ->execute();
      }

      if ($language->isDefault()) {
        continue;
      }

      $destinationIds[] = $language->getId();
      $idMap->saveIdMapping($row, $destinationIds);
    }
  }

}
