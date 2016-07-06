<?php

namespace Drupal\gathercontent\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\gathercontent\DAO\Account;

/**
 * Class MappingController.
 *
 * @package Drupal\gathercontent\Controller
 */
class MappingController extends ControllerBase {

  /**
   * Page callback for connection testing page.
   *
   * @return string
   *   Content of the page.
   */
  public function testConnectionPage() {
    $account = new Account();
    $success = $account->testConnection();

    if ($success === TRUE) {
      $message = $this->t('Connection successful.');
    }
    else {
      $message = $this->t("Connection wasn't successful.");
    }

    return [
      '#type' => 'markup',
      '#markup' => $message,
    ];
  }

}
