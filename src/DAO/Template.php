<?php

namespace Drupal\gathercontent\DAO;

use GuzzleHttp\Client;

/**
 * Class Template.
 *
 * @package GatherContent
 */
class Template {
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
   * Fetch projects from GatherContent.
   *
   * @return array
   *   Associative array of projects.
   */
  public function getTemplates($project_id) {
    $templates = [];

    try {
      $response = $this->client->get('/templates?project_id=' . $project_id);
      if ($response->getStatusCode() === 200) {
        $data = json_decode($response->getBody());
        foreach ($data->data as $template) {
          $templates[$template->id] = $template->name;
        }
      }
    }
    catch (\Exception $e) {
      drupal_set_message($e->getMessage(), 'error');
      \Drupal::logger('gathercontent')->error($e->getMessage(), []);
    }

    return $templates;
  }

  /**
   * Fetch projects from GatherContent.
   *
   * @return array
   *   Associative array of projects.
   */
  public function getTemplatesObject($project_id) {
    $templates = [];

    try {
      $response = $this->client->get('/templates?project_id=' . $project_id);
      if ($response->getStatusCode() === 200) {
        $data = json_decode($response->getBody());
        foreach ($data->data as $template) {
          $templates[$template->id] = $template;
        }
      }
    }
    catch (\Exception $e) {
      drupal_set_message($e->getMessage(), 'error');
      \Drupal::logger('gathercontent')->error($e->getMessage(), []);
    }

    return $templates;
  }

  /**
   * TBD.
   *
   * @param int $template_id
   *   ID of template.
   *
   * @return object
   *   Object of template
   */
  public function getTemplate($template_id) {
    $template = NULL;
    try {
      $response = $this->client->get('/templates/' . $template_id);
      if ($response->getStatusCode() === 200) {
        $data = json_decode($response->getBody());
        $template = $data->data;
      }
    }
    catch (\Exception $e) {
      drupal_set_message($e->getMessage(), 'error');
      \Drupal::logger('gathercontent')->error($e->getMessage(), []);
    }

    return $template;
  }

}
