<?php

namespace Drupal\gathercontent;

use function GuzzleHttp\json_decode;
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

    foreach ($projects['data'] as $id => $project) {
      if (!$project->active) {
        unset($projects['data'][$id]);
      }
    }

    $projects['data'] = $this->reKeyArray($projects['data'], 'id');

    return $projects;
  }

  /**
   * Returns a formatted array with the template ID's as a key.
   *
   * @param int $projectId
   *   Project ID.
   *
   * @return array
   *   Return array.
   */
  public function getTemplatesOptionArray($projectId) {
    $formatted = [];
    $templates = $this->templatesGet($projectId);

    foreach ($templates['data'] as $template) {
      $formatted[$template->id] = $template->name;
    }

    return $formatted;
  }

  /**
   * Returns the response body.
   *
   * @param bool $jsonDecoded
   *   If TRUE the method will return the body json_decoded.
   *
   * @return \Psr\Http\Message\StreamInterface
   *   Response body.
   */
  public function getBody($jsonDecoded = FALSE) {
    $body = $this->getResponse()->getBody();

    if ($jsonDecoded) {
      return json_decode($body);
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
    $entityTypeManager = \Drupal::service('entity_type.manager');
    /** @var \GuzzleHttp\Client $httpClient */
    $httpClient = $this->client;
    $options = [
      'auth' => $this->getRequestAuth(),
      'headers' => [],
    ];

    $options['headers'] += $this->getRequestHeaders();

    // Remove unnecessary associative array keys.
    $files = array_values($files);
    $importedFiles = [];

    $requests = function () use ($httpClient, $files, $options) {
      foreach ($files as $file) {
        if (!$file) {
          continue;
        }

        yield function () use ($httpClient, $file, $options) {
          return $httpClient->getAsync($file->url, $options);
        };
      }
    };

    $pool = new Pool(
      $httpClient,
      $requests(),
      [
        'fulfilled' => function ($response, $index) use ($files, $directory, $language, &$importedFiles, $entityTypeManager) {
          if ($response->getStatusCode() === 200) {
            $file = $entityTypeManager
              ->getStorage('file')
              ->loadByProperties(['gc_file_id' => $files[$index]->fileId]);

            if ($file) {
              $file = reset($file);
              $importedFiles[$index] = $file->id();
              return;
            }

            $path = $directory . '/' . $files[$index]->filename;

            $importedFile = file_save_data($response->getBody(), $path);

            if ($importedFile) {
              $importedFile
                ->set('gc_file_id', $files[$index]->fileId)
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
   * {@inheritdoc}
   */
  public function templateGet($templateId) {
    $template = parent::templateGet($templateId);

    if (empty($template['related'])) {
      return $template;
    }

    // Add the ID as the array key.
    $groups = $template['related']->structure->groups;

    foreach ($groups as $group) {
      $group->fields = $this->reKeyArray($group->fields, 'id');
    }

    $template['related']->structure->groups = $this->reKeyArray($groups, 'id');

    return $template;
  }

  /**
   * {@inheritdoc}
   */
  public function projectStatusesGet($projectId) {
    $statuses = parent::projectStatusesGet($projectId);

    if (empty($statuses['data'])) {
      return $statuses;
    }

    // Add the ID as the array key.
    $statuses['data'] = $this->reKeyArray($statuses['data'], 'id');

    return $statuses;
  }

  /**
   * Create new assoc array with the key parameter as array key.
   *
   * @param array $array
   *   Array to re-key.
   * @param string $key
   *   The key to re-key by.
   *
   * @return array
   *   Returns the re-keyed array.
   */
  protected function reKeyArray(array $array, $key) {
    $items = [];
    foreach ($array as $item) {
      $items[$item->{$key}] = $item;
    }

    return $items;
  }

}
