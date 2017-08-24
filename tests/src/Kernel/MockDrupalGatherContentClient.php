<?php

namespace Drupal\Tests\gathercontent\Kernel;

use Drupal\file\Entity\File;
use Drupal\gathercontent\DrupalGatherContentClient;

/**
 * Class to mock GC client.
 */
class MockDrupalGatherContentClient extends DrupalGatherContentClient {

  /**
   * Mock download.
   */
  public function downloadFiles(array $files, $directory, $language) {
    $importedFiles = [];
    foreach ($files as $file) {
      $importedFile = File::create([
        'filename' => $file->name,
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

}