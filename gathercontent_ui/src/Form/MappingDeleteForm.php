<?php

namespace Drupal\gathercontent_ui\Form;

use Drupal\Core\Entity\EntityConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

/**
 * Builds the form to delete Mapping entities.
 */
class MappingDeleteForm extends EntityConfirmFormBase {

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return $this->t('Are you sure you want to delete %name?',
      [
        '%name' => $this->entity->get('gathercontent_template'),
      ],
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl() {
    return new Url('entity.gathercontent_mapping.collection');
  }

  /**
   * {@inheritdoc}
   */
  public function getConfirmText() {
    return $this->t('Delete');
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->entity->delete();

    $this->messenger()->addStatus(
      $this->t('Mapping %label has been deleted.',
        [
          '%label' => $this->entity->get('gathercontent_template'),
        ],
      )
    );

    $form_state->setRedirectUrl($this->getCancelUrl());
  }

}
