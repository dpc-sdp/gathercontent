<?php

namespace Drupal\gathercontent\Plugin\migrate\source;

use Cheppers\GatherContent\DataTypes\Element;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\gathercontent\DrupalGatherContentClient;
use Drupal\migrate\MigrateException;
use Drupal\migrate\Plugin\migrate\source\SourcePluginBase;
use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\migrate\Row;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * A source class for Gathercontent API.
 *
 * @MigrateSource(
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
    DrupalGatherContentClient $client
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

    $this->client = $client;
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
      $container->get('gathercontent.client')
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
   * Returns the chosen tab's data.
   *
   * @param array $data
   *   Tabs array.
   * @param int $tabId
   *   Tab ID.
   *
   * @return \Cheppers\GatherContent\DataTypes\Tab|bool
   *   Returns tab object or false on failure.
   */
  protected function getTabData(array $data, $tabId) {
    /** @var \Cheppers\GatherContent\DataTypes\Tab $pane */
    foreach ($data as $tab) {
      if ($tabId === $tab->id) {
        return $tab;
      }
    }

    return FALSE;
  }

  /**
   * Returns the correct files for the gathecontent content.
   *
   * @param array $gcFiles
   *   Gathercontent file array.
   * @param \Cheppers\GatherContent\DataTypes\Element $field
   *   Gathercontent field.
   *
   * @return array
   *   File list.
   */
  protected function getFiles(array $gcFiles, Element $field) {
    $value = [];

    foreach ($gcFiles as $file) {
      if ($file->field == $field->label) {
        $value[] = $file;
      }
    }

    return $value;
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

    if ($ret) {
      $gcId = $row->getSourceProperty('id');
      $gcItem = $this->client->itemGet($gcId);

      if (empty($gcItem)) {
        return FALSE;
      }

      $tabData = $this->getTabData($gcItem->config, $this->tabId);

      if (!$tabData) {
        return FALSE;
      }

      $gcFiles = $this->client->itemFilesGet($gcId);

      foreach ($tabData->elements as $field) {
        $value = $field->getValue();

        if ($field->type == 'files') {
          $value = $this->getFiles($gcFiles, $field);
        }

        $row->setSourceProperty($field->id, $value);
      }
    }

    return $ret;
  }

}
