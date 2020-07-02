<?php

namespace Drupal\gathercontent\Plugin\migrate\source;

use Cheppers\GatherContent\DataTypes\Element;
use Cheppers\GatherContent\DataTypes\ElementSimpleChoice;
use Cheppers\GatherContent\DataTypes\ElementSimpleFile;
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
   * An array of source fields.
   *
   * @var array
   */
  protected $fields = [];

  /**
   * An array of metatag source fields.
   *
   * @var array
   */
  protected $metatagFields = [];

  /**
   * Drupal GatherContent Client.
   *
   * @var \Drupal\gathercontent\DrupalGatherContentClient
   */
  protected $client;

  /**
   * {@inheritdoc}
   */
  protected $trackChanges = TRUE;

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
      'fields',
      'metatagFields',
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
      $this->items = $this->client->itemsGet(
        $this->projectId,
        ['template_id' => $this->templateId]
      );
    }

    return $this->convertItemsToArray($this->items['data']);
  }

  /**
   * Convert items to array.
   */
  protected function convertItemsToArray($items) {
    $converted = [];

    if ($items !== NULL) {
      foreach ($items as $key => $item) {
        $converted[$key] = get_object_vars($item);
      }
    }

    return $converted;
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
      if ($file->field == $field->id) {
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
      $collectedMetaTags = [];
      $gcId = $row->getSourceProperty('id');
      $gcItem = $this->client->itemGet($gcId);

      if (empty($gcItem)) {
        return FALSE;
      }

      foreach ($gcItem->content as $fieldId => $field) {
        $value = $this->getFieldValue($field);

        // Check if the field is for meta tags.
        if (array_key_exists($fieldId, $this->metatagFields)) {
          $collectedMetaTags[$this->metatagFields[$fieldId]] = $value;
          continue;
        }

        $row->setSourceProperty($fieldId, $value);
      }

      $row->setSourceProperty('item_title', $gcItem->name);

      if (!empty($collectedMetaTags)) {
        $value = $this->prepareMetatags($collectedMetaTags);
        $row->setSourceProperty('meta_tags', $value);
      }
    }

    return $ret;
  }

  /**
   * Returns the collected metatags values serialized.
   *
   * @param array $collectedMetaTags
   *   The collected metatags.
   *
   * @return string
   *   Serialized string.
   */
  protected function prepareMetatags(array $collectedMetaTags) {
    return serialize($collectedMetaTags);
  }

  /**
   * Get field's value.
   *
   * @param mixed $field
   *   Field object/objects.
   *
   * @return array
   *   Returns value.
   */
  protected function getFieldValue($field) {
    if (!is_array($field)) {
      return $field->getValue();
    }

    $value = [];

    foreach ($field as $item) {
      if ($item instanceof ElementSimpleChoice) {
        $value[] = [
          'gc_id' => $item->id,
        ];
      }
      if ($item instanceof ElementSimpleFile) {
        $value[] = $item;
      }
    }

    return $value;
  }

}
