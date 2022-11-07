<?php

namespace Drupal\gathercontent_upload_ui\Form;

use GatherContent\GatherContentClientInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\gathercontent\Entity\Mapping;
use PDO;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class ContentUpdateSelectForm.
 *
 * @package Drupal\gathercontent\Form
 */
class ContentUploadSelectForm extends FormBase {

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
   * Entities.
   *
   * @var mixed|object
   */
  protected $entities;

  /**
   * Items.
   *
   * @var array|string
   */
  protected $items;

  /**
   * Client object.
   *
   * @var \GatherContent\GatherContentClientInterface
   */
  protected $client;

  /**
   * The current database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

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
      $container->get('database'),
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

    // Step 1 - select project + select entities.
    // Step 2 - confirm screen.
    if ($this->step === 1) {
      $createdMappings = Mapping::loadMultiple();
      $projects = [];
      $mappingArray = [];
      $migrationIds = [];

      foreach ($createdMappings as $mapping) {
        /** @var \Drupal\gathercontent\Entity\Mapping $mapping */
        if ($mapping->hasMapping()) {
          $projects[$mapping->getGathercontentProjectId()] = $mapping->getGathercontentProject();
          $mappingArray[$mapping->getGathercontentTemplateId()] = [
            'gc_template' => $mapping->getGathercontentTemplate(),
            'id' => $mapping->id(),
          ];

          $migrationIds = array_merge($migrationIds, $mapping->getMigrations());
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
          'wrapper' => 'edit-upload',
          'method' => 'replace',
          'effect' => 'fade',
        ],
        '#default_value' => !empty($this->projectId) ? $this->projectId : 0,
        '#description' => $this->t('You can only see projects with mapped templates in the dropdown.'),
      ];

      $form['upload'] = [
        '#prefix' => '<div id="edit-upload">',
        '#suffix' => '</div>',
      ];

      if (($form_state->hasValue('project') || !empty($this->projectId))
        && (!empty($form_state->getValue('project')))
      ) {
        $form['upload']['filter'] = [
          '#type' => 'markup',
          '#markup' => '<div class="gc-table--filter-wrapper clearfix"></div>',
          '#weight' => 0,
        ];

        $form['upload']['counter'] = [
          '#type' => 'markup',
          '#markup' => '<div class="gc-table--counter"></div>',
          '#weight' => 1,
        ];

        $form['upload']['entities'] = [
          '#tree' => TRUE,
          '#type' => 'table',
          '#header' => [
            'selected' => [
              'class' => ['select-all'],
              'data' => '',
            ],
            'title' => $this->t('Item Name'),
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

        $projectId = $form_state->hasValue('project') ? $form_state->getValue('project') : $this->projectId;

        foreach ($createdMappings as $mapping) {
          /** @var \Drupal\gathercontent\Entity\Mapping $mapping */
          if (
            !$mapping->hasMapping()
            || $mapping->getGathercontentProjectId() != $projectId
          ) {
            continue;
          }

          $entityType = $mapping->getMappedEntityType();
          $entityStorage = $this->entityTypeManager->getStorage($entityType);
          $bundle = $this->entityTypeManager->getDefinition($entityType)->getKey('bundle');
          $entities = $entityStorage->loadByProperties([$bundle => $mapping->getContentType()]);

          foreach ($entities as $entity) {
            $entityId = $entity->id();
            $key = $entityType . '_' . $entityId;

            $gcId = $this->database->select('gathercontent_entity_mapping')
              ->fields('gathercontent_entity_mapping', [
                'gc_id',
              ])
              ->condition('migration_id', $migrationIds, 'IN')
              ->condition('entity_type', $entityType)
              ->condition('entity_id', $entityId)
              ->execute()
              ->fetchField();

            $this->items[$key] = [
              'template' => $mapping->getGathercontentTemplate(),
              'title' => $entity->label(),
            ];

            $form['upload']['entities'][$key] = [
              '#tree' => TRUE,
              'data' => [
                'selected' => [
                  '#type' => 'checkbox',
                  '#title' => $this->t('Selected'),
                  '#title_display' => 'invisible',
                  '#default_value' => !empty($this->entities[$key]),
                  '#attributes' => [
                    'class' => ['gathercontent-select-import-items'],
                  ],
                ],
                'entity_type' => [
                  '#type' => 'hidden',
                  '#value' => $entityType,
                ],
                'entity_id' => [
                  '#type' => 'hidden',
                  '#value' => $entityId,
                ],
                'gc_id' => [
                  '#type' => 'hidden',
                  '#value' => $gcId,
                ],
                'mapping_id' => [
                  '#type' => 'hidden',
                  '#value' => $mapping->id(),
                ],
              ],
              'title' => [
                'data' => [
                  '#type' => 'markup',
                  '#markup' => $entity->label(),
                ],
                '#wrapper_attributes' => [
                  'class' => ['gc-item', 'gc-item--name'],
                ],
              ],
              'template' => [
                'data' => [
                  '#markup' => $mapping->getGathercontentTemplate(),
                ],
                '#wrapper_attributes' => [
                  'class' => ['template-name-item'],
                ],
              ],
            ];
          }
        }

        $form['upload']['actions']['#type'] = 'actions';
        $form['upload']['actions']['submit'] = [
          '#type' => 'submit',
          '#value' => $this->t('Next'),
          '#button_type' => 'primary',
          '#weight' => 10,
        ];

        $form['upload']['actions']['back'] = [
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
          '#value' => $this->formatPlural(count($this->entities),
            'Confirm upload selection (@count item)',
            'Confirm upload selection (@count items)'
          ),
        ],
        'form_description' => [
          '#type' => 'html_tag',
          '#tag' => 'p',
          '#value' => $this->t('Please review your upload selection before uploading.'),
        ],
      ];

      $header = [
        'title' => $this->t('Item name'),
        'template' => $this->t('GatherContent Template'),
      ];

      $rows = [];
      foreach ($this->entities as $key => $data) {
        $rows[$key] = [
          'title' => $this->items[$key]['title'],
          'template' => $this->items[$key]['template'],
        ];
      }

      $form['table'] = [
        '#type' => 'table',
        '#header' => $header,
        '#rows' => $rows,
      ];

      $form['actions']['#type'] = 'actions';
      $form['actions']['submit'] = [
        '#type' => 'submit',
        '#value' => $this->t('Upload'),
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
        $selectedEntities = [];

        foreach ($form_state->getValue('entities') as $key => $data) {
          if ($data['data']['selected'] === '1') {
            $selectedEntities[$key] = [
              'id' => $data['data']['entity_id'],
              'entity_type' => $data['data']['entity_type'],
              'gc_id' => $data['data']['gc_id'],
              'mapping_id' => $data['data']['mapping_id'],
            ];
          }
        }

        $this->entities = $selectedEntities;
        $this->step = 2;
        $form_state->setRebuild(TRUE);
      }
      elseif ($this->step === 2) {
        $operations = [];
        $uploadContent = $this->entities;

        foreach ($uploadContent as $data) {
          /** @var \Drupal\gathercontent\Entity\MappingInterface $mapping */
          $mapping = Mapping::load($data['mapping_id']);
          $storage = $this->entityTypeManager->getStorage($data['entity_type']);
          $entity = $storage->load($data['id']);

          $operations[] = [
            'gathercontent_upload_process',
            [
              $entity,
              $mapping,
              $data['gc_id'],
            ],
          ];
        }

        $operations[] = [
          'gathercontent_upload_migrate_update_process',
          [],
        ];

        $batch = [
          'title' => $this->t('Uploading content ...'),
          'operations' => $operations,
          'finished' => 'gathercontent_upload_finished',
          'file' => drupal_get_path('module', 'gathercontent_upload_ui') . '/gathercontent_upload_ui.module',
          'init_message' => $this->t('Upload is starting ...'),
          'progress_message' => $this->t('Processed @current out of @total.'),
          'error_message' => $this->t('An error occurred during processing'),
          'progressive' => TRUE,
        ];

        batch_set($batch);
      }
    }
    elseif ($form_state->getTriggeringElement()['#id'] === 'edit-back') {
      if ($this->step === 1) {
        return $form_state->setRedirect('gathercontent_upload_ui.upload_select_form');
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
    return $form['upload'];
  }

}
