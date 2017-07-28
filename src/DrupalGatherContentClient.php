<?php

namespace Drupal\gathercontent;

use Cheppers\GatherContent\GatherContentClient;
use GuzzleHttp\ClientInterface;

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
    $this->setEmail($config->get('gathercontent_username'));
    $this->setApiKey($config->get('gathercontent_api_key'));
  }

  /**
   * Retrieve the account id of the given account.
   *
   * If none given, retrieve the first account by default.
   */
  public static function getAccountId(string $account_name = NULL) {
    $account = \Drupal::config('gathercontent.settings')
      ->get('gathercontent_account');
    $account = unserialize($account);

    if (!$account_name) {
      if (reset($account)) {
        return key($account);
      }
    }

    foreach ($account as $id => $name) {
      if ($name === $account_name) {
        return $id;
      }
    }

    return NULL;
  }

}
