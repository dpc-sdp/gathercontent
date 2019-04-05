<?php

namespace Drupal\gathercontent_ui\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\gathercontent\Import\ImportOptions;
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
    return new Url('gathercontent_ui.update_select_form');
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

    $form['node_create_new_revision'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Create new revision'),
      '#default_value' => $import_config->get('node_create_new_revision'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    if ($form_state->getValue('confirm') && !empty($this->nodeIds)) {
      $nodes = Node::loadMultiple($this->nodeIds);
      $operations = [];
      foreach ($nodes as $node) {
        $update_options = new ImportOptions();
        $update_options->setCreateNewRevision($form_state->getValue('node_create_new_revision'));

        $operations[] = [
          'gathercontent_import_process',
          [
            $node->gc_id->value,
            $update_options,
          ],
        ];
      }

      $batch = [
        'title' => t('Updating content ...'),
        'operations' => $operations,
        'finished' => 'gathercontent_import_finished',
        'init_message' => t('Update is starting ...'),
        'progress_message' => t('Processed @current out of @total.'),
        'error_message' => t('An error occurred during processing'),
      ];

      $this->tempStore->delete('nodes');
      batch_set($batch);
    }
  }

}
