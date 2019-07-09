<?php

namespace Drupal\gathercontent\Plugin\migrate\process;

use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Url;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\Plugin\migrate\process\Get;
use Drupal\migrate\Row;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Perform custom value transformation.
 *
 * The main reason this process plugin exists is to support link replace.
 *
 * @\Drupal\migrate\Annotation\MigrateProcessPlugin(
 *   id = "gather_content_get"
 * )
 *
 * @code
 * text_field:
 *   plugin: gather_content_get
 *   source: field_name
 * @endcode
 */
class GatherContentGet extends Get implements ContainerFactoryPluginInterface {

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * {@inheritdoc}
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    Connection $database,
    EntityTypeManagerInterface $entityTypeManager
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->database = $database;
    $this->entityTypeManager = $entityTypeManager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('database'),
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    $data = parent::transform($value, $migrate_executable, $row, $destination_property);

    return $this->replaceUrls($data);
  }

  /**
   * Replaces the GC urls to Drupal entity url.
   *
   * @param mixed $data
   *   String or array containing GC urls.
   *
   * @return array|string
   *   Returns the replaced string or array.
   */
  protected function replaceUrls($data) {
    if (!is_array($data)) {
      return $this->gcUrlToDrupal($data);
    }

    foreach ($data as $key => $value) {
      $data[$key] = $this->gcUrlToDrupal($value);
    }

    return $data;
  }

  /**
   * Returns formatted string with replaced urls.
   *
   * @param string $text
   *   String containing the urls.
   *
   * @return string
   *   Formatted string containing the replaced urls.
   */
  protected function gcUrlToDrupal(string $text) {
    $collectedUrls = [];

    preg_match_all("/https?:\/\/([a-z0-9]+)\.gathercontent\.com\/item\/(\d+)/", $text, $collectedUrls);

    if (empty($collectedUrls[0])) {
      return $text;
    }

    $gcUrls = array_unique(array_combine($collectedUrls[2], $collectedUrls[0]));

    $query = $this->database->select('gathercontent_entity_mapping')
      ->fields('gathercontent_entity_mapping', [
        'gc_id',
        'entity_id',
        'entity_type',
      ]);
    $query->condition('gc_id', array_unique($collectedUrls[2]), 'IN');
    $results = $query->execute()->fetchAll();

    if (empty($results)) {
      return $text;
    }

    $validGcUrls = [];
    $internalUrls = [];

    foreach ($results as $result) {
      $validGcUrls[] = $gcUrls[$result->gc_id];

      try {
        $entity = $this
          ->entityTypeManager
          ->getStorage($result->entity_type)
          ->load($result->entity_id);
      }
      catch (InvalidPluginDefinitionException $e) {

      }
      catch (PluginNotFoundException $e) {

      }

      $entityType = $entity->getEntityTypeId();
      $entityId = $entity->id();

      $internalUrls[] = Url::fromUri("base:/{$entityType}/{$entityId}")
        ->setAbsolute()
        ->toString();
    }

    $text = str_replace($validGcUrls, $internalUrls, $text);

    return $text;
  }

}
