<?php

namespace Drupal\gathercontent\Plugin\migrate\process;

use GatherContent\GatherContentClientInterface;
use Drupal\Component\Render\PlainTextOutput;
use Drupal\Core\File\FileSystem;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\media\Entity\Media;
use Drupal\media\Entity\MediaType;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\Row;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Perform custom value transformation.
 *
 * @\Drupal\migrate\Annotation\MigrateProcessPlugin(
 *   id = "gather_content_media"
 * )
 *
 * @code
 * file:
 *   plugin: gather_content_media
 *   source: file
 *   uri_scheme: string
 *   file_dir: string
 *   language: string
 * @endcode
 */
class GatherContentMedia extends ProcessPluginBase implements ContainerFactoryPluginInterface {

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

    // The plugin does not handle_multiples, so we know that there is always one value.
    $fileId = $this->client->downloadFiles([$value], $this->configuration['uri_scheme'] . $fileDir, $language);
    $media_type = MediaType::load($this->configuration['bundle']);
    $fileId = reset($fileId);
    if (!$fileId) {
      return NULL;
    }
    $entity_ids = \Drupal::entityQuery('media')
      ->accessCheck(FALSE)
      ->condition($media_type->getSource()->getSourceFieldDefinition($media_type)->getName() . '.target_id', $fileId)
      ->execute();
    if ($entity_ids) {
      $media_id = reset($entity_ids);
    }
    else {
      $mediaData = [
        'bundle' => $this->configuration['bundle'],
        'status' => 1,
        'title' => $value->filename,
        $media_type->getSource()->getSourceFieldDefinition($media_type)->getName() => [
          'target_id' => $fileId,
          'alt' => $value->altText,
        ],
      ];
      $media = Media::create($mediaData);
      $media->save();
      $media_id = $media->id();
    }
    return ['target_id' => $media_id];
  }

}
