<?php

namespace Drupal\gathercontent\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\gathercontent\DAO\Content;
use Drupal\gathercontent\Entity\Mapping;

/**
 * Class ContentImportSelectForm.
 *
 * @package Drupal\gathercontent\Form
 */
class ContentImportSelectForm extends MultistepFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'gathercontent_content_import_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = [];

    $created_mapping_ids = Mapping::loadMultiple();
    $projects = array();
    $mapping_array = array();
    foreach ($created_mapping_ids as $mapping) {
      /** @var Mapping $mapping */
      if ($mapping->hasMapping()) {
        $projects[$mapping->getGathercontentProjectId()] = $mapping->getGathercontentProject();
        $mapping_array[$mapping->getGathercontentTemplateId()] = array(
          'gc_template' => $mapping->getGathercontentTemplate(),
          'ct' => $mapping->getContentTypeName(),
        );
      }
    }

    $form['project'] = array(
      '#type' => 'select',
      '#title' => t('Select project'),
      '#options' => $projects,
      '#empty_option' => t('- Select -'),
      '#required' => TRUE,
      '#ajax' => array(
        'callback' => '::getContentTable',
        'wrapper' => 'edit-import',
        'method' => 'replace',
        'effect' => 'fade',
      ),
      '#default_value' => $this->store->get('project_id') ? $this->store->get('project_id') : 0,
      '#description' => t('You can only see projects with mapped templates in the dropdown.'),
    );

    $form['import'] = array(
      '#prefix' => '<div id="edit-import">',
      '#suffix' => '</div>',
    );

    $form['menu'] = array(
      '#type' => 'value',
    );

    if (($form_state->hasValue('project') || $this->store->get('project_id'))
      && (!empty($form_state->getValue('project')) || !empty($this->store->get('project_id')))
    ) {

      $form['import']['filter'] = [
        '#type' => 'markup',
        '#markup' => '<div class="gc-table--filter-wrapper clearfix"></div>',
        '#weight' => 0,
      ];

      $form['import']['counter'] = [
        '#type' => 'markup',
        '#markup' => '<div class="gc-table--counter"></div>',
        '#weight' => 1,
      ];

      $project_id = $form_state->hasValue('project') ? $form_state->getValue('project') : $this->store->get('project_id');
      $content_obj = new Content();
      $content = $content_obj->getContents($project_id);
      $content_table = array();

      foreach ($content as $item) {
        // If template is not empty, we have mapped template and item
        // isn't synced yet.
        if (!is_null($item->template_id)
          && $item->template_id != 'null'
          && isset($mapping_array[$item->template_id])
        ) {
          $content_table[$item->id] = array(
            'status' => array(
              'data' => array(
                'color' => array(
                  '#type' => 'html_tag',
                  '#tag' => 'div',
                  '#value' => ' ',
                  '#attributes' => array(
                    'style' => 'width:20px; height: 20px; float: left; margin-right: 5px; background: ' . $item->status->data->color,
                  ),
                ),
                'label' => array(
                  '#plain_text' => $item->status->data->name,
                ),
              ),
              'class' => array('gc-item', 'status-item'),
            ),
            'title' => array(
              'data' => $item->name,
              'class' => array('gc-item', 'gc-item--name'),
            ),
            'updated' => array(
              'data' => date('F d, Y - H:i', strtotime($item->updated_at->date)),
              'class' => array('gc-item', 'gc-item-date'),
              'data-date' => date('Y-m-d.H:i:s', strtotime($item->updated_at->date)),
            ),
            'template' => array(
              'data' => $mapping_array[$item->template_id]['gc_template'],
              'class' => array('template-name-item'),
            ),
            'menu' => array(
              'data' => array(
                '#type' => 'select',
                '#options' =>
                  array(
                    0 => t("- Don't create menu item -"),
                    -1 => t("Parent being imported"),
                  ) + \Drupal::service('menu.parent_form_selector')
                    ->getParentSelectOptions(),
                '#title' => t('Menu'),
                '#title_display' => 'invisible',
                '#name' => "menu[$item->id]",
              ),
            ),
          );

        }
      }

      $header = array(
        'status' => t('Status'),
        'title' => t('Item Name'),
        'updated' => t('Last updated in GatherContent'),
        'template' => t('GatherContent Template Name'),
        'menu' => t('Menu'),
      );

      $form['import']['content'] = array(
        '#type' => 'tableselect',
        '#weight' => 2,
        '#attributes' => [
          'class' => [
            'tablesorter-enabled',
          ],
        ],
        '#attached' => [
          'library' => [
            'gathercontent/tablesorter-mottie',
            'gathercontent/filter',
          ],
          'drupalSettings' => [
            'gatherContent' => [
              'tableSorterOptionOverrides' => [
                'headers' => [
                  '0' => [
                    'sorter' => FALSE,
                  ],
                  '5' => [
                    'sorter' => FALSE,
                  ],
                ],
              ],
            ],
          ],
        ],
        '#header' => $header,
        '#options' => $content_table,
        '#empty' => $this->t('No content available.'),
        '#default_value' => $this->store->get('nodes') ? $this->store->get('nodes') : [],
      );

      $form['import']['actions']['#type'] = 'actions';
      $form['import']['actions']['submit'] = array(
        '#type' => 'submit',
        '#value' => $this->t('Next'),
        '#button_type' => 'primary',
        '#weight' => 10,
      );
      $form['import']['actions']['back'] = array(
        '#type' => 'submit',
        '#value' => $this->t('Back'),
        '#weight' => 11,
      );
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    if ($form_state->getTriggeringElement()['#id'] === 'edit-submit') {
      $this->store->set('project_id', $form_state->getValue('project'));
      $this->store->set('nodes', array_filter($form_state->getValue('content')));
      $this->store->set('menu', array_intersect_key(array_filter($form_state->getValue('menu')), array_filter($form_state->getValue('content'))));
      $form_state->setRedirect('gathercontent.import_confirm_form');
    }
    elseif ($form_state->getTriggeringElement()['#id'] === 'edit-back') {
      $form_state->setValue('project', NULL);
      $form_state->setValue('content', NULL);
      $form_state->setValue('menu', NULL);
      $this->deleteStore(array('project_id', 'nodes', 'menu'));
      $form_state->setRebuild(TRUE);
    }
  }

  public function getContentTable(array &$form, FormStateInterface $form_state) {
    $form_state->setRebuild(TRUE);

    return $form['import'];
  }

}
