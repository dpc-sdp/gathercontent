<?php

namespace Drupal\gathercontent\Controller;

use Cheppers\GatherContent\GatherContentClient;
use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class MappingController.
 *
 * @package Drupal\gathercontent\Controller
 */
class MappingController extends ControllerBase {

  protected $client;

  /**
   * {@inheritdoc}
   */
  public function __construct(GatherContentClient $client) {
    $this->client = $client;
    $this->client
      ->setEmail(\Drupal::config('gathercontent.settings')->get('gathercontent_username'))
      ->setApiKey(\Drupal::config('gathercontent.settings')->get('gathercontent_api_key'));
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('gathercontent.client')
    );
  }

  /**
   * Page callback for connection testing page.
   *
   * @return array
   *   Content of the page.
   */
  public function testConnectionPage() {
    $message = $this->t('Connection successful.');

    try {
      $this->client->meGet();
    }
    catch (\Exception $e) {
      $message = $this->t("Connection wasn't successful.");
    }

    return [
      '#type' => 'markup',
      '#markup' => $message,
    ];
  }

}
