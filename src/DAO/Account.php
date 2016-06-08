<?php

/**
 * @file
 * Contains GatherContent\Accont class.
 */

namespace Drupal\gathercontent\DAO;

use GuzzleHttp\Client;

/**
 * Class Account.
 *
 * @package GatherContent
 */
class Account {

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

    if (empty($username || $api_key)) {
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
   * Get list of accounts.
   *
   * @return array
   *   Array with accounts.
   */
  public function getAccounts() {
    $accounts = array();

    try {
      $response = $this->client->get('/accounts');

      if ($response->getStatusCode() === 200) {
        $data = json_decode($response->getBody());
        $accounts = $data->data;
      }
      else {
        drupal_set_message(t("User with provided credentials wasn't found."), 'error');
        $accounts = NULL;
      }
    }
    catch (\Exception $e) {
      \Drupal::logger('gathercontent')->error($e->getMessage(), array());
      drupal_set_message(t("User with provided credentials wasn't found. 1"), 'error');
      $accounts = NULL;
    }

    return $accounts;
  }

  /**
   * Test connection with current credentials.
   *
   * @return bool
   *   Return TRUE on success connection otherwise FALSE.
   */
  public function testConnection() {
    try {
      $response = $this->client->get('/me');

      if ($response->getStatusCode() === 200) {
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

}
