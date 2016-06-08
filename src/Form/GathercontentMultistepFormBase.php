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
 * Class GathercontentMultistepFormBase.
 *
 * @package Drupal\gathercontent\Form
 */
abstract class GathercontentMultistepFormBase extends FormBase {


  /**
   * Drupal\Core\Entity\EntityManager definition.
   *
   * @var EntityManager
   */
  protected $entity_manager;

  /**
   * Drupal\Core\Datetime\DateFormatter definition.
   *
   * @var DateFormatter
   */
  protected $date_formatter;

  /**
   * @var \Drupal\user\PrivateTempStoreFactory
   */
  protected $tempStoreFactory;

  /**
   * @var \Drupal\Core\Entity\Query\QueryFactory
   */
  protected $entityQuery;

  /**
   * @var \Drupal\user\PrivateTempStore
   */
  protected $store;

  public function __construct(
    EntityManagerInterface $entity_manager,
    DateFormatterInterface $date_formatter,
    PrivateTempStoreFactory $temp_store_factory,
    QueryFactory $entityQuery
  ) {
    $this->entity_manager = $entity_manager;
    $this->date_formatter = $date_formatter;
    $this->tempStoreFactory = $temp_store_factory;
    $this->entityQuery = $entityQuery;
    $this->store = $this->tempStoreFactory->get('gathercontent_multistep_data');
  }

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
    $form = array();
    $form['actions']['#type'] = 'actions';
    $form['actions']['submit'] = array(
      '#type' => 'submit',
      '#value' => $this->t('Submit'),
      '#button_type' => 'primary',
      '#weight' => 10,
    );

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {

  }

  /**
   * Saves the data from the multistep form.
   */
  protected function saveData() {
    // Logic for saving data goes here...
    $this->deleteStore([]);
    drupal_set_message($this->t('The form has been saved.'));

  }

  /**
   * Helper method that removes all the keys from the store collection used for
   * the multistep form.
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
