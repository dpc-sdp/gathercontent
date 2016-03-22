<?php

/**
 * @file
 * Contains GatherContent\Accont class.
 */

namespace GatherContent;


use GuzzleHttp\Client;
use GuzzleHttp\Message\Request;

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
      $username = variable_get('gc_username', '');
    }

    if (is_null($api_key)) {
      $api_key = variable_get('gc_api_key', '');
    }

    if (empty($username || $api_key)) {
      watchdog('gathercontent', "Trying to call API without credentials.", array(), WATCHDOG_ERROR);
    }

    $this->client = new Client(
      array(
        'base_url' => 'https://api.gathercontent.com',
        'defaults' => array(
          'auth' => array(
            $username,
            $api_key,
          ),
          'headers' => array(
            'Accept' => 'application/vnd.gathercontent.v0.5+json',
          ),
        ),
      )
    );

  }

  /**
   * Get list of content.
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
      watchdog('gathercontent', $e->getMessage(), array(), WATCHDOG_ERROR);
    }

    return $accounts;
  }

  /**
   * Get single piece of content.
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
      watchdog('gathercontent', $e->getMessage(), array(), WATCHDOG_ERROR);
    }

    return $accounts;
  }

  /**
   * Get list of files.
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
      watchdog('gathercontent', $e->getMessage(), array(), WATCHDOG_ERROR);
    }

    return $accounts;
  }
}
