<?php

namespace Drupal\gathercontent;

use Drupal\gathercontent\Import\MenuCreator;
use Drupal\migrate\Event\MigrateImportEvent;
use Drupal\migrate_tools\MigrateExecutable as MigrateExecutableBase;
use Drupal\migrate\MigrateMessageInterface;
use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\migrate_plus\Event\MigratePrepareRowEvent;
use Drupal\node\Entity\Node;

/**
 * Defines a migrate executable class.
 */
class MigrateExecutable extends MigrateExecutableBase {

  /**
   * Migration options.
   *
   * @var array
   */
  protected $importOptions = [];

  /**
   * Gathercontent client.
   *
   * @var \Drupal\gathercontent\DrupalGatherContentClient
   */
  protected $client;

  /**
   * Latest GatherContent status.
   *
   * @var \GatherContent\DataTypes\Status
   */
  protected $latestGcStatus;

  /**
   * Entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Session manager.
   *
   * @var \Symfony\Component\HttpFoundation\Session\Session
   */
  protected $session;

  /**
   * Statuses.
   *
   * @var \GatherContent\DataTypes\Status[]
   */
  protected $statuses;

  /**
   * {@inheritdoc}
   */
  public function __construct(MigrationInterface $migration, MigrateMessageInterface $message, array $options = []) {
    parent::__construct($migration, $message, $options);

    if (isset($options['import_options'])) {
      $this->importOptions = $options['import_options'];
    }

    if (isset($options['client'])) {
      $this->client = $options['client'];
    }

    $this->entityTypeManager = \Drupal::service('entity_type.manager');
    $this->session = \Drupal::service('session');
  }

  /**
   * {@inheritdoc}
   */
  public function onPrepareRow(MigratePrepareRowEvent $event) {
    parent::onPrepareRow($event);
    $row = $event->getRow();

    $migration = $event->getMigration();
    $sourceId = array_merge(array_flip(array_keys($migration->getSourcePlugin()
      ->getIds())), $row->getSourceIdValues());

    if (!empty($this->importOptions[$sourceId['id']])) {
      /** @var \Drupal\gathercontent\Import\ImportOptions $options */
      $options = $this->importOptions[$sourceId['id']];
      $destinationConfiguration = $migration->getDestinationConfiguration();
      $plugin = explode(':', $destinationConfiguration['plugin']);

      $entityTypeManager = \Drupal::entityTypeManager();
      $entityDefinition = $entityTypeManager->getDefinition($plugin[1]);

      if ($entityDefinition->hasKey('published')) {
        $statusKey = $entityDefinition->getKey('published');
        $row->setDestinationProperty($statusKey, $options->getPublish());
      }

      $row->setDestinationProperty('gc_import_options/new_revision', $options->getCreateNewRevision());
    }

    $sourceConfiguration = $migration->getSourceConfiguration();
    $this->latestGcStatus = $this->client->projectStatusGet($sourceConfiguration['projectId'], $row->getSourceProperty('statusId'));
  }

  /**
   * {@inheritdoc}
   */
  public function onPostImport(MigrateImportEvent $event) {
    parent::onPostImport($event);
    $rows = [];

    $migration = $event->getMigration();
    $destinationConfiguration = $migration->getDestinationConfiguration();
    $plugin = explode(':', $destinationConfiguration['plugin']);
    $sourceConfiguration = $migration->getSourceConfiguration();
    $pluginDefinition = $migration->getPluginDefinition();

    foreach ($this->idlist as $item) {
      $rows[] = $event->getMigration()->getIdMap()->getRowBySource($item);
    }

    if (empty($rows)) {
      return;
    }

    foreach ($rows as $row) {
      if (empty($row) || empty($row['destid1'])) {
        continue;
      }

      if (!empty($this->importOptions[$row['sourceid1']])) {
        /** @var \Drupal\gathercontent\Import\ImportOptions $options */
        $options = $this->importOptions[$row['sourceid1']];
        $parentMenuItem = $options->getParentMenuItem();

        if (!empty($parentMenuItem) && $parentMenuItem != '0') {
          // TODO: Use the entity type from the mapping, not the node!
          /** @var \Drupal\node\NodeInterface $entity */
          $entity = Node::load($row['destid1']);

          // TODO: Rewrite menu creator to support none node entities too.
          if ($entity) {
            MenuCreator::createMenu($entity, $parentMenuItem);
          }
        }

        $newGcStatus = $options->getNewStatus();

        if ($newGcStatus && is_int($newGcStatus)) {
          $status = $this->client->projectStatusGet($sourceConfiguration['projectId'], $newGcStatus);

          // Update only if status exists.
          if ($status !== NULL) {
            // Update status on GC.
            $this->client->itemChooseStatusPost($row['sourceid1'], $newGcStatus);

            $this->latestGcStatus = $status;
          }
        }
      }

      $this->trackEntities($row, $plugin[1], $sourceConfiguration['templateName'], $migration->id(), $pluginDefinition['langcode']);
    }
  }

  /**
   * Tracks the entity changes, to show in a table after the migration run.
   */
  protected function trackEntities(array $row, string $plugin, string $templateName, $migrationId, string $langcode) {
    $tracked = $this->session->get('gathercontent_tracked_entities', []);

    $tracked[$row['sourceid1']] = [
      'id' => $row['destid1'],
      'entity_type' => $plugin,
      'status' => $this->latestGcStatus,
      'template_name' => $templateName,
    ];

    $this->session->set('gathercontent_tracked_entities', $tracked);

    $connection = \Drupal::service('database');
    $result = $connection->select('gathercontent_entity_mapping')
      ->fields('gathercontent_entity_mapping', [
        'entity_id',
        'entity_type',
      ])
      ->condition('entity_id', $row['destid1'])
      ->condition('entity_type', $plugin)
      ->condition('langcode', $langcode)
      ->execute()
      ->fetchAll();

    if (!empty($result)) {
      return;
    }

    $connection->insert('gathercontent_entity_mapping')
      ->fields([
        'entity_id' => $row['destid1'],
        'entity_type' => $plugin,
        'gc_id' => $row['sourceid1'],
        'migration_id' => $migrationId,
        'langcode' => $langcode,
      ])
      ->execute();
  }

}
