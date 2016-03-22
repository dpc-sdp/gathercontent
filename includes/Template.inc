<?php

namespace GatherContent;

use GuzzleHttp\Client;
use GuzzleHttp\Message\Request;

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
   * Fetch projects from GatherContent.
   *
   * @return array
   *   Associative array of projects.
   */
  public function getTemplates($project_id) {
    $templates = array();

    $request = new Request('GET', '/templates?project_id=' . $project_id);
    try {
      $response = $this->client->send($request);
      if ($response->getStatusCode() === 200) {
        $data = json_decode($response->getBody());
        foreach ($data->data as $template) {
          $templates[$template->id] = $template->name;
        }
      }
    }
    catch (\Exception $e) {
      drupal_set_message($e->getMessage(), 'error');
      watchdog('gathercontent', $e->getMessage(), array(), WATCHDOG_ERROR);
    }

    return $templates;
  }

  /**
   * TBD.
   *
   * @param $template_id
   * @return null
   */
  public function getTemplate($template_id) {
    $template = NULL;
    $request = new Request('GET', '/templates/' . $template_id);
    try {
      $response = $this->client->send($request);
      if ($response->getStatusCode() === 200) {
        $data = json_decode($response->getBody());
        $template = $data->data;
      }
    }
    catch (\Exception $e) {
      drupal_set_message($e->getMessage(), 'error');
      watchdog('gathercontent', $e->getMessage(), array(), WATCHDOG_ERROR);
    }

    return $template;
  }

}
