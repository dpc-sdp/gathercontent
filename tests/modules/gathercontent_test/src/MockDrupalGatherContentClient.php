<?php

namespace Drupal\gathercontent_test;

use Drupal\file\Entity\File;
use Drupal\gathercontent\DrupalGatherContentClient;

/**
 * Class to mock GC client.
 */
class MockDrupalGatherContentClient extends DrupalGatherContentClient {

  public static $choosenStatus = NULL;

  public $mockItems = [];

  /**
   * Set mock items.
   *
   * @param array $mockItems
   *   Mock items array.
   */
  public function setMockItems(array $mockItems) {
    $this->mockItems = $mockItems;
  }

  /**
   * {@inheritdoc}
   */
  public function itemsGet($projectId) {
    return $this->mockItems['project_items'][$projectId];
  }

  /**
   * {@inheritdoc}
   */
  public function itemGet($itemId) {
    return $this->mockItems['detailed_items'][$itemId];
  }

  /**
   * Mock download.
   */
  public function downloadFiles(array $files, $directory, $language) {
    $importedFiles = [];
    foreach ($files as $file) {
      $importedFile = File::create([
        'filename' => $file->fileName,
        'uri' => $file->url,
        'status' => 1,
        'gc_id' => $file->id,
        'langcode' => $language,
        'filesize' => $file->size,
      ]);
      $importedFile->save();
      $importedFiles[] = $importedFile->id();
    }
    return $importedFiles;
  }

  /**
   * Mock files fetch.
   */
  public function itemFilesGet($itemId) {
    return [];
  }

  /**
   * Mock status fetch.
   */
  public function projectStatusGet($projectId, $statusId) {
    $statuses = MockData::getStatuses();
    return $statuses[$statusId];
  }

  /**
   * Mock status change.
   */
  public function itemChooseStatusPost($itemId, $statusId) {
    if (static::$choosenStatus !== NULL) {
      throw new \Exception("itemChooseStatusPost shouldn't be called twice");
    }
    static::$choosenStatus = $statusId;
  }

}
