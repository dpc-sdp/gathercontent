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
    $form = parent::buildForm($form, $form_state);

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
      '#attached' => array(
        'library' => array(
          'gathercontent/filter',
        ),
      ),
    );

    $form['menu'] = array(
      '#type' => 'value',
    );

    if (($form_state->hasValue('project') || $this->store->get('project_id'))
      && (!empty($form_state->getValue('project')) || !empty($this->store->get('project_id')))) {
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

      // @TODO: use custom form element
      $form['import']['content'] = array(
        '#type' => 'tableselect',
        '#header' => $header,
        '#options' => $content_table,
        '#empty' => t('No content available.'),
//        '#filterwrapper' => array(
//          'filter_wrapper' => array('gc-table--filter-wrapper', 'clearfix'),
//          'counter_wrapper' => array('gc-table--counter', 'clearfix'),
//        ),
//        '#filterdescription' => t('You can only see items with mapped templates in the table.'),
        '#default_value' => $this->store->get('nodes') ? $this->store->get('nodes') : [],
      );

      // @FIXME
// l() expects a Url object, created from a route name or external URI.
// $form['import']['back_button'] = array(
//       '#type' => 'markup',
//       '#markup' => l(t('Back'), '/admin/config/gc/import', array('attributes' => array('class' => array('button')))),
//     );

    }

    $form['actions']['submit']['#value'] = $this->t('Next');

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->store->set('project_id', $form_state->getValue('project'));
    $this->store->set('nodes', array_filter($form_state->getValue('content')));
    $this->store->set('menu', array_intersect_key(array_filter($form_state->getValue('menu')), array_filter($form_state->getValue('content'))));
    $form_state->setRedirect('gathercontent.import_confirm_form');
  }

  public function getContentTable(array &$form, FormStateInterface $form_state) {
    $form_state->setRebuild(TRUE);
    return $form['import'];
  }

}
