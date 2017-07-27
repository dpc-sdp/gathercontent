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

}
