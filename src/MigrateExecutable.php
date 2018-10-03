<?php

namespace Drupal\gathercontent;

use Drupal\gathercontent\Import\MenuCreator;
use Drupal\migrate\Event\MigrateImportEvent;
use Drupal\migrate\MigrateSkipRowException;
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
   * {@inheritdoc}
   */
  public function __construct(MigrationInterface $migration, MigrateMessageInterface $message, array $options = []) {
    parent::__construct($migration, $message, $options);

    if (isset($options['import_options'])) {
      $this->importOptions = $options['import_options'];
    }
  }

  /**
   * {@inheritdoc}
   */
  public function onPrepareRow(MigratePrepareRowEvent $event) {
    parent::onPrepareRow($event);
    $row = $event->getRow();

    $migration = $event->getMigration();
    $source_id = array_merge(array_flip(array_keys($migration->getSourcePlugin()
      ->getIds())), $row->getSourceIdValues());

    /** @var \Drupal\gathercontent\Import\ImportOptions $options */
    $options = $this->importOptions[$source_id['id']];

    if (empty($options)) {
      throw new MigrateSkipRowException(NULL, FALSE);
    }

    $row->setDestinationProperty('status', $options->getPublish());
    $row->setDestinationProperty('gc_import_options/new_revision', $options->getCreateNewRevision());
    $row->setDestinationProperty('gc_import_options/gc_new_status', $options->getNewStatus());
  }

  /**
   * {@inheritdoc}
   */
  public function onPostImport(MigrateImportEvent $event) {
    parent::onPostImport($event);
    $rows = [];

    foreach ($this->idlist as $item) {
      $rows[] = $event->getMigration()->getIdMap()->getRowBySource($item);
    }

    if (empty($rows)) {
      return;
    }

    foreach ($rows as $row) {
      /** @var \Drupal\gathercontent\Import\ImportOptions $options */
      $options = $this->importOptions[$row['sourceid1']];
      $parent_menu_item = $options->getParentMenuItem();

      if ($parent_menu_item != '0') {
        $entity = Node::load($row['destid1']);

        MenuCreator::createMenu($entity, $parent_menu_item);
      }
    }
  }

}
