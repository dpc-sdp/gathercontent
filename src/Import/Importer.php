<?php

namespace Drupal\gathercontent\Import;

use Cheppers\GatherContent\DataTypes\Item;
use Cheppers\GatherContent\GatherContentClientInterface;
use Drupal\Component\EventDispatcher\ContainerAwareEventDispatcher;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\gathercontent\Entity\Mapping;
use Drupal\gathercontent\Event\GatherContentEvents;
use Drupal\gathercontent\Event\PostNodeSaveEvent;
use Drupal\gathercontent\Event\PreNodeSaveEvent;
use Drupal\gathercontent\Import\ContentProcess\ContentProcessor;
use Drupal\gathercontent\MappingLoader;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Class for handling import/update logic from GatherContent to Drupal.
 */
class Importer implements ContainerInjectionInterface {

  /**
   * Drupal GatherContent Client.
   *
   * @var \Drupal\gathercontent\DrupalGatherContentClient
   */
  protected $client;

  /**
   * The ContentProcessor fills the nodes with imported content.
   *
   * @var \Drupal\gathercontent\Import\ContentProcess\ContentProcessor
   */
  protected $contentProcessor;

  protected $eventDispatcher;

  /**
   * Importer.
   */
  public function __construct(
    GatherContentClientInterface $client,
    ContentProcessor $contentProcessor,
    EventDispatcherInterface $eventDispatcher
  ) {
    $this->client = $client;
    $this->contentProcessor = $contentProcessor;
    $this->eventDispatcher = $eventDispatcher;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('gathercontent.client'),
      $container->get('gathercontent.content_processor'),
      $container->get('event_dispatcher')
    );
  }

  /**
   * Don't forget to add a finished callback and the operations array.
   */
  public static function getBasicImportBatch() {
    return [
      'title' => t('Importing'),
      'init_message' => t('Starting import'),
      'error_message' => t('An error occurred during processing'),
      'progress_message' => t('Processed @current out of @total.'),
      'progressive' => TRUE,
    ];
  }

  /**
   * Getter GatherContentClient.
   */
  public function getClient() {
    return $this->client;
  }

  /**
   * Import a single GatherContent item to Drupal.
   *
   * This function is a replacement for the old _gc_fetcher function.
   *
   * The caller (e.g. batch processes) should handle the thrown exceptions.
   *
   * @return int
   *   The ID of the imported entity.
   */
  public function import(Item $gc_item, ImportOptions $importOptions) {
    $this->updateStatus($gc_item, $importOptions->getNewStatus());
    $files = $this->client->itemFilesGet($gc_item->id);
    $entity = $this->contentProcessor->createNode($gc_item, $importOptions, $files);

    $this->eventDispatcher->dispatch(
      GatherContentEvents::PRE_NODE_SAVE,
      new PreNodeSaveEvent($entity, $gc_item, $files)
    );

    $entity->save();
    $entity = $entity->getTranslation(reset($entity->getTranslationLanguages())->getId());

    static::createMenu($entity, $importOptions->getParentMenuItem());

    $this->eventDispatcher->dispatch(
      GatherContentEvents::POST_NODE_SAVE,
      new PostNodeSaveEvent($entity, $gc_item, $files)
    );

    return $entity->id();
  }

  /**
   * Create a menu link to the imported node.
   */
  public static function createMenu(NodeInterface $entity, $parentMenuItem) {
    $isContentTypeTranslatable = static::isContentTypeTranslatable($entity->bundle());
    $isMenuTranslatable = static::isMenuTranslatable();
    $menuLinkDefaults = menu_ui_get_menu_link_defaults($entity);
    if (!(bool) $menuLinkDefaults['id']) {
      if ($isContentTypeTranslatable && $isMenuTranslatable) {
        $languages = $entity->getTranslationLanguages();
        $originalLinkId = NULL;
        foreach ($languages as $langcode => $language) {
          $localized_entity = $entity->hasTranslation($langcode) ? $entity->getTranslation($langcode) : NULL;
          if (!is_null($localized_entity)) {
            gc_create_menu_link($entity->id(), $localized_entity->getTitle(), $parentMenuItem, $langcode, $originalLinkId);
          }
        }
      }
      else {
        gc_create_menu_link($entity->id(), $entity->getTitle(), $parentMenuItem);
      }
    }
  }

  /**
   * Update item's status based on status id. Upload new status to GC.
   */
  protected function updateStatus(Item $item, $statusId) {
    if (!is_int($statusId)) {
      // User does not want to update status.
      return;
    }

    $status = $this->client->projectStatusGet($item->projectId, $statusId);

    // Update only if status exists.
    if ($status !== NULL) {
      // Update status on GC.
      $this->client->itemChooseStatusPost($item->id, $statusId);
      // Update status of item (will be uploaded to Drupal on import).
      $item->status = $status;
    }
  }

  /**
   * Decide whether a content type is translatable.
   */
  public static function isContentTypeTranslatable($contentType) {
    return \Drupal::moduleHandler()
      ->moduleExists('content_translation')
      && \Drupal::service('content_translation.manager')
        ->isEnabled('node', $contentType);
  }

  /**
   * Decide whether a menu is translatable.
   */
  public static function isMenuTranslatable() {
    return \Drupal::moduleHandler()
      ->moduleExists('content_translation')
      && \Drupal::service('content_translation.manager')
        ->isEnabled('menu_link_content');
  }

}
