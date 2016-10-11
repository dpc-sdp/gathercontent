<?php

namespace Drupal\gathercontent\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\gathercontent\DAO\Content;
use Drupal\gathercontent\DAO\Project;
use Drupal\gathercontent\Entity\Mapping;
use Drupal\gathercontent\Entity\Operation;

/**
 * Class ContentImportSelectForm.
 *
 * @package Drupal\gathercontent\Form
 */
class ContentImportSelectForm extends FormBase {

  protected $step;

  protected $projectId;

  protected $nodes;

  protected $menu;

  protected $items;

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

    if (empty($this->step)) {
      $this->step = 1;
    }

    // Step 1 - select project + select nodes.
    // Step 2 - confirm screen.
    if ($this->step === 1) {
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
        '#default_value' => !empty($this->projectId) ? $this->projectId : 0,
        '#description' => t('You can only see projects with mapped templates in the dropdown.'),
      );

      $form['import'] = array(
        '#prefix' => '<div id="edit-import">',
        '#suffix' => '</div>',
      );

      $form['menu'] = array(
        '#type' => 'value',
      );

      if (($form_state->hasValue('project') || !empty($this->projectId))
        && (!empty($form_state->getValue('project')))
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

        $project_id = $form_state->hasValue('project') ? $form_state->getValue('project') : $this->projectId;
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
            $this->items[$item->id] = [
              'color' => $item->status->data->color,
              'label' => $item->status->data->name,
              'template' => $mapping_array[$item->template_id]['gc_template'],
              'title' => $item->name,
            ];
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
                    ) + \Drupal::service('menu.parent_form_selector')->getParentSelectOptions(),
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
          '#default_value' => !empty($this->nodes) ? $this->nodes : [],
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
    }
    elseif ($this->step === 2) {
      $form['title'] = array(
        'form_title' => array(
          '#type' => 'html_tag',
          '#tag' => 'h2',
          '#value' => \Drupal::translation()->formatPlural(count($this->nodes),
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
      foreach ($this->nodes as $node) {
        $options[$node] = array(
          'status' => array(
            'data' => array(
              'color' => array(
                '#type' => 'html_tag',
                '#tag' => 'div',
                '#value' => ' ',
                '#attributes' => array(
                  'style' => 'width:20px; height: 20px; float: left; margin-right: 5px; background: ' . $this->items[$node]['color'],
                ),
              ),
              'label' => array(
                '#plain_text' => $this->items[$node]['label'],
              ),
            ),
          ),
          'title' => $this->items[$node]['title'],
          'template' => $this->items[$node]['template'],
        );
      }

      $form['table'] = array(
        '#type' => 'table',
        '#header' => $header,
        '#rows' => $options,
      );

      $options = array();
      $project_obj = new Project();
      $statuses = $project_obj->getStatuses($this->projectId);
      foreach ($statuses as $status) {
        $options[$status->id] = $status->name;
      }

      $form['status'] = array(
        '#type' => 'select',
        '#options' => $options,
        '#title' => t('After successful import change status to:'),
        '#empty_option' => t("- Don't change status -"),
      );

      $form['actions']['#type'] = 'actions';
      $form['actions']['submit'] = array(
        '#type' => 'submit',
        '#value' => $this->t('Import'),
        '#button_type' => 'primary',
        '#weight' => 10,
      );
      $form['actions']['back'] = array(
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
      if ($this->step === 1) {
        $this->projectId = $form_state->getValue('project');
        $this->nodes = array_filter($form_state->getValue('content'));
        $this->menu = array_intersect_key(array_filter($form_state->getValue('menu')), array_filter($form_state->getValue('content')));
        $this->step = 2;
        $form_state->setRebuild(TRUE);
      }
      elseif ($this->step === 2) {
        $operation = Operation::create(array(
          'type' => 'import',
        ));
        $operation->save();

        $operations = array();
        $stack = array();
        $import_content = $this->nodes;
        foreach ($import_content as $k => $value) {
          if ((isset($this->menu[$value]) && $this->menu[$value] != -1) || !isset($this->menu[$value])) {
            $parent_menu_item = isset($this->menu[$value]) ? $this->menu[$value] : NULL;
            $operations[] = array(
              'gathercontent_import_process',
              array(
                $value,
                $form_state->getValue('status'),
                $operation->uuid(),
                $parent_menu_item,
              ),
            );
            $stack[] = $value;
            unset($import_content[$k]);
          }
        }

        if (!empty($import_content)) {
          // Load all by project_id.
          $content_obj = new Content();
          $contents_source = $content_obj->getContents($this->projectId);
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
    }
    elseif ($form_state->getTriggeringElement()['#id'] === 'edit-back') {
      if ($this->step === 1) {
        return $form_state->setRedirect('gathercontent.import_select_form');
      }
      $this->step = 1;
      $form_state->setRebuild(TRUE);
    }
  }

  /**
   * Ajax callback for project dropdown.
   *
   * {@inheritdoc}
   */
  public function getContentTable(array &$form, FormStateInterface $form_state) {
    $form_state->setRebuild(TRUE);
    return $form['import'];
  }

}
