<?php

namespace Drupal\gathercontent;

use Cheppers\GatherContent\GatherContentClient;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Pool;

/**
 * Extends the GatherContentClient class with Drupal specific functionality.
 */
class DrupalGatherContentClient extends GatherContentClient {

  /**
   * {@inheritdoc}
   */
  public function __construct(ClientInterface $client) {
    parent::__construct($client);
    $this->setCredentials();
  }

  /**
   * Put the authentication config into client.
   */
  public function setCredentials() {
    $config = \Drupal::config('gathercontent.settings');
    $this->setEmail($config->get('gathercontent_username') ?: '');
    $this->setApiKey($config->get('gathercontent_api_key') ?: '');
  }

  /**
   * Retrieve the account id of the given account.
   *
   * If none given, retrieve the first account by default.
   */
  public static function getAccountId($accountName = NULL) {
    $account = \Drupal::config('gathercontent.settings')
      ->get('gathercontent_account');
    $account = unserialize($account);
    if (!is_array($account)) {
      return NULL;
    }

    if (!$accountName) {
      if (reset($account)) {
        return key($account);
      }
    }

    foreach ($account as $id => $name) {
      if ($name === $accountName) {
        return $id;
      }
    }

    return NULL;
  }

  /**
   * Retrieve all the active projects.
   */
  public function getActiveProjects($accountId) {
    $projects = $this->projectsGet($accountId);

    foreach ($projects as $id => $project) {
      if (!$project->active) {
        unset($projects[$id]);
      }
    }

    return $projects;
  }

  /**
   * Returns a formatted array with the template ID's as a key.
   *
   * @param int $project_id
   *   Project ID.
   *
   * @return array
   *   Return array.
   */
  public function getTemplatesOptionArray($projectId) {
    $formatted = [];
    $templates = $this->templatesGet($projectId);

    foreach ($templates as $id => $template) {
      $formatted[$id] = $template->name;
    }

    return $formatted;
  }

  /**
   * Returns the response body.
   *
   * @param bool $json_decoded
   *   If TRUE the method will return the body json_decoded.
   *
   * @return \Psr\Http\Message\StreamInterface
   *   Response body.
   */
  public function getBody($jsonDecoded = FALSE) {
    $body = $this->getResponse()->getBody();

    if ($jsonDecoded) {
      return \GuzzleHttp\json_decode($body);
    }

    return $body;
  }

  /**
   * Downloads all files asynchronously.
   *
   * @param array $files
   *   Files object array.
   * @param string $directory
   *   Destination directory.
   * @param string $language
   *   Language string.
   *
   * @return array
   *   Imported files array.
   */
  public function downloadFiles(array $files, $directory, $language) {
    /** @var \GuzzleHttp\Client $httpClient */
    $httpClient = $this->client;
    $options = [
      'auth' => $this->getRequestAuth(),
      'headers' => [],
    ];

    $options['headers'] += $this->getRequestHeaders();

    $files = array_values($files);
    $importedFiles = [];

    $requests = function () use ($httpClient, $files, $options) {
      foreach ($files as $file) {
        $url = $this->getUri("files/{$file->id}/download");

        yield function () use ($httpClient, $url, $options) {
          return $httpClient->getAsync($url, $options);
        };
      }
    };

    $pool = new Pool(
      $httpClient,
      $requests(),
      [
        'fulfilled' => function ($response, $index) use ($files, $directory, $language, &$importedFiles) {
          if ($response->getStatusCode() === 200) {
            $path = $directory . '/' . $files[$index]->fileName;

            $importedFile = file_save_data($response->getBody(), $path);

            if ($importedFile) {
              $importedFile
                ->set('gc_id', $files[$index]->id)
                ->set('langcode', $language)
                ->set('filesize', $files[$index]->size)
                ->save();

              $importedFiles[$index] = $importedFile->id();
            }
          }
        },
      ]
    );

    $promise = $pool->promise();
    $promise->wait();

    ksort($importedFiles);
    return $importedFiles;
  }

  /**
   * Returns the collected folder UUIDs.
   *
   * @param $projectId
   *   Project ID.
   * @param $gcId
   *   GatherContent Item ID.
   *
   * @return array
   *   Array containing the collected folder ids.
   */
  public function getSubFolderIds($projectId, $gcId) {
    $item = $this->itemGet($gcId);

    if (!$item) {
      return [];
    }

    $folders = $this->foldersGet($projectId);

    if (!$folders) {
      return [];
    }

    $parentFolderUuid = 0;
    foreach ($folders as $folder) {
      if ($item->folderUuid == $folder->id && $folder->type !== 'project-root') {
        $parentFolderUuid = $folder->id;
      }
    }

    if (!$parentFolderUuid) {
      return [];
    }

    $folderIds = [];
    foreach ($folders as $folder) {
      if ($parentFolderUuid == $folder->parentUuid) {
        $folderIds[] = $folder->id;
      }
    }

    return $folderIds;
  }

  /**
   * Returns the first level children IDs for a given item.
   *
   * @param $projectId
   *   Project ID.
   * @param $parentId
   *   Parent GatherContent Item ID.
   *
   * @return array
   *   Collected children IDs.
   */
  public function getChildrenIds($projectId, $parentId) {
    $folderIds = $this->getSubFolderIds($projectId, $parentId);

    if (!$folderIds) {
      return [];
    }

    $collectedChildrenIds = [];
    $items = $this->itemsGet($projectId);

    foreach ($items as $item) {
      if (!in_array($item->folderUuid, $folderIds)) {
        continue;
      }

      $collectedChildrenIds[] = $item->id;
    }

    return $collectedChildrenIds;
  }

  /**
   * Returns all the children IDs for a given item from every level.
   *
   * @param $projectId
   *   Project ID.
   * @param $parentId
   *   Parent item ID.
   *
   * @return array
   *   Collected children IDs.
   */
  public function getAllChildrenIds($projectId, $parentId) {
    $deeperChildrenIds = [];
    $currentLevelChildrenIds = $this->getChildrenIds($projectId, $parentId);

    foreach ($currentLevelChildrenIds as $childrenId) {
      $collectedChildrenIds = $this->getAllChildrenIds($projectId, $childrenId);
      $deeperChildrenIds = array_unique(array_merge(
        $deeperChildrenIds, $collectedChildrenIds
      ), SORT_REGULAR);
    }

    return array_unique(array_merge(
      $currentLevelChildrenIds, $deeperChildrenIds
    ), SORT_REGULAR);
  }

}
