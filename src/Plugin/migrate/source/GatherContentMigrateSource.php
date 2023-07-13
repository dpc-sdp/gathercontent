<?php

namespace Drupal\gathercontent\Plugin\migrate\source;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\gathercontent\DrupalGatherContentClient;
use Drupal\migrate\MigrateException;
use Drupal\migrate\Plugin\migrate\source\SourcePluginBase;
use Drupal\migrate\Plugin\MigrateIdMapInterface;
use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\migrate\Row;
use GatherContent\DataTypes\Element;
use GatherContent\DataTypes\ElementSimpleChoice;
use GatherContent\DataTypes\ElementSimpleFile;
use GatherContent\DataTypes\ElementSimpleText;
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
   * List of GatherContent IDs to import.
   *
   * If set, the collection lookups will be skipped and only those items will
   * be loaded.
   *
   * @var array
   */
  protected array $ids;

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
  public function get(string $property) {
    return $this->{$property};
  }

  /**
   * Get all items for given project and template.
   *
   * @return array
   *   All items.
   */
  public function getItems() {
    if ($this->items !== NULL) {
      return $this->items;
    }

    $ids = $this->getItemIds();

    foreach ($ids as $id) {
      $collectedMetaTags = [];
      $gcItem = $this->client->itemGet($id);
      if (empty($gcItem)) {
        continue;
      }

      $item = get_object_vars($gcItem);
      $item['item_title'] = $gcItem->name;

      foreach ($gcItem->content as $fieldId => $field) {
        $value = $this->getFieldValue($field);

        // Check if the field is for meta tags.
        if (array_key_exists($fieldId, $this->metatagFields)) {
          $collectedMetaTags[$this->metatagFields[$fieldId]] = $value;
          continue;
        }

        $item[$fieldId] = $value;
      }

      if (!empty($collectedMetaTags)) {
        $value = $this->prepareMetatags($collectedMetaTags);
        $item['meta_tags'] = $value;
      }

      // In single component mode, define an item for each entry in the
      // specified component.
      if (!empty($this->configuration['singleComponent'])) {
        if (!empty($item[$this->configuration['singleComponent']][0])) {
          foreach ($item[$this->configuration['singleComponent']] as $component_delta => $component_value) {
            $component_item = $item;
            $component_item[$this->configuration['singleComponent']] = $component_value;
            $component_item['id'] = $id . ':' . $component_delta;

            $this->items[$component_item['id']] = $component_item;
          }

          // Do not add the item again.
          continue;
        }
      }

      $this->items[$id] = $item;
    }

    return $this->items;
  }

  /**
   * Limit the source to the given IDs.
   *
   * @param array $ids
   */
  public function setItemIds(array $ids): void {
    $this->ids = $ids;
  }

  /**
   * Either returns the set item ids or returns all for this project/template.
   *
   * @return array
   *   List of IDS.
   */
  public function getItemIds(): array {
    if (isset($this->ids)) {
      return $this->ids;
    }
    $current_page = 0;
    $this->ids = [];
    do {
      $response = $this->client->itemsGet($this->projectId, [
        'template_id' => $this->templateId,
        'page' => ($current_page + 1),
      ]);

      // The first response will reveal the total number of pages. If there
      // is more than one page, continue until total pages has been reached.
      if (empty($response['data'])) {
        break;
      }

      /** @var \GatherContent\DataTypes\Pagination $pagination */
      $pagination = $response['pagination'];
      $total_pages = $pagination->totalPages;
      $current_page = $pagination->currentPage;

      foreach ($response['data'] as $key => $item) {
        $this->ids[] = $item->id;
      }
    }
    while ($current_page < $total_pages);

    return $this->ids;
  }

  /**
   * Convert items to array.
   *
   * @param array $items
   *   Items to covert.
   *
   * @return array
   *   The converted array.
   */
  protected function convertItemsToArray(array $items) {
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
   * @param \GatherContent\DataTypes\Element $field
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
      foreach ($row->getSource() as $fieldid => $field) {
        if (in_array($fieldid, $this->fields) && is_array($field)) {
          foreach ($field as $subObject) {
            if ($subObject instanceof ElementSimpleFile) {
              // Entity with image needs to update as the alt_text property of
              // the image may have been updated/changed in GatherContent.
              $idMap = $row->getIdMap();
              $idMap['source_row_status'] = MigrateIdMapInterface::STATUS_NEEDS_UPDATE;
              $row->setIdMap($idMap);
              break 2;
            }
          }
        }
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

    foreach ($field as $key => $item) {
      if ($item instanceof ElementSimpleChoice) {
        $value[$key] = [
          'gc_id' => $item->id,
        ];
      }
      elseif ($item instanceof ElementSimpleFile) {
        $value[$key] = $item;
      }
      elseif ($item instanceof ElementSimpleText) {
        // Lists of texts are keyed in an extra value, other texts
        // for example in a component) are not.
        if (is_int($key)) {
          $value[$key] = ['value' => $item->getValue()];
        }
        else {
          $value[$key] = $item->getValue();
        }
      }
      elseif (!is_array($item)) {
        $value[$key] = $item->getValue();
      }
      else {
        $value[$key] = $this->getFieldValue($item);
      }
    }

    return $value;
  }

}
