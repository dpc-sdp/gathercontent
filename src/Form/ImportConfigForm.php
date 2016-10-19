<?php

namespace Drupal\gathercontent\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Class ImportConfigForm.
 *
 * @package Drupal\gathercontent\Form
 */
class ImportConfigForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'gathercontent.import',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'gathercontent_import_config_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('gathercontent.import');

    $form['node_update_method'] = [
      '#type' => 'radios',
      '#required' => TRUE,
      '#title' => $this->t('Content update method'),
      '#default_value' => $config->get('node_update_method'),
      '#options' => [
        'always_create' => $this->t('Always create new Content'),
        'update_if_not_changed' => $this->t('Create new Content if it has changed since the last import'),
        'always_update' => $this->t('Always update existing Content'),
      ],
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this
      ->config('gathercontent.import')
      ->set('node_update_method', $form_state->getValue('node_update_method'))
      ->save();

    parent::submitForm($form, $form_state);
  }

}
