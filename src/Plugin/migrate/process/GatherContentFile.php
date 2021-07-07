<?php

namespace Drupal\gathercontent\Plugin\migrate\process;

use Cheppers\GatherContent\GatherContentClientInterface;
use Drupal\Component\Render\PlainTextOutput;
use Drupal\Core\File\FileSystem;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\Row;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Perform custom value transformation.
 *
 * @\Drupal\migrate\Annotation\MigrateProcessPlugin(
 *   id = "gather_content_file"
 * )
 *
 * @code
 * file:
 *   plugin: gather_content_file
 *   source: file
 *   uri_scheme: string
 *   file_dir: string
 *   language: string
 * @endcode
 */
class GatherContentFile extends ProcessPluginBase implements ContainerFactoryPluginInterface {

  /**
   * GatherContent client.
   *
   * @var \Drupal\gathercontent\DrupalGatherContentClient
   */
  protected $client;

  /**
   * File system service.
   *
   * @var \Drupal\Core\File\FileSystem
   */
  protected $fileSystem;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, GatherContentClientInterface $client, FileSystem $fileSystem) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->client = $client;
    $this->fileSystem = $fileSystem;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('gathercontent.client'),
      $container->get('file_system')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    if (empty($value)) {
      return NULL;
    }

    $language = $this->configuration['language'];
    $fileDir = PlainTextOutput::renderFromHtml(\Drupal::token()->replace($this->configuration['file_dir'], []));
    $createDir = $this->fileSystem->realpath($this->configuration['uri_scheme']) . '/' . $fileDir;
    $this->fileSystem->prepareDirectory($createDir, FileSystemInterface::CREATE_DIRECTORY);

    if (is_array($value)) {
      return $this->client->downloadFiles($value, $this->configuration['uri_scheme'] . $fileDir, $language);
    }

    $fileId = $this->client->downloadFiles([$value], $this->configuration['uri_scheme'] . $fileDir, $language);

    if (empty($fileId[0])) {
      return NULL;
    }

    $result['target_id'] = $fileId[0];
    if ($value->altText) {
      $result['alt'] = $value->altText;
    }

    return $result;
  }

}
