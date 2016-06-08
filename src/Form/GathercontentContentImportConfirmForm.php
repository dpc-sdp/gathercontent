<?php

namespace Drupal\gathercontent\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\gathercontent\DAO\Content;
use Drupal\gathercontent\DAO\Template;
use Drupal\gathercontent\DAO\Project;
use Drupal\gathercontent\Entity\GathercontentOperation;

/**
 * Class GathercontentContentImportConfirmForm.
 *
 * @package Drupal\gathercontent\Form
 */
class GathercontentContentImportConfirmForm extends GathercontentMultistepFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'gathercontent_content_confirm_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildForm($form, $form_state);

    $nodes = $this->store->get('nodes');

    $form['title'] = array(
      'form_title' => array(
        '#type' => 'html_tag',
        '#tag' => 'h2',
        '#value' => \Drupal::translation()->formatPlural(count($nodes),
          'Confirm import selection (@count item)',
          'Confirm import selection (@count items)'
        ),
      ),
      'form_description' => array(
        '#type' => 'html_tag',
        '#tag' => 'p',
        '#value' => t('Please review your import selection before importing.'),
      ),
    );

    $header = array(
      'status' => t('Status'),
      'title' => t('Item name'),
      'template' => t('GatherContent Template'),
    );

    $options = array();

    $tmp_obj = new Template();
    $templates = $tmp_obj->getTemplates($this->store->get('project_id'));

    foreach ($nodes as $node) {
      $content_obj = new Content();
      $content = $content_obj->getContent($node);

      $options[$content->id] = array(
        'status' => '<div style="width:20px; height: 20px; float: left; margin-right: 5px; background: ' . $content->status->data->color . ';"></div>' . $content->status->data->name,
        'title' => $content->name,
        'template' => $templates[$content->template_id],
      );
    }

    $form['table'] = array(
      '#type' => 'table',
      '#header' => $header,
      '#rows' => $options,
    );

    $options = array();
    $project_obj = new Project();
    $statuses = $project_obj->getStatuses($this->store->get('project_id'));
    foreach ($statuses as $status) {
      $options[$status->id] = $status->name;
    }

    $form['status'] = array(
      '#type' => 'select',
      '#options' => $options,
      '#title' => t('After successful import change status to:'),
      '#empty_option' => t("- Don't change status -"),
    );

    $form['actions']['submit']['#value'] = $this->t('Import');
    $form['actions']['back'] = array(
      '#type' => 'submit',
      '#value' => $this->t('Back'),
    );

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    if ($form_state->getTriggeringElement()['#id'] === 'edit-submit') {
      $operation = GathercontentOperation::create(array(
        'type' => 'import',
      ));
      $operation->save();

      $operations = array();

      $stack = array();
      $import_content = $this->store->get('nodes');

      foreach ($import_content as $k => $value) {
        if ($this->store->get('menu')[$value] && $this->store->get('menu')[$value] != -1) {
          $parent_menu_item = $this->store->get('menu')[$value] ? $this->store->get('menu')[$value] : NULL;
          $operations[] = array(
            'gc_import_process',
            array(
              $value,
              $form_state->getValue('status'),
              $operation->uuid(),
              $parent_menu_item
            ),
          );
          $stack[] = $value;
          unset($import_content[$k]);
        }
      }

      if (!empty($import_content)) {
        // Load all by project_id.
        $first = reset($import_content);
        $content_obj = new Content();
        $content = $content_obj->getContent($first);

        $contents_source = $content_obj->getContents($content->project_id);
        $content = array();

        foreach ($contents_source as $value) {
          $content[$value->id] = $value;
        }

        while (!empty($import_content)) {
          $current = reset($import_content);
          if (isset($stack[$content[$current]->parent_id])) {
            $parent_menu_item = 'node:' . $content[$current]->parent_id;
            $operations[] = array(
              'gathercontent_import_process',
              array(
                $current,
                $form_state->getValue('status'),
                $operation->uuid(),
                $parent_menu_item,
              ),
            );
            $stack[] = $current;
            array_shift($import_content);
          }
          else {
            array_shift($import_content);
            array_push($import_content, $current);
          }
        }
      }

      $batch = array(
        'title' => t('Importing content ...'),
        'operations' => $operations,
        'finished' => 'gathercontent_import_finished',
        'file' => drupal_get_path('module', 'gathercontent') . '/gathercontent.module',
        'init_message' => t('Import is starting ...'),
        'progress_message' => t('Processed @current out of @total.'),
        'error_message' => t('An error occurred during processing'),
      );

      batch_set($batch);
    }
    else {
      
    }
  }

}
