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
        ->error("Trying to call API without credentials.", array());
    }

    $this->client = new Client(
      array(
        'base_uri' => 'https://api.gathercontent.com',
        'auth' => array(
          $username,
          $api_key,
        ),
        'headers' => array(
          'Accept' => 'application/vnd.gathercontent.v0.5+json',
        ),
      )
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
      $response = $this->client->post('/items/' . $content_id . '/choose_status', array(
        'form_params' => array(
          'status_id' => $status_id,
        ),
      ));
      if ($response->getStatusCode() === 202) {
        return TRUE;
      }
      else {
        return FALSE;
      }
    }
    catch (\Exception $e) {
      drupal_set_message($e->getMessage(), 'error');
      \Drupal::logger('gathercontent')->error($e->getMessage(), array());
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
   *   Array with accounts.
   */
  public function getContents($project_id) {
    $accounts = array();

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
      \Drupal::logger('gathercontent')->error($e->getMessage(), array());
    }
    
    return $accounts;
  }

  /**
   * Get single piece of content.
   *
   * @param int $content_id
   *   ID of content, we want to fetch.
   *
   * @return array
   *   Array with accounts.
   */
  public function getContent($content_id) {
    $accounts = array();

    try {
      $response = $this->client->get('/items/' . $content_id);

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
      \Drupal::logger('gathercontent')->error($e->getMessage(), array());
    }

    return $accounts;
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
      $response = $this->client->post('/items/' . $content_id . '/save', array('body' => array('config' => base64_encode(json_encode($config)))));
      if ($response->getStatusCode() === 202) {
        return TRUE;
      }
      else {
        return FALSE;
      }
    }
    catch (\Exception $e) {
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
    $accounts = array();

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
      \Drupal::logger('gathercontent')->error($e->getMessage(), array());
    }

    return $accounts;
  }

}
