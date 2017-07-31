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

  /**
   * Retrieve all the active projects.
   */
  public function getActiveProjects(int $account_id) {
    $projects = $this->projectsGet($account_id);

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
  public function getTemplatesOptionArray(int $project_id) {
    $formatted = [];
    $templates = $this->templatesGet($project_id);

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
  public function getBody(bool $json_decoded = FALSE) {
    $body = $this->getResponse()->getBody();

    if ($json_decoded) {
      return \GuzzleHttp\json_decode($body);
    }

    return $body;
  }

}
