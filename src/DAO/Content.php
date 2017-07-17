<?php

namespace Drupal\gathercontent\DAO;

use GuzzleHttp\Client;

/**
 * Class Account.
 *
 * @package GatherContent
 */
class Content {

  private $client;

  /**
   * Account constructor.
   *
   * @param string $username
   *   API username.
   * @param string $api_key
   *   API key.
   */
  public function __construct($username = NULL, $api_key = NULL) {
    if (is_null($username)) {
      $username = \Drupal::config('gathercontent.settings')
        ->get('gathercontent_username');
    }

    if (is_null($api_key)) {
      $api_key = \Drupal::config('gathercontent.settings')
        ->get('gathercontent_api_key');
    }

    if (empty($username) || empty($api_key)) {
      \Drupal::logger('gathercontent')
        ->error("Trying to call API without credentials.", []);
    }

    $this->client = new Client(
      [
        'base_uri' => 'https://api.gathercontent.com',
        'auth' => [
          $username,
          $api_key,
        ],
        'headers' => [
          'Accept' => 'application/vnd.gathercontent.v0.5+json',
        ],
      ]
    );

  }

  /**
   * Update status of content.
   *
   * @param int $content_id
   *   ID of content we want to update.
   * @param int $status_id
   *   Status ID.
   *
   * @return bool
   *   Return TRUE on success.
   */
  public function updateStatus($content_id, $status_id) {
    try {
      $response = $this->client->post('/items/' . $content_id . '/choose_status', [
        'form_params' => [
          'status_id' => $status_id,
        ],
      ]);
      if ($response->getStatusCode() === 202) {
        return TRUE;
      }
      else {
        return FALSE;
      }
    }
    catch (\Exception $e) {
      drupal_set_message($e->getMessage(), 'error');
      \Drupal::logger('gathercontent')->error($e->getMessage(), []);
      return FALSE;
    }
  }

  /**
   * Get list of content.
   *
   * @param int $project_id
   *   ID of project we want items from.
   *
   * @return array
   *   Array with content.
   */
  public function getContents($project_id) {
    $accounts = [];

    try {
      $response = $this->client->get('/items?project_id=' . $project_id);

      if ($response->getStatusCode() === 200) {
        $data = json_decode($response->getBody());
        $accounts = $data->data;
      }
      else {
        drupal_set_message(t("User with provided credentials wasn't found."), 'error');
      }
    }
    catch (\Exception $e) {
      drupal_set_message($e->getMessage(), 'error');
      \Drupal::logger('gathercontent')->error($e->getMessage(), []);
    }

    return $accounts;
  }

  /**
   * Get single piece of content.
   *
   * @param int $content_id
   *   ID of content, we want to fetch.
   *
   * @return null|object
   *   Content received from GatherContent.
   */
  public function getContent($content_id) {
    $content = NULL;

    try {
      $response = $this->client->get('/items/' . $content_id);

      if ($response->getStatusCode() === 200) {
        $data = json_decode($response->getBody());
        $content = $data->data;
      }
      else {
        drupal_set_message(t("User with provided credentials wasn't found."), 'error');
      }
    }
    catch (\Exception $e) {
      drupal_set_message($e->getMessage(), 'error');
      \Drupal::logger('gathercontent')->error($e->getMessage(), []);
    }

    return $content;
  }

  /**
   * Update content in GatherContent.
   *
   * @param int $content_id
   *   ID of content we want to update.
   * @param array $config
   *   Configration array.
   *
   * @return bool
   *   Return boolean value.
   */
  public function postContent($content_id, array $config) {
    try {
      $response = $this->client->post('/items/' . $content_id . '/save', ['form_params' => ['config' => base64_encode(json_encode($config))]]);
      if ($response->getStatusCode() === 202) {
        return TRUE;
      }
      else {
        \Drupal::logger('gathercontent')
          ->alert('Upload return code:' . $response->getStatusCode(), TRUE);
        return FALSE;
      }
    }
    catch (\Exception $e) {
      \Drupal::logger('gathercontent')->alert(print_r($e->getMessage(), TRUE));
      return FALSE;
    }
  }

  /**
   * Get list of files.
   *
   * @param int $content_id
   *   ID of content, we want to fetch files for.
   *
   * @return array
   *   Array with accounts.
   */
  public function getFiles($content_id) {
    $accounts = [];

    try {
      $response = $this->client->get('/items/' . $content_id . '/files');

      if ($response->getStatusCode() === 200) {
        $data = json_decode($response->getBody());
        $accounts = $data->data;
      }
      else {
        drupal_set_message(t("User with provided credentials wasn't found."), 'error');
      }
    }
    catch (\Exception $e) {
      drupal_set_message($e->getMessage(), 'error');
      \Drupal::logger('gathercontent')->error($e->getMessage(), []);
    }

    return $accounts;
  }

}
