<?php

namespace Drupal\gathercontent\Form;

use Drupal\Core\Form\FormStateInterface;

/**
 * Class ContentUpdateSelectForm.
 *
 * @package Drupal\gathercontent\Form
 */
class ContentUploadSelectForm extends ContentSelectForm {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'gathercontent_content_upload_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->store->set('nodes', array_filter($form_state->getValue('nodes')));
    $form_state->setRedirect('gathercontent.upload_confirm_form');
  }

}
