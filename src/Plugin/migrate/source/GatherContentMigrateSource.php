<?php

namespace Drupal\gathercontent\Plugin\migrate\source;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\gathercontent\Import\Importer;
use Drupal\migrate\MigrateException;
use Drupal\migrate\Plugin\migrate\source\SourcePluginBase;
use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\migrate\Row;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * A source class for Gathercontent API.
 *
 * @\Drupal\migrate\Annotation\MigrateSource(
 *   id = "gathercontent_migration"
 * )
 */
class GatherContentMigrateSource extends SourcePluginBase implements ContainerFactoryPluginInterface {

  /**
   * Project ID.
   *
   * @var int
   */
  protected $projectId;

  /**
   * Template ID.
   *
   * @var int
   */
  protected $templateId;

  /**
   * Item tab ID.
   *
   * @var int
   */
  protected $tabId;

  /**
   * An array of source fields.
   *
   * @var array
   */
  protected $fields = [];

  /**
   * Gathercontent importer.
   *
   * @var \Drupal\gathercontent\Import\Importer
   */
  protected $importer;

  /**
   * Drupal GatherContent Client.
   *
   * @var \Drupal\gathercontent\DrupalGatherContentClient
   */
  protected $client;

  /**
   * {@inheritdoc}
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    MigrationInterface $migration,
    Importer $importer
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $migration);

    $configFields = [
      'projectId',
      'templateId',
      'tabId',
      'fields',
    ];

    foreach ($configFields as $configField) {
      if (isset($configuration[$configField])) {
        $this->{$configField} = $configuration[$configField];
      }
      else {
        throw new MigrateException("The source configuration must include '$configField'.");
      }
    }

    $this->importer = $importer;
    $this->client = $this->importer->getClient();
  }

  /**
   * {@inheritdoc}
   */
  public static function create(
    ContainerInterface $container,
    array $configuration,
    $plugin_id,
    $plugin_definition,
    MigrationInterface $migration = NULL
  ) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $migration,
      $container->get('gathercontent.importer')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function count($refresh = FALSE) {
    return count($this->getItems());
  }

  /**
   * {@inheritdoc}
   */
  public function getIds() {
    return [
      'id' => ['type' => 'string'],
    ];
  }

  /**
   * Items to import.
   *
   * @var array
   */
  protected $items = NULL;

  /**
   * {@inheritdoc}
   */
  public function __toString() {
    return 'Gathercontent migration';
  }

  /**
   * {@inheritdoc}
   */
  public function fields() {
    return $this->fields;
  }

  /**
   * Get protected values.
   *
   * @param string $property
   *   Property name.
   *
   * @return mixed
   *   Value of the property.
   */
  public function get($property) {
    return $this->{$property};
  }

  /**
   * Get all items for given project and template.
   *
   * @return array
   *   All items.
   */
  protected function getItems() {
    if ($this->items === NULL) {
      $this->items = $this->client->itemsGet($this->projectId);
    }

    $this->clearUnwantedItems();

    return $this->items;
  }

  /**
   * Remove items which are not connected to the template id.
   */
  protected function clearUnwantedItems() {
    if ($this->items !== NULL) {
      foreach ($this->items as &$item) {
        if ($item->templateId !== $this->templateId) {
          unset($item);
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function initializeIterator() {
    return new \ArrayIterator($this->getItems());
  }

  /**
   * {@inheritdoc}
   */
  public function prepareRow(Row $row) {
    $ret = parent::prepareRow($row);

    $gcId = $row->getSourceProperty('id');

    $gcItem = $this->client->itemGet($gcId);

    return $ret;
  }

}
