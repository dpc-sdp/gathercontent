<?php

namespace Drupal\gathercontent_ui\Form;

use Cheppers\GatherContent\GatherContentClientInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Menu\MenuParentFormSelectorInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\gathercontent\Entity\Mapping;
use Drupal\gathercontent\Import\ImportOptions;
use Drupal\gathercontent\MappingLoader;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class ContentImportSelectForm.
 *
 * @package Drupal\gathercontent\Form
 */
class ContentImportSelectForm extends FormBase {

  use StringTranslationTrait;

  /**
   * @var int
   */
  protected $step;

  /**
   * @var int|string
   */
  protected $projectId;

  /**
   * @var mixed|object
   */
  protected $nodes;

  /**
   * @var string
   */
  protected $menu;

  /**
   * @var array|string
   */
  protected $items;

  /**
   * @var mixed
   */
  protected $drupalStatus;

  /**
   * @var \Cheppers\GatherContent\GatherContentClientInterface
   */
  protected $client;

  /**
   * @var \Drupal\Core\Menu\MenuParentFormSelectorInterface
   */
  protected $menuParentFormSelector;

  /**
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * {@inheritdoc}
   */
  public function __construct(
    GatherContentClientInterface $client,
    MenuParentFormSelectorInterface $menuParentFormSelector,
    EntityTypeManagerInterface $entityTypeManager
  ) {
    $this->client = $client;
    $this->menuParentFormSelector = $menuParentFormSelector;
    $this->entityTypeManager = $entityTypeManager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('gathercontent.client'),
      $container->get('menu.parent_form_selector'),
      $container->get('entity_type.manager')
    );
  }

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
      $projects = [];
      $mapping_array = [];
      $content_types = [];
      $entity_types = [];
      foreach ($created_mapping_ids as $mapping) {
        /** @var \Drupal\gathercontent\Entity\Mapping $mapping */
        if ($mapping->hasMapping()) {
          if (!array_key_exists($mapping->getGathercontentTemplateId(), $content_types)) {
            $content_types[$mapping->getGathercontentTemplateId()] = $mapping->getContentType();
            $entity_types[$mapping->getGathercontentTemplateId()] = $mapping->getMappedEntityType();
          }
          $projects[$mapping->getGathercontentProjectId()] = $mapping->getGathercontentProject();
          $mapping_array[$mapping->getGathercontentTemplateId()] = [
            'gc_template' => $mapping->getGathercontentTemplate(),
            'ct' => $mapping->getContentTypeName(),
          ];
        }
      }

      $form['project'] = [
        '#type' => 'select',
        '#title' => $this->t('Select project'),
        '#options' => $projects,
        '#empty_option' => $this->t('- Select -'),
        '#required' => TRUE,
        '#ajax' => [
          'callback' => '::getContentTable',
          'wrapper' => 'edit-import',
          'method' => 'replace',
          'effect' => 'fade',
        ],
        '#default_value' => !empty($this->projectId) ? $this->projectId : 0,
        '#description' => $this->t('You can only see projects with mapped templates in the dropdown.'),
      ];

      $form['import'] = [
        '#prefix' => '<div id="edit-import">',
        '#suffix' => '</div>',
      ];

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
        $content = $this->client->itemsGet($project_id);
        $statuses = $this->client->projectStatusesGet($project_id);
        $import_config = $this->configFactory()->get('gathercontent.import');

        $form['import']['items'] = [
          '#tree' => TRUE,
          '#type' => 'table',
          '#header' => [
            'selected' => [
              'class' => ['select-all'],
              'data' => '',
            ],
            'status' => $this->t('Status'),
            'title' => $this->t('Item Name'),
            'updated' => $this->t('Last updated in GatherContent'),
            'template' => $this->t('GatherContent Template Name'),
            'drupal_status' => $this->t('Import published'),
            'menu' => $this->t('Menu'),
          ],
          '#empty' => $this->t('No content available.'),
          '#weight' => 2,
          '#attributes' => [
            'class' => [
              'tablesorter-enabled',
            ],
          ],
          '#attached' => [
            'library' => [
              'gathercontent_ui/tablesorter-mottie',
              'gathercontent_ui/filter',
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
        ];

        foreach ($content['data'] as $item) {
          // If template is not empty, we have mapped template and item
          // isn't synced yet.
          if (!is_null($item->templateId)
            && $item->templateId != 'null'
            && isset($mapping_array[$item->templateId])
          ) {
            if ($entity_types[$item->templateId] == 'node') {
              $node_type = $this->entityTypeManager->getStorage('node_type')->load($content_types[$item->templateId]);
              $selected_boxes = $node_type->getThirdPartySetting('menu_ui', 'available_menus', ['main']);
              $available_menus = [];
              foreach ($selected_boxes as $selected_box) {
                $available_menus[$selected_box] = $selected_box;
              }
            }

            $this->items[$item->id] = [
              'color' => $statuses['data'][$item->statusId]->color,
              'label' => $statuses['data'][$item->statusId]->name,
              'template' => $mapping_array[$item->templateId]['gc_template'],
              'title' => $item->name,
            ];
            $form['import']['items'][$item->id] = [
              '#tree' => TRUE,
              'selected' => [
                '#type' => 'checkbox',
                '#title' => $this->t('Selected'),
                '#title_display' => 'invisible',
                '#default_value' => !empty($this->nodes[$item->id]),
                '#attributes' => [
                  'class' => ['gathercontent-select-import-items'],
                ],
              ],
              'status' => [
                '#wrapper_attributes' => [
                  'class' => ['gc-item', 'status-item'],
                ],
                'color' => [
                  '#type' => 'html_tag',
                  '#tag' => 'div',
                  '#value' => ' ',
                  '#attributes' => [
                    'style' => 'width:20px; height: 20px; float: left; margin-right: 5px; background: ' . $statuses['data'][$item->statusId]->color,
                  ],
                ],
                'label' => [
                  '#plain_text' => $statuses['data'][$item->statusId]->name,
                ],
              ],
              'title' => [
                'data' => [
                  '#type' => 'markup',
                  '#markup' => $item->name,
                ],
                '#wrapper_attributes' => [
                  'class' => ['gc-item', 'gc-item--name'],
                ],
              ],
              'updated' => [
                'data' => [
                  '#markup' => date('F d, Y - H:i', strtotime($item->updatedAt)),
                ],
                '#wrapper_attributes' => [
                  'class' => ['gc-item', 'gc-item-date'],
                ],
                '#attributes' => [
                  'data-date' => date('Y-m-d.H:i:s', strtotime($item->updatedAt)),
                ],
              ],
              'template' => [
                'data' => [
                  '#markup' => $mapping_array[$item->templateId]['gc_template'],
                ],
                '#wrapper_attributes' => [
                  'class' => ['template-name-item'],
                ],
              ],
              'drupal_status' => [
                '#type' => 'checkbox',
                '#title' => $this->t('Publish'),
                '#title_display' => 'invisible',
                '#default_value' => isset($this->drupalStatus[$item->id]) ? $this->drupalStatus[$item->id] : $import_config->get('node_default_status'),
                '#states' => [
                  'disabled' => [
                    ':input[name="items[' . $item->id . '][selected]"]' => ['checked' => FALSE],
                  ],
                ],
              ],
              'menu' => [
                '#type' => 'markup',
                '#markup' => '',
              ],
            ];

            if ($entity_types[$item->templateId] == 'node') {
              $form['import']['items'][$item->id]['menu'] = [
                '#type' => 'select',
                '#default_value' => $node_type->getThirdPartySetting('menu_ui', 'parent'),
                '#empty_option' => $this->t("- Don't create menu item -"),
                '#empty_value' => 0,
                '#options' => [-1 => $this->t("Parent being imported")]
                + $this->menuParentFormSelector
                  ->getParentSelectOptions('', $available_menus),
                '#title' => $this->t('Menu'),
                '#title_display' => 'invisible',
                '#states' => [
                  'disabled' => [
                    ':input[name="items[' . $item->id . '][selected]"]' => ['checked' => FALSE],
                  ],
                ],
              ];
            }
          }
        }

        $form['import']['actions']['#type'] = 'actions';
        $form['import']['actions']['submit'] = [
          '#type' => 'submit',
          '#value' => $this->t('Next'),
          '#button_type' => 'primary',
          '#weight' => 10,
        ];

        $form['import']['actions']['back'] = [
          '#type' => 'submit',
          '#value' => $this->t('Back'),
          '#weight' => 11,
        ];
      }
    }
    elseif ($this->step === 2) {
      $form['title'] = [
        'form_title' => [
          '#type' => 'html_tag',
          '#tag' => 'h2',
          '#value' => $this->formatPlural(count($this->nodes),
            'Confirm import selection (@count item)',
            'Confirm import selection (@count items)'
          ),
        ],
        'form_description' => [
          '#type' => 'html_tag',
          '#tag' => 'p',
          '#value' => $this->t('Please review your import selection before importing.'),
        ],
      ];

      $header = [
        'status' => $this->t('Status'),
        'title' => $this->t('Item name'),
        'template' => $this->t('GatherContent Template'),
      ];

      $options = [];
      foreach ($this->nodes as $node) {
        $options[$node] = [
          'status' => [
            'data' => [
              'color' => [
                '#type' => 'html_tag',
                '#tag' => 'div',
                '#value' => ' ',
                '#attributes' => [
                  'style' => 'width:20px; height: 20px; float: left; margin-right: 5px; background: ' . $this->items[$node]['color'],
                ],
              ],
              'label' => [
                '#plain_text' => $this->items[$node]['label'],
              ],
            ],
          ],
          'title' => $this->items[$node]['title'],
          'template' => $this->items[$node]['template'],
        ];
      }

      $form['table'] = [
        '#type' => 'table',
        '#header' => $header,
        '#rows' => $options,
      ];

      $options = [];
      /** @var \Cheppers\GatherContent\DataTypes\Status[] $statuses */
      $statuses = $this->client->projectStatusesGet($this->projectId);

      foreach ($statuses['data'] as $status) {
        $options[$status->id] = $status->name;
      }

      $form['status'] = [
        '#type' => 'select',
        '#options' => $options,
        '#title' => $this->t('After successful import change status to:'),
        '#empty_option' => $this->t("- Don't change status -"),
      ];

      $import_config = $this->configFactory()->get('gathercontent.import');

      $form['node_create_new_revision'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Create new revision'),
        '#default_value' => $import_config->get('node_create_new_revision'),
        '#description' => $this->t('If the "Content update method" is any other than "Always update existing Content" then this setting won\'t take effect, because the entity will always be new.'),
      ];

      $form['actions']['#type'] = 'actions';
      $form['actions']['submit'] = [
        '#type' => 'submit',
        '#value' => $this->t('Import'),
        '#button_type' => 'primary',
        '#weight' => 10,
      ];
      $form['actions']['back'] = [
        '#type' => 'submit',
        '#value' => $this->t('Back'),
        '#weight' => 11,
      ];
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    if ($form_state->getTriggeringElement()['#id'] === 'edit-submit') {
      if ($this->step === 1) {
        $stack = [];
        $import_content = [];
        $selected_menus = [];
        foreach ($form_state->getValue('items') as $item_id => $item) {
          if ($item['selected'] === "1") {
            $import_content[] = $item_id;

            if (!empty($item['menu'])) {
              $selected_menus[$item_id] = $item['menu'];
            }
          }
        }
        foreach ($import_content as $k => $value) {
          if ((isset($selected_menus[$value]) && $selected_menus[$value] != -1) || !isset($selected_menus[$value])) {
            $stack[$value] = $value;
            unset($import_content[$k]);
          }
        }

        if (!empty($import_content)) {
          // Load all by project_id.
          /** @var \Cheppers\GatherContent\DataTypes\Item[] $content */
          $content = $this->client->itemsGet($form_state->getValue('project'));

          $num_of_repeats = 0;
          $size = count($import_content);

          while (!empty($import_content)) {
            $current = reset($import_content);
            if (isset($stack[$content[$current]->parentId])) {
              $stack[$current] = $current;
              array_shift($import_content);
            }
            else {
              array_shift($import_content);
              array_push($import_content, $current);
              $num_of_repeats++;
              if ($num_of_repeats >= ($size * $size)) {
                $form_state->setErrorByName('form', $this->t("Please check your menu selection, some of items don't have parent in import."));
                $import_content = [];
              }
            }
          }
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    if ($form_state->getTriggeringElement()['#id'] === 'edit-submit') {
      if ($this->step === 1) {
        $this->projectId = $form_state->getValue('project');
        $selected_nodes = [];
        $selected_menus = [];
        $selected_statuses = [];
        foreach ($form_state->getValue('items') as $item_id => $item) {
          if ($item['selected'] === "1") {
            $selected_nodes[] = $item_id;
            $selected_statuses[$item_id] = $item['drupal_status'];

            if (!empty($item['menu'])) {
              $selected_menus[$item_id] = $item['menu'];
            }
          }
        }
        $this->nodes = $selected_nodes;
        $this->menu = $selected_menus;
        $this->drupalStatus = $selected_statuses;
        $this->step = 2;
        $form_state->setRebuild(TRUE);
      }
      elseif ($this->step === 2) {
        $operations = [];
        $stack = [];
        $import_content = $this->nodes;
        $gcIds = [];
        $import_options = [];

        foreach ($import_content as $k => $value) {
          /** @var \Cheppers\GatherContent\DataTypes\Item $item */
          $gc_item = $this->client->itemGet($value);
          /** @var \Drupal\gathercontent\Entity\MappingInterface $mapping */
          $mapping = MappingLoader::load($gc_item);
          $mappingId = $mapping->id();

          $parent_menu_item = isset($this->menu[$value]) ? $this->menu[$value] : NULL;
          $drupal_status = isset($this->drupalStatus[$value]) ? $this->drupalStatus[$value] : 0;

          $import_options[$mappingId][$value] = new ImportOptions(
            $drupal_status,
            $form_state->getValue('node_create_new_revision'),
            $form_state->getValue('status'),
            $parent_menu_item
          );

          if (!empty($value) && (!isset($gcIds[$mappingId]) || !array_search($value, $gcIds[$mappingId]))) {
            $gcIds[$mappingId][] = $value;
          }

          $operations[$mappingId] = [
            'gathercontent_import_process',
            [
              $gcIds[$mappingId],
              $import_options[$mappingId],
              $mapping,
            ],
          ];

          $stack[$value] = $value;
          unset($import_content[$k]);
        }

        $batch = [
          'title' => $this->t('Importing content ...'),
          'operations' => $operations,
          'finished' => 'gathercontent_ui_import_finished',
          'file' => drupal_get_path('module', 'gathercontent') . '/gathercontent.module',
          'init_message' => $this->t('Import is starting ...'),
          'progress_message' => $this->t('Processed @current out of @total.'),
          'error_message' => $this->t('An error occurred during processing'),
        ];

        batch_set($batch);
      }
    }
    elseif ($form_state->getTriggeringElement()['#id'] === 'edit-back') {
      if ($this->step === 1) {
        return $form_state->setRedirect('gathercontent_ui.import_select_form');
      }
      $this->step = 1;
      $form_state->setRebuild(TRUE);
    }
    return TRUE;
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
