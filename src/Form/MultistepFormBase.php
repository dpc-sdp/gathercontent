<?php

namespace Drupal\gathercontent\Form;

use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityManagerInterface;
use Drupal\Core\Entity\Query\QueryFactory;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\user\PrivateTempStoreFactory;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class MultistepFormBase.
 *
 * @package Drupal\gathercontent\Form
 */
abstract class MultistepFormBase extends FormBase {


  /**
   * Drupal\Core\Entity\EntityManager definition.
   *
   * @var \Drupal\Core\Entity\EntityManager
   */
  protected $entityManager;

  /**
   * Drupal\Core\Datetime\DateFormatter definition.
   *
   * @var \Drupal\Core\Datetime\DateFormatter
   */
  protected $dateFormatter;

  /**
   * Drupal\user\PrivateTempStoreFactory definition.
   *
   * @var \Drupal\user\PrivateTempStoreFactory
   */
  protected $tempStoreFactory;

  /**
   * Drupal\Core\Entity\Query\QueryFactory definition.
   *
   * @var \Drupal\Core\Entity\Query\QueryFactory
   */
  protected $entityQuery;

  /**
   * Drupal\user\PrivateTempStore definition.
   *
   * @var \Drupal\user\PrivateTempStore
   */
  protected $store;

  /**
   * Constructor for class.
   *
   * @param \Drupal\Core\Entity\EntityManagerInterface $entity_manager
   *   EntityManagerInterface object.
   * @param \Drupal\Core\Datetime\DateFormatterInterface $date_formatter
   *   DateFormatterInterface object.
   * @param \Drupal\user\PrivateTempStoreFactory $temp_store_factory
   *   PrivateTempStoreFactory object.
   * @param \Drupal\Core\Entity\Query\QueryFactory $entityQuery
   *   QueryFactory object.
   */
  public function __construct(
    EntityManagerInterface $entity_manager,
    DateFormatterInterface $date_formatter,
    PrivateTempStoreFactory $temp_store_factory,
    QueryFactory $entityQuery
  ) {
    $this->entityManager = $entity_manager;
    $this->dateFormatter = $date_formatter;
    $this->tempStoreFactory = $temp_store_factory;
    $this->entityQuery = $entityQuery;
    $this->store = $this->tempStoreFactory->get('gathercontent_multistep_data');
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity.manager'),
      $container->get('date.formatter'),
      $container->get('user.private_tempstore'),
      $container->get('entity.query')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = [];
    $form['actions']['#type'] = 'actions';
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Submit'),
      '#button_type' => 'primary',
      '#weight' => 10,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
  }

  /**
   * Helper removing all keys from the store collection used for multistep form.
   *
   * @param array $keys
   *   Array of keys to delete.
   */
  protected function deleteStore(array $keys) {
    foreach ($keys as $key) {
      $this->store->delete($key);
    }
  }

}
