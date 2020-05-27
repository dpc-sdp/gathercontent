<?php

namespace Drupal\gathercontent_upload_ui\Form;

use Cheppers\GatherContent\GatherContentClientInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\gathercontent\Entity\Mapping;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class ContentUpdateSelectForm.
 *
 * @package Drupal\gathercontent\Form
 */
class ContentUploadSelectForm extends FormBase {

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
   * @var array|string
   */
  protected $items;

  /**
   * @var \Cheppers\GatherContent\GatherContentClientInterface
   */
  protected $client;

  /**
   * The current database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * {@inheritdoc}
   */
  public function __construct(
    GatherContentClientInterface $client,
    Connection $database,
    EntityTypeManagerInterface $entityTypeManager
  ) {
    $this->client = $client;
    $this->database = $database;
    $this->entityTypeManager = $entityTypeManager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('gathercontent.client'),
      $container->get('entity_type.manager')
    );
  }

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
    $form = [];

    if (empty($this->step)) {
      $this->step = 1;
    }

    // Step 1 - select project + select nodes.
    // Step 2 - confirm screen.
    if ($this->step === 1) {
      $createdMappings = Mapping::loadMultiple();
      $projects = [];
      $mappingArray = [];
      $contentTypes = [];
      $entityTypes = [];

      foreach ($createdMappings as $mapping) {
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
          'wrapper' => 'edit-export',
          'method' => 'replace',
          'effect' => 'fade',
        ],
        '#default_value' => !empty($this->projectId) ? $this->projectId : 0,
        '#description' => $this->t('You can only see projects with mapped templates in the dropdown.'),
      ];

      $form['export'] = [
        '#prefix' => '<div id="edit-export">',
        '#suffix' => '</div>',
      ];

      if (($form_state->hasValue('project') || !empty($this->projectId))
        && (!empty($form_state->getValue('project')))
      ) {
        $form['export']['filter'] = [
          '#type' => 'markup',
          '#markup' => '<div class="gc-table--filter-wrapper clearfix"></div>',
          '#weight' => 0,
        ];

        $form['export']['counter'] = [
          '#type' => 'markup',
          '#markup' => '<div class="gc-table--counter"></div>',
          '#weight' => 1,
        ];

        $form['export']['items'] = [
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

        $results = $this->database->select('gathercontent_entity_mapping')
          ->fields('gathercontent_entity_mapping', [
            'gc_id',
          ])
          ->condition('migration_id', array_keys($createdMappings), 'IN')
          ->execute()
          ->fetchAll();

        $projectId = $form_state->hasValue('project') ? $form_state->getValue('project') : $this->projectId;
        $content = $this->client->itemsGet($projectId, ['item_ids' => implode($results)]);

        foreach ($content['data'] as $item) {
          // If template is not empty, we have mapped template and item
          // isn't synced yet.
          if (!is_null($item->templateId)
            && $item->templateId != 'null'
            && isset($mappingArray[$item->templateId])
          ) {
            if ($entityTypes[$item->templateId] == 'node') {
              $node_type = $this->entityTypeManager->getStorage('node_type')->load($contentTypes[$item->templateId]);
              $selected_boxes = $node_type->getThirdPartySetting('menu_ui', 'available_menus', ['main']);
              $available_menus = [];
              foreach ($selected_boxes as $selected_box) {
                $available_menus[$selected_box] = $selected_box;
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

            if ($entityTypes[$item->templateId] == 'node') {
              $form['import']['items'][$item->id]['menu'] = [
                '#type' => 'select',
                '#default_value' => $node_type->getThirdPartySetting('menu_ui', 'parent'),
                '#empty_option' => $this->t("- Don't create menu item -"),
                '#empty_value' => 0,
                '#options' => $this->menuParentFormSelector
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
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $form_state->setRedirect('gathercontent_upload_ui.upload_confirm_form');
  }

}
