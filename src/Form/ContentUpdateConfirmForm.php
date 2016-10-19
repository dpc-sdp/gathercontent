<?php

namespace Drupal\gathercontent\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\gathercontent\Entity\Operation;
use Drupal\node\Entity\Node;

/**
 * Provides a node deletion confirmation form.
 */
class ContentUpdateConfirmForm extends ContentConfirmForm {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'node_update_from_gc_confirm';
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return $this->formatPlural(count($this->nodeIds), 'Confirm update selection (@count item)', 'Confirm update selection (@count items)');
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl() {
    return new Url('gathercontent.update_select_form');
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription() {
    return $this->t('Please review your selection before updating.');
  }

  /**
   * {@inheritdoc}
   */
  public function getConfirmText() {
    return $this->t('Update');
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildForm($form, $form_state);

    $import_config = $this->configFactory()->get('gathercontent.import');

    $form['node_update_method'] = [
      '#type' => 'radios',
      '#required' => TRUE,
      '#title' => $this->t('Content update method'),
      '#default_value' => $import_config->get('node_update_method'),
      '#options' => [
        'always_create' => $this->t('Always create new Content'),
        'update_if_not_changed' => $this->t('Create new Content if it has changed since the last import'),
        'always_update' => $this->t('Always update existing Content'),
      ],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    if ($form_state->getValue('confirm') && !empty($this->nodeIds)) {
      $operation = Operation::create(array(
        'type' => 'update',
      ));
      $operation->save();

      $nodes = Node::loadMultiple($this->nodeIds);
      $operations = [];
      foreach ($nodes as $node) {
        $gc_id = $node->gc_id->value;
        $operations[] = array(
          'gathercontent_update_process',
          array(
            $gc_id,
            $operation->uuid(),
            $form_state->getValue('node_update_method'),
          ),
        );
      }

      $batch = array(
        'title' => t('Updating content ...'),
        'operations' => $operations,
        'finished' => 'gathercontent_update_finished',
        'init_message' => t('Update is starting ...'),
        'progress_message' => t('Processed @current out of @total.'),
        'error_message' => t('An error occurred during processing'),
      );

      $this->tempStore->delete('nodes');
      batch_set($batch);
    }
  }

}
