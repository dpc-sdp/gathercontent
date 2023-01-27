<?php

namespace Drupal\gathercontent_ui\Form;

use GatherContent\GatherContentClientInterface;
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
   * Step.
   *
   * @var int
   */
  protected $step;

  /**
   * Project ID.
   *
   * @var int|string
   */
  protected $projectId;

  /**
   * Nodes.
   *
   * @var mixed|object
   */
  protected $nodes;

  /**
   * Menu.
   *
   * @var string
   */
  protected $menu;

  /**
   * Items.
   *
   * @var array|string
   */
  protected $items;

  /**
   * Drupal status.
   *
   * @var mixed
   */
  protected $drupalStatus;

  /**
   * GatherCotnent client.
   *
   * @var \GatherContent\GatherContentClientInterface
   */
  protected $client;

  /**
   * Menu parent form selector.
   *
   * @var \Drupal\Core\Menu\MenuParentFormSelectorInterface
   */
  protected $menuParentFormSelector;

  /**
   * Entity type manager.
   *
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
      $mappings = Mapping::loadMultiple();
      $projects = [];
      $mappingArray = [];
      $contentTypes = [];
      $entityTypes = [];

      foreach ($mappings as $mapping) {
        /** @var \Drupal\gathercontent\Entity\Mapping $mapping */
        if ($mapping->hasMapping()) {
          if (!array_key_exists($mapping->getGathercontentTemplateId(), $contentTypes)) {
            $contentTypes[$mapping->getGathercontentTemplateId()] = $mapping->getContentType();
            $entityTypes[$mapping->getGathercontentTemplateId()] = $mapping->getMappedEntityType();
          }
          $projects[$mapping->getGathercontentProjectId()] = $mapping->getGathercontentProject();
          $mappingArray[$mapping->getGathercontentTemplateId()] = [
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

        $projectId = $form_state->hasValue('project') ? $form_state->getValue('project') : $this->projectId;
        $content = $this->client->itemsGet($projectId);
        $statuses = $this->client->projectStatusesGet($projectId);
        $importConfig = $this->configFactory()->get('gathercontent.import');

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
            && isset($mappingArray[$item->templateId])
          ) {
            if ($entityTypes[$item->templateId] == 'node') {
              $nodeType = $this->entityTypeManager->getStorage('node_type')->load($contentTypes[$item->templateId]);
              $selectedBoxes = $nodeType->getThirdPartySetting('menu_ui', 'available_menus', ['main']);
              $availableMenus = [];

              foreach ($selectedBoxes as $selected_box) {
                $availableMenus[$selected_box] = $selected_box;
              }
            }

            $this->items[$item->id] = [
              'color' => $statuses['data'][$item->statusId]->color,
              'label' => $statuses['data'][$item->statusId]->name,
              'template' => $mappingArray[$item->templateId]['gc_template'],
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
                  '#markup' => $mappingArray[$item->templateId]['gc_template'],
                ],
                '#wrapper_attributes' => [
                  'class' => ['template-name-item'],
                ],
              ],
              'drupal_status' => [
                '#type' => 'checkbox',
                '#title' => $this->t('Publish'),
                '#title_display' => 'invisible',
                '#default_value' => isset($this->drupalStatus[$item->id]) ? $this->drupalStatus[$item->id] : $importConfig->get('node_default_status'),
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

            if ($entityTypes[$item->templateId] == 'node') {
              $form['import']['items'][$item->id]['menu'] = [
                '#type' => 'select',
                '#default_value' => $nodeType->getThirdPartySetting('menu_ui', 'parent'),
                '#empty_option' => $this->t("- Don't create menu item -"),
                '#empty_value' => 0,
                '#options' => $this->menuParentFormSelector
                  ->getParentSelectOptions('', $availableMenus),
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

      $rows = [];
      foreach ($this->nodes as $node) {
        $rows[$node] = [
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
        '#rows' => $rows,
      ];

      $options = [];
      /** @var \GatherContent\DataTypes\Status[] $statuses */
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

      $importConfig = $this->configFactory()->get('gathercontent.import');

      $form['node_create_new_revision'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Create new revision'),
        '#default_value' => $importConfig->get('node_create_new_revision'),
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
  public function submitForm(array &$form, FormStateInterface $form_state) {
    if ($form_state->getTriggeringElement()['#id'] === 'edit-submit') {
      if ($this->step === 1) {
        $this->projectId = $form_state->getValue('project');
        $selectedNodes = [];
        $selectedMenus = [];
        $selectedStatuses = [];

        foreach ($form_state->getValue('items') as $item_id => $item) {
          if ($item['selected'] === "1") {
            $selectedNodes[] = $item_id;
            $selectedStatuses[$item_id] = $item['drupal_status'];

            if (!empty($item['menu'])) {
              $selectedMenus[$item_id] = $item['menu'];
            }
          }
        }

        $this->nodes = $selectedNodes;
        $this->menu = $selectedMenus;
        $this->drupalStatus = $selectedStatuses;
        $this->step = 2;
        $form_state->setRebuild(TRUE);
      }
      elseif ($this->step === 2) {
        $operations = [];
        $importContent = $this->nodes;
        $gcIds = [];
        $importOptions = [];

        foreach ($importContent as $value) {
          /** @var \GatherContent\DataTypes\Item $item */
          $gcItem = $this->client->itemGet($value);
          /** @var \Drupal\gathercontent\Entity\MappingInterface $mapping */
          $mapping = MappingLoader::load($gcItem);
          $mappingId = $mapping->id();

          $parentMenuItem = isset($this->menu[$value]) ? $this->menu[$value] : NULL;
          $drupalStatus = isset($this->drupalStatus[$value]) ? $this->drupalStatus[$value] : 0;

          $importOptions[$mappingId][$value] = new ImportOptions(
            $drupalStatus,
            $form_state->getValue('node_create_new_revision'),
            $form_state->getValue('status'),
            $parentMenuItem
          );

          if (!empty($value) && (!isset($gcIds[$mappingId]) || !array_search($value, $gcIds[$mappingId]))) {
            $gcIds[$mappingId][] = $value;
          }

          $operations[$mappingId] = [
            'gathercontent_import_process',
            [
              $gcIds[$mappingId],
              $importOptions[$mappingId],
              $mapping,
            ],
          ];
        }

        $batch = [
          'title' => $this->t('Importing content ...'),
          'operations' => $operations,
          'finished' => 'gathercontent_ui_import_finished',
          'file' => \Drupal::service('extension.list.module')->getPath('gathercontent') . '/gathercontent.module',
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
