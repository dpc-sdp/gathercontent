<?php

namespace Drupal\gathercontent_ui\Form;

use Cheppers\GatherContent\DataTypes\Template;
use Cheppers\GatherContent\GatherContentClientInterface;
use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\taxonomy\Entity\Term;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class MappingEditForm.
 *
 * @package Drupal\gathercontent\Form
 */
class MappingEditForm extends EntityForm {

  /**
   * Flag for mapping if it's new.
   *
   * @var bool
   */
  protected $new;

  /**
   * Step in multipart form.
   *
   * Values:
   * - field_mapping
   * - er_mapping
   * - completed.
   *
   * @var string
   */
  protected $step;

  /**
   * Mapping data.
   *
   * @var array
   */
  protected $mappingData;

  /**
   * GatherContent full template.
   *
   * @var object
   */
  protected $template;

  /**
   * Machine name of content type.
   *
   * @var string
   */
  protected $contentType;

  /**
   * Array of entity reference fields in mapping.
   *
   * @var array
   */
  protected $entityReferenceFields;

  /**
   * Type of import for entity reference fields.
   *
   * Values:
   * - automatic
   * - manual
   * - semiautomatic.
   *
   * @var string
   */
  protected $erImportType;

  /**
   * Flag for skipping ER mapping.
   *
   * @var bool
   */
  protected $skip;

  /**
   * Count of imported or updated taxonomy terms.
   *
   * @var int
   */
  protected $erImported;

  /**
   * GatherContent client.
   *
   * @var \Drupal\gathercontent\DrupalGatherContentClient
   */
  protected $client;

  /**
   * MappingImportForm constructor.
   *
   * @param \Cheppers\GatherContent\GatherContentClientInterface $client
   *   GatherContent client.
   */
  public function __construct(GatherContentClientInterface $client) {
    $this->client = $client;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('gathercontent.client')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    if (empty($this->step)) {
      $this->step = 'field_mapping';
    }

    $form['#attached']['library'][] = 'gathercontent_ui/theme';
    $form['#attached']['library'][] = 'gathercontent_ui/entity_references';

    /** @var \Drupal\gathercontent\Entity\MappingInterface $mapping */
    $mapping = $this->entity;
    $this->new = !$mapping->hasMapping();

    $template = $this->client->templateGet($mapping->getGathercontentTemplateId());

    if ($this->step === 'field_mapping') {
      $content_types = node_type_get_names();
      $form['form_description'] = [
        '#type' => 'html_tag',
        '#tag' => 'i',
        '#value' => t('Please map your GatherContent Template fields to your Drupal
    Content Type Fields. Please note that a GatherContent field can only be
    mapped to a single Drupal field. So each field can only be mapped to once.'),
      ];

      $form['gathercontent_project'] = [
        '#type' => 'item',
        '#title' => $this->t('Project name:'),
        '#markup' => $mapping->getGathercontentProject(),
        '#wrapper_attributes' => [
          'class' => [
            'inline-label',
          ],
        ],
      ];
      $form['gathercontent'] = [
        '#type' => 'container',
      ];

      $form['gathercontent']['gathercontent_template'] = [
        '#type' => 'item',
        '#title' => $this->t('GatherContent template:'),
        '#markup' => $mapping->getGathercontentTemplate(),
        '#wrapper_attributes' => [
          'class' => [
            'inline-label',
          ],
        ],
      ];

      if (!$this->new) {
        $this->mappingData = unserialize($mapping->getData());
        $this->contentType = $mapping->getContentType();
        $form['#attached']['drupalSettings']['gathercontent'] = $this->getAllEntityReferenceFields();

        $form['gathercontent']['content_type'] = [
          '#type' => 'item',
          '#title' => $this->t('Drupal content type:'),
          '#markup' => $mapping->getContentTypeName(),
          '#wrapper_attributes' => [
            'class' => [
              'inline-label',
            ],
          ],
        ];

        $form['mapping'] = [
          '#prefix' => '<div id="edit-mapping">',
          '#suffix' => '</div>',
        ];

        foreach ($template->config as $i => $fieldset) {
          if ($fieldset->hidden === FALSE) {
            $form['mapping'][$fieldset->id] = [
              '#type' => 'details',
              '#title' => $fieldset->label,
              '#open' => ($i === 0 ? TRUE : FALSE),
              '#tree' => TRUE,
            ];

            if (\Drupal::moduleHandler()->moduleExists('metatag')) {
              $form['mapping'][$fieldset->id]['type'] = [
                '#type' => 'select',
                '#options' => [
                  'content' => t('Content'),
                  'metatag' => t('Metatag'),
                ],
                '#title' => t('Type'),
                '#default_value' => (isset($this->mappingData[$fieldset->id]['type']) || $form_state->hasValue($fieldset->id)['type']) ? ($form_state->hasValue($fieldset->id)['type'] ? $form_state->getValue($fieldset->id)['type'] : $this->mappingData[$fieldset->id]['type']) : 'content',
                '#ajax' => [
                  'callback' => '::getMappingTable',
                  'wrapper' => 'edit-mapping',
                  'method' => 'replace',
                  'effect' => 'fade',
                ],
              ];
            }

            if (\Drupal::moduleHandler()->moduleExists('content_translation') &&
              \Drupal::service('content_translation.manager')
                ->isEnabled('node', $form_state->getValue('content_type'))
            ) {

              $form['mapping'][$fieldset->id]['language'] = [
                '#type' => 'select',
                '#options' => ['und' => t('None')] + $this->getLanguageList(),
                '#title' => t('Language'),
                '#default_value' => isset($this->mappingData[$fieldset->id]['language']) ? $this->mappingData[$fieldset->id]['language'] : 'und',
              ];
            }

            foreach ($fieldset->elements as $gc_field) {
              $d_fields = [];
              if (isset($form_state->getTriggeringElement()['#name'])) {
                // We need different handling for changed fieldset.
                if ($form_state->getTriggeringElement()['#array_parents'][1] === $fieldset->id) {
                  if ($form_state->getTriggeringElement()['#value'] === 'content') {
                    $d_fields = $this->filterFields($gc_field, $mapping->getContentType());
                  }
                  elseif ($form_state->getTriggeringElement()['#value'] === 'metatag') {
                    $d_fields = $this->filterMetatags($gc_field);
                  }
                }
                else {
                  if ($form_state->getValue($fieldset->id)['type'] === 'content') {
                    $d_fields = $this->filterFields($gc_field, $mapping->getContentType());
                  }
                  elseif ($form_state->getTriggeringElement()['#value'] === 'metatag') {
                    $d_fields = $this->filterMetatags($gc_field);
                  }
                }
              }
              else {
                if ((isset($this->mappingData[$fieldset->id]['type']) && $this->mappingData[$fieldset->id]['type'] === 'content') || !isset($this->mappingData[$fieldset->id]['type'])) {
                  $d_fields = $this->filterFields($gc_field, $mapping->getContentType());
                }
                else {
                  $d_fields = $this->filterMetatags($gc_field);
                }
              }
              $form['mapping'][$fieldset->id]['elements'][$gc_field->id] = [
                '#type' => 'select',
                '#options' => $d_fields,
                '#title' => (!empty($gc_field->label) ? $gc_field->label : $gc_field->title),
                '#default_value' => isset($this->mappingData[$fieldset->id]['elements'][$gc_field->id]) ? $this->mappingData[$fieldset->id]['elements'][$gc_field->id] : NULL,
                '#empty_option' => t("Don't map"),
                '#attributes' => [
                  'class' => [
                    'gathercontent-ct-element',
                  ],
                ],
              ];
            }
          }
        }
        $form['mapping']['er_mapping_type'] = [
          '#type' => 'radios',
          '#title' => $this->t('Taxonomy terms mapping type'),
          '#options' => [
            'automatic' => $this->t('Automatic'),
            'semiautomatic' => $this->t('Semi-automatic'),
            'manual' => $this->t('Manual'),
          ],
          '#attributes' => [
            'class' => ['gathercontent-er-mapping-type'],
          ],
          '#description' => $this->t("<strong>Automatic</strong> - taxonomy terms will be automatically created in predefined vocabulary. You cannot select translations. Field should be set as translatable for correct functionality.<br>
<strong>Semi-automatic</strong> - taxonomy terms will be imported into predefined vocabulary in the first language and we will offer you possibility to select their translations from other languages. For single language mapping this option will execute same action as 'Automatic' importField should not be set as translatable for correct functionality.<br>
<strong>Manual</strong> - you can map existing taxonomy terms from predefined vocabulary to translations in all languages."),
        ];
      }
      else {
        $form['gathercontent']['content_type'] = [
          '#type' => 'select',
          '#title' => $this->t('Drupal content type'),
          '#options' => $content_types,
          '#required' => TRUE,
          '#wrapper_attributes' => [
            'class' => [
              'inline-label',
            ],
          ],
          '#ajax' => [
            'callback' => '::getMappingTable',
            'wrapper' => 'edit-mapping',
            'method' => 'replace',
            'effect' => 'fade',
          ],
          '#default_value' => $form_state->getValue('content_type'),
        ];

        $form['mapping'] = [
          '#prefix' => '<div id="edit-mapping">',
          '#suffix' => '</div>',
        ];

        if ($form_state->hasValue('content_type')) {
          $this->contentType = $form_state->getValue('content_type');
          foreach ($template->config as $i => $fieldset) {
            if ($fieldset->hidden === FALSE) {
              $form['mapping'][$fieldset->id] = [
                '#type' => 'details',
                '#title' => $fieldset->label,
                '#open' => ($i === 0 ? TRUE : FALSE),
                '#tree' => TRUE,
              ];

              if ($i === 0) {
                $form['mapping'][$fieldset->id]['#prefix'] = '<div id="edit-mapping">';
              }
              if (end($template->config) === $fieldset) {
                $form['mapping'][$fieldset->id]['#suffix'] = '</div>';
              }

              if (\Drupal::moduleHandler()->moduleExists('metatag')) {
                $form['mapping'][$fieldset->id]['type'] = [
                  '#type' => 'select',
                  '#options' => [
                    'content' => t('Content'),
                    'metatag' => t('Metatag'),
                  ],
                  '#title' => t('Type'),
                  '#default_value' => $form_state->hasValue($fieldset->id)['type'] ? $form_state->getValue($fieldset->id)['type'] : 'content',
                  '#ajax' => [
                    'callback' => '::getMappingTable',
                    'wrapper' => 'edit-mapping',
                    'method' => 'replace',
                    'effect' => 'fade',
                  ],
                ];
              }

              if (\Drupal::moduleHandler()
                ->moduleExists('content_translation') &&
                \Drupal::service('content_translation.manager')
                  ->isEnabled('node', $form_state->getValue('content_type'))
              ) {

                $form['mapping'][$fieldset->id]['language'] = [
                  '#type' => 'select',
                  '#options' => ['und' => t('None')] + $this->getLanguageList(),
                  '#title' => t('Language'),
                  '#default_value' => $form_state->hasValue($fieldset->id)['language'] ? $form_state->getValue($fieldset->id)['language'] : 'und',
                ];
              }

              foreach ($fieldset->elements as $gc_field) {
                $d_fields = [];
                if ($form_state->getTriggeringElement()['#name'] !== 'content_type') {
                  // We need different handling for changed fieldset.
                  if ($form_state->getTriggeringElement()['#array_parents'][1] === $fieldset->id) {
                    if ($form_state->getTriggeringElement()['#value'] === 'content') {
                      $d_fields = $this->filterFields($gc_field, $this->contentType);
                    }
                    elseif ($form_state->getTriggeringElement()['#value'] === 'metatag') {
                      $d_fields = $this->filterMetatags($gc_field);
                    }
                  }
                  else {
                    if ($form_state->getValue($fieldset->id)['type'] === 'content') {
                      $d_fields = $this->filterFields($gc_field, $this->contentType);
                    }
                    elseif ($form_state->getValue($fieldset->id)['type'] === 'metatag') {
                      $d_fields = $this->filterMetatags($gc_field);
                    }
                  }
                }
                else {
                  $d_fields = $this->filterFields($gc_field, $this->contentType);
                }
                $form['mapping'][$fieldset->id]['elements'][$gc_field->id] = [
                  '#type' => 'select',
                  '#options' => $d_fields,
                  '#title' => (!empty($gc_field->label) ? $gc_field->label : $gc_field->title),
                  '#empty_option' => $this->t("Don't map"),
                  '#default_value' => $form_state->hasValue($fieldset->id)['elements'][$gc_field->id]
                  ? $form_state->getValue($fieldset->id)['elements'][$gc_field->id] : NULL,
                  '#attributes' => [
                    'class' => [
                      'gathercontent-ct-element',
                    ],
                  ],
                ];
              }
            }
          }
          $form['mapping']['er_mapping_type'] = [
            '#type' => 'radios',
            '#title' => $this->t('Taxonomy terms mapping type'),
            '#options' => [
              'automatic' => $this->t('Automatic'),
              'semiautomatic' => $this->t('Semi-automatic'),
              'manual' => $this->t('Manual'),
            ],
            '#attributes' => [
              'class' => ['gathercontent-er-mapping-type'],
            ],
            '#description' => $this->t("<strong>Automatic</strong> - taxonomy terms will be automatically created in predefined vocabulary. You cannot select translations. Field should be set as translatable for correct functionality.<br>
<strong>Semi-automatic</strong> - taxonomy terms will be imported into predefined vocabulary in the first language and we will offer you possibility to select their translations from other languages. For single language mapping this option will execute same action as 'Automatic' importField should not be set as translatable for correct functionality.<br>
<strong>Manual</strong> - you can map existing taxonomy terms from predefined vocabulary to translations in all languages."),
          ];
        }
      }
    }
    elseif ($this->step === 'er_mapping') {
      // Unset previous form.
      foreach ($form as $k => $item) {
        if (!in_array($k, ['#attributes', '#cache'])) {
          unset($form[$k]);
        }
      }

      foreach ($this->entityReferenceFields as $field => $gcMapping) {
        $field_config = FieldConfig::loadByName('node', $this->contentType, $field);

        $options = [];
        $header = [];

        // Prepare options for every language.
        foreach ($gcMapping as $lang => $fieldSettings) {
          foreach ($template->config as $tab) {
            if ($tab->id === $fieldSettings['tab']) {
              foreach ($tab->elements as $element) {
                if ($element->id == $fieldSettings['name']) {
                  $header[$lang] = $this->t('@field (@lang values)', [
                    '@field' => $element->label,
                    '@lang' => strtoupper($lang),
                  ]);
                  if (count($header) === 1 && $this->erImportType === 'manual') {
                    $header['terms'] = $this->t('Terms');
                  }
                  foreach ($element->options as $option) {
                    if (!isset($option->value)) {
                      $options[$lang][$option->name] = $option->label;
                    }
                  }
                }
              }
            }
          }
        }

        $term_options = [];
        // For manual mapping load terms from vocabulary.
        if ($this->erImportType === 'manual') {
          $settings = $field_config->getSetting('handler_settings');
          /** @var \Drupal\taxonomy\Entity\Term[] $terms */
          if (!empty($settings['auto_create_bundle'])) {
            $terms = $this->entityTypeManager->getStorage('taxonomy_term')
              ->loadByProperties(['vid' => $settings['auto_create_bundle']]);
          }
          else {
            $target = reset($settings['target_bundles']);
            $terms = $this->entityTypeManager->getStorage('taxonomy_term')
              ->loadByProperties(['vid' => $target]);
          }
          foreach ($terms as $term) {
            $term_options[$term->id()] = $term->getName();
          }

        }

        // Extract available languages and first language and his options.
        $languages = array_keys($header);
        $first_language = array_shift($languages);
        $first_language_options = array_shift($options);
        // Delete terms from languages, it's not language.
        if (isset($languages[0]) && $languages[0] === 'terms') {
          unset($languages[0]);
        }

        $form[$field] = [
          '#tree' => TRUE,
        ];

        $form[$field]['title'] = [
          '#type' => 'html_tag',
          '#value' => $this->t('Field @field', ['@field' => $field_config->getLabel()]),
          '#tag' => 'h2',
        ];

        // Define table header.
        $form[$field]['table'] = [
          '#type' => 'table',
          '#header' => $header,
        ];

        // Each option in the first language is new row.
        // This solution is not dealing with situation when other languages has
        // more options than first language.
        $rows = 0;
        foreach ($first_language_options as $k => $option) {
          $form[$field]['table'][$rows][$first_language] = [
            '#type' => 'value',
            '#value' => $k,
            '#markup' => $option,
          ];

          if ($this->erImportType === 'manual') {
            $form[$field]['table'][$rows]['terms'] = [
              '#type' => 'select',
              '#options' => $term_options,
              '#title' => $this->t('Taxonomy term options'),
              '#title_display' => 'invisible',
              '#empty_option' => $this->t('- None -'),
            ];
          }

          foreach ($languages as $i => $language) {
            $form[$field]['table'][$rows][$language] = [
              '#type' => 'select',
              '#options' => $options[$language],
              '#title' => $this->t('@lang options', ['@lang' => $language]),
              '#title_display' => 'invisible',
              '#empty_option' => $this->t('- None -'),
            ];
          }
          $rows++;
        }
      }
      $this->step = 'completed';
    }
    return $form;
  }

  /**
   * Get list of languages as assoc array.
   *
   * @return array
   *   Assoc array of languages keyed by lang code, value is language name.
   */
  protected function getLanguageList() {
    $languages = \Drupal::service('language_manager')
      ->getLanguages(LanguageInterface::STATE_CONFIGURABLE);
    $language_list = [];
    foreach ($languages as $lang_code => $language) {
      /** @var \Drupal\Core\Language\Language $language */
      $language_list[$lang_code] = $language->getName();
    }
    return $language_list;
  }

  /**
   * Wrapper function for filterFieldsRecursively.
   *
   * Use for filtering only equivalent fields.
   *
   * @param object $gc_field
   *   Type of field in GatherContent.
   * @param string $content_type
   *   Name of Drupal content type.
   *
   * @return array
   *   Associative array with equivalent fields.
   */
  protected function filterFields($gc_field, $content_type) {
    $fields = $this->filterFieldsRecursively($gc_field, $content_type);

    if ($gc_field->type === 'text'
      && $gc_field->plainText
    ) {
      $fields['title'] = 'Title';
    }

    return $fields;
  }

  /**
   * Helper function.
   *
   * Use for filtering only equivalent fields.
   *
   * @param object $gc_field
   *   Type of field in GatherContent.
   * @param string $content_type
   *   Name of Drupal content type.
   * @param string $entity_type
   *   Name of Drupal Entity type.
   * @param array $nested_ids
   *   Nested ID array.
   * @param string $bundle_label
   *   Bundle label string.
   *
   * @return array
   *   Associative array with equivalent fields.
   */
  protected function filterFieldsRecursively($gc_field, $content_type, $entity_type = 'node', array $nested_ids = [], $bundle_label = '') {
    $mapping_array = [
      'files' => [
        'file',
        'image',
        'entity_reference_revisions',
      ],
      'section' => [
        'text_long',
        'entity_reference_revisions',
      ],
      'text' => [
        'text',
        'text_long',
        'text_with_summary',
        'string_long',
        'string',
        'email',
        'telephone',
        'date',
        'datetime',
        'entity_reference_revisions',
      ],
      'choice_radio' => [
        'string',
        'entity_reference',
        'entity_reference_revisions',
      ],
      'choice_checkbox' => [
        'list_string',
        'entity_reference',
        'entity_reference_revisions',
      ],
    ];
    $entityFieldManager = \Drupal::service('entity_field.manager');
    $entityTypeManager = \Drupal::entityTypeManager();

    /** @var \Drupal\Core\Field\FieldDefinitionInterface[] $instances */
    $instances = $entityFieldManager->getFieldDefinitions($entity_type, $content_type);

    $fields = [];
    // Fields.
    foreach ($instances as $name => $instance) {
      if (substr_compare($name, 'field', 0, 5) <> 0 && !in_array($name, ['body'])) {
        continue;
      }
      if (in_array($instance->getType(), $mapping_array[$gc_field->type])) {
        // Constrains:
        // - do not map plain text (Drupal) to rich text (GC).
        // - do not map radios (GC) to text (Drupal),
        // if widget isn't provided by select_or_other module.
        // - do not map section (GC) to plain text (Drupal).
        // - map only taxonomy entity reference (Drupal) to radios
        // and checkboxes (GC).
        switch ($gc_field->type) {
          case 'text':
            if ($gc_field->plainText && in_array($instance->getType(), [
              'string',
              'string_long',
              'email',
              'telephone',
            ])) {
              continue 2;
            }
            break;

          case 'section':
            if (in_array($instance->getType(), [
              'string',
              'string_long',
            ])) {
              continue 2;
            }
            break;

          case 'choice_radio':
          case 'choice_checkbox':
            if ($instance->getSetting('handler') !== 'default:taxonomy_term') {
              continue 2;
            }
            break;
        }

        if ($instance->getType() === 'entity_reference_revisions') {
          $settings = $instance->getSetting('handler_settings');

          if (!empty($settings['target_bundles'])) {
            $bundles = $settings['target_bundles'];
            $target_type = $instance->getFieldStorageDefinition()
              ->getSetting('target_type');
            $bundle_entity_type = $entityTypeManager
              ->getStorage($target_type)
              ->getEntityType()
              ->get('bundle_entity_type');

            $new_nested_ids = $nested_ids;
            $new_nested_ids[] = $instance->id();

            foreach ($bundles as $bundle) {
              $new_bundle_label = ((!empty($bundle_label)) ? $bundle_label . ' - ' : '') . $instance->getLabel();
              $bundle_name = $entityTypeManager
                ->getStorage($bundle_entity_type)
                ->load($bundle)
                ->label();

              $new_bundle_label .= ' (' . $bundle_name . ')';

              $targetFields = $this->filterFieldsRecursively($gc_field, $bundle, $target_type, $new_nested_ids, $new_bundle_label);

              if (!empty($targetFields)) {
                $fields = $fields + $targetFields;
              }
            }
          }
        }
        else {
          $key = $instance->id();

          if (!empty($nested_ids)) {
            $nested_ids[] = $instance->id();
            $key = implode('||', $nested_ids);
          }

          $fields[$key] = ((!empty($bundle_label)) ? $bundle_label . ' - ' : '') . $instance->getLabel();
        }
      }
    }

    return $fields;
  }

  /**
   * Return only supported metatag fields.
   *
   * @param object $gathercontent_field
   *   Object of field from GatherContent.
   *
   * @return array
   *   Array of supported metatag fields.
   */
  protected function filterMetatags($gathercontent_field) {
    if ($gathercontent_field->type === 'text' && $gathercontent_field->plainText) {
      return [
        'title' => t('Title'),
        'description' => t('Description'),
        'abstract' => t('Abstract'),
        'keywords' => t('Keywords'),
      ];
    }

    else {
      return [];
    }
  }

  /**
   * Ajax callback for mapping multistep form.
   *
   * @return array
   *   Array of form elements.
   *
   * @inheritdoc
   */
  public function getMappingTable(array &$form, FormStateInterface $form_state) {
    $this->contentType = $form_state->getValue('content_type');
    $fields = $this->getAllEntityReferenceFields();
    $form['mapping']['#attached']['drupalSettings']['gathercontent'] = (empty($fields) ? NULL : $fields);
    $form_state->setRebuild(TRUE);
    return $form['mapping'];
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    if ($form_state->getTriggeringElement()['#id'] == 'edit-submit') {
      if ($this->step === 'field_mapping') {
        $form_definition_elements = [
          'return',
          'form_build_id',
          'form_token',
          'form_id',
          'op',
        ];
        $non_data_elements = array_merge($form_definition_elements, [
          'content_type',
          'id',
          'updated',
          'gathercontent_project',
          'gathercontent_template',
        ]);

        $mapping_data = [];
        foreach ($form_state->getValues() as $key => $value) {
          if (!in_array($key, $non_data_elements) && substr_compare($key, 'tab', 0, 3) === 0) {
            $mapping_data[$key] = $value;
          }
        }
        // Check if is translatable.
        /** @var \Drupal\gathercontent\Entity\MappingInterface $mapping */
        $mapping = $this->entity;
        $content_type = (empty($mapping->getContentType()) ? $form_state->getValue('content_type') : $mapping->getContentType());
        $translatable = \Drupal::moduleHandler()
          ->moduleExists('content_translation')
          && \Drupal::service('content_translation.manager')
            ->isEnabled('node', $content_type);
        // Validate if each language is used only once
        // for translatable content types.
        $content_lang = [];
        $metatag_lang = [];
        if ($translatable) {
          foreach ($mapping_data as $tab_id => $tab) {
            $tab_type = (isset($tab['type']) ? $tab['type'] : 'content');
            if ($tab['language'] != 'und') {
              if (!in_array($tab['language'], ${$tab_type . '_lang'})) {
                ${$tab_type . '_lang'}[] = $tab['language'];
              }
              else {
                $element = $tab_id . '[language]';
                $form_state->setErrorByName($element, $this->t('Each language can be used only once'));
              }
            }
          }
        }

        // Validate if each field is used only once.
        $content_fields = [];
        $metatag_fields = [];
        if ($translatable) {
          foreach ($content_lang as $lang) {
            $content_fields[$lang] = [];
          }
          foreach ($metatag_lang as $lang) {
            $metatag_fields[$lang] = [];
          }
          $content_fields['und'] = $metatag_fields['und'] = [];
        }
        foreach ($mapping_data as $tab_id => $tab) {
          $tab_type = (isset($tab['type']) ? $tab['type'] : 'content');
          if (isset($tab['elements'])) {
            foreach ($tab['elements'] as $k => $element) {
              if (empty($element)) {
                continue;
              }
              if ($translatable) {
                if (!in_array($element, ${$tab_type . '_fields'}[$tab['language']])) {
                  ${$tab_type . '_fields'}[$tab['language']][] = $element;
                }
                else {
                  if (!strpos($element, '||')) {
                    $form_state->setErrorByName($tab_id,
                      $this->t('A GatherContent field can only be mapped to a single Drupal field. So each field can only be mapped to once.'));
                  }
                }
              }
              else {
                if (!in_array($element, ${$tab_type . '_fields'})) {
                  ${$tab_type . '_fields'}[] = $element;
                }
                else {
                  if (!strpos($element, '||')) {
                    $form_state->setErrorByName($tab_id, $this->t('A GatherContent field can only be mapped to a single Drupal field. So each field can only be mapped to once.'));
                  }
                }
              }
            }
          }
        }

        // Validate if at least one field in mapped.
        if (!$translatable && empty($content_fields) && empty($metatag_fields)) {
          $form_state->setErrorByName('form', t('You need to map at least one field to create mapping.'));
        }
        elseif ($translatable &&
          count($content_fields) === 1
          && empty($content_fields['und'])
          && empty($metatag_fields['und'])
          && count($metatag_fields) === 1
        ) {
          $form_state->setErrorByName('form', t('You need to map at least one field to create mapping.'));
        }

        // Validate if title is mapped for translatable content.
        if ($translatable) {
          foreach ($content_fields as $k => $lang_fields) {
            if (!in_array('title', $lang_fields) && $k != 'und') {
              $form_state->setErrorByName('form', t('You have to map Drupal Title field for translatable content.'));
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
    if ($form_state->getTriggeringElement()['#id'] == 'edit-submit') {
      /** @var \Drupal\gathercontent\Entity\MappingInterface $mapping */
      $mapping = $this->entity;
      $entityStorage = \Drupal::entityTypeManager()
        ->getStorage('taxonomy_term');
      if ($this->step === 'field_mapping') {
        $this->step = 'er_mapping';
        $mapping_data = $this->extractMappingData($form_state->getValues());
        if ($this->new) {
          $this->contentType = $form_state->getValue('content_type');
        }
        else {
          $this->contentType = $mapping->getContentType();
        }

        $this->erImportType = $form_state->getValue('er_mapping_type');
        $this->getEntityReferenceFields();

        if (empty($this->entityReferenceFields) || $this->erImportType === 'automatic') {
          $this->skip = TRUE;
        }

        if (!$this->skip) {
          $form_state->setRebuild(TRUE);
        }
      }

      if ($this->step === 'completed' || $this->skip) {
        $this->erImported = 0;
        if ($this->new) {
          $mapping->setContentType($this->contentType);
          $content_types = node_type_get_names();
          $mapping->setContentTypeName($content_types[$this->contentType]);
        }
        $mapping->setData(serialize($this->mappingData));
        $mapping->setUpdatedDrupal(time());

        $template = $this->client->templateGet($mapping->getGathercontentTemplateId());

        $mapping->setTemplate(serialize($this->client->getBody(TRUE)));
        $mapping->save();

        // We need to modify field for checkboxes and field instance for radios.
        foreach ($template->config as $i => $fieldset) {
          if ($fieldset->hidden === FALSE) {
            foreach ($fieldset->elements as $gc_field) {
              $local_field_id = $this->mappingData[$fieldset->id]['elements'][$gc_field->id];
              if ($gc_field->type === 'choice_checkbox') {
                if (!empty($local_field_id)) {
                  $local_options = [];
                  foreach ($gc_field->options as $option) {
                    $local_options[$option['name']] = $option['label'];
                  }
                  $field_info = FieldConfig::load($local_field_id);
                  if ($field_info->getType() === 'entity_reference') {
                    if ($this->erImportType === 'automatic') {
                      $this->automaticTermsGenerator($field_info, $local_options, isset($this->mappingData[$fieldset->id]['language']) ? $this->mappingData[$fieldset->id]['language'] : LanguageInterface::LANGCODE_NOT_SPECIFIED);
                    }
                  }
                  else {
                    $field_info = $field_info->getFieldStorageDefinition();
                    // Make the change.
                    $field_info->setSetting('allowed_values', $local_options);
                    try {
                      $field_info->save();
                    }
                    catch (\Exception $e) {
                      // Log something.
                    }
                  }
                }
              }
              elseif ($gc_field->type === 'choice_radio') {
                if (!empty($mapping_data[$fieldset->id]['elements'][$gc_field->id])) {
                  $local_options = [];
                  foreach ($gc_field->options as $option) {
                    if (!isset($option['value'])) {
                      $local_options[$option['name']] = $option['label'];
                    }
                  }
                  $field_info = FieldConfig::load($local_field_id);
                  if ($field_info->getType() === 'entity_reference') {
                    if ($this->erImportType === 'automatic') {
                      $this->automaticTermsGenerator($field_info, $local_options, isset($this->mappingData[$fieldset->id]['language']) ? $this->mappingData[$fieldset->id]['language'] : LanguageInterface::LANGCODE_NOT_SPECIFIED);
                    }
                  }
                  else {
                    $new_local_options = [];
                    foreach ($local_options as $name => $label) {
                      $new_local_options[] = $name . '|' . $label;
                    }
                    $entity = \Drupal::entityTypeManager()
                      ->getStorage('entity_form_display')
                      ->load('node.' . $mapping->getContentType() . '.default');
                    /** @var \Drupal\Core\Entity\Entity\EntityFormDisplay $entity */
                    $entity->getRenderer($field_info->getName())
                      ->setSetting('available_options', implode("\n", $new_local_options));
                  }
                }
              }
            }
          }
        }

        // If we went through mapping of er, we want to save them.
        if (!$this->skip) {
          $form_state->cleanValues();
          $fields = $form_state->getValues();
          // Prepare options for every language for every field.
          $options = $this->prepareOptions($template);

          foreach ($fields as $field_name => $tables) {
            $vid = $this->getVocabularyId($field_name);

            // Check if gathercontent_options_ids field exists.
            $this->gcOptionIdsFieldExists($vid);

            foreach ($tables as $table) {
              foreach ($table as $row) {
                $languages = $this->getAvailableLanguages($row);
                if ($this->erImportType === 'manual') {
                  $this->manualErImport($languages, $entityStorage, $row);
                }
                else {
                  $this->semiErImport($languages, $entityStorage, $row, $options, $vid);
                }
              }
            }
          }
        }

        if ($this->new) {
          drupal_set_message(t('Mapping has been created.'));
        }
        else {
          drupal_set_message(t('Mapping has been updated.'));
        }

        if (!empty($this->entityReferenceFields)) {
          drupal_set_message($this->formatPlural($this->erImported, '@count term was imported', '@count terms were imported'));
        }

        $form_state->setRedirect('entity.gathercontent_mapping.collection');
      }
    }
  }

  /**
   * Get list of entity reference fields with mapping to GatherContent.
   */
  public function getEntityReferenceFields() {
    $this->entityReferenceFields = [];
    /** @var \Drupal\Core\Field\FieldDefinitionInterface[] $instances */
    $instances = \Drupal::service('entity_field.manager')
      ->getFieldDefinitions('node', $this->contentType);
    foreach ($instances as $instance) {
      if ($instance->getType() === 'entity_reference' && $instance->getSetting('handler') === 'default:taxonomy_term') {
        foreach ($this->mappingData as $tabName => $tab) {
          $gcField = array_search($instance->getName(), $tab['elements']);
          if (empty($gcField)) {
            continue 2;
          }
          if (isset($tab['language'])) {
            $this->entityReferenceFields[$instance->getName()][$tab['language']]['name'] = $gcField;
            $this->entityReferenceFields[$instance->getName()][$tab['language']]['tab'] = $tabName;
          }
          else {
            $this->entityReferenceFields[$instance->getName()][LanguageInterface::LANGCODE_NOT_SPECIFIED]['name'] = $gcField;
            $this->entityReferenceFields[$instance->getName()][LanguageInterface::LANGCODE_NOT_SPECIFIED]['tab'] = $tabName;
          }
        }
      }
    }
  }

  /**
   * Get list of entity reference fields with mapping to GatherContent.
   */
  public function getAllEntityReferenceFields() {
    $entityReferenceFields = [];
    /** @var \Drupal\Core\Field\FieldDefinitionInterface[] $instances */
    $instances = \Drupal::service('entity_field.manager')
      ->getFieldDefinitions('node', $this->contentType);
    foreach ($instances as $instance) {
      if ($instance->getType() === 'entity_reference' && $instance->getSetting('handler') === 'default:taxonomy_term') {
        $entityReferenceFields[] = $instance->id();
      }
    }

    return $entityReferenceFields;
  }

  /**
   * Generate automatically terms for local field from GatherContent options.
   *
   * @param \Drupal\field\Entity\FieldConfig $handlerSettings
   *   Field config for local field.
   * @param array $localOptions
   *   Array of remote options.
   * @param string $langcode
   *   The language of the generated term.
   */
  public function automaticTermsGenerator(FieldConfig $handlerSettings, array $localOptions, $langcode) {
    $settings = $handlerSettings->getSetting('handler_settings');
    /** @var \Drupal\taxonomy\Entity\Term[] $terms */
    if (!empty($settings['auto_create_bundle'])) {
      $vid = $settings['auto_create_bundle'];
    }
    else {
      $vid = reset($settings['target_bundles']);
    }

    // Check if field exists.
    $this->gcOptionIdsFieldExists($vid);

    foreach ($localOptions as $id => $localOption) {
      $query = \Drupal::entityQuery('taxonomy_term');
      $group = $query->orConditionGroup()
        ->condition('gathercontent_option_ids', $id)
        ->condition('name', $localOption);
      $term_ids = $query->condition($group)
        ->condition('vid', $vid)
        ->condition('langcode', $langcode)
        ->execute();
      $term_id = array_shift($term_ids);
      if (!empty($term_id)) {
        $term = Term::load($term_id);
        if ($langcode === LanguageInterface::LANGCODE_NOT_SPECIFIED) {
          if ($term->label() !== $localOption) {
            $term->setName($localOption);
          }
          if (!in_array($id, $term->get('gathercontent_option_ids')
            ->getValue())
          ) {
            $term->gathercontent_option_ids->appendItem($id);
          }
        }
        else {
          if ($term->getTranslation($langcode)->label() !== $localOption) {
            $term->getTranslation($langcode)->setName($localOption);
          }
          if (!in_array($id, $term->getTranslation($langcode)->gathercontent_option_ids->getValue())
          ) {
            $term->getTranslation($langcode)->gathercontent_option_ids->appendItem($id);
          }
        }

        $term->save();
        $this->erImported++;
      }
      else {
        $term_values = [
          'vid' => $vid,
          'langcode' => $langcode,
        ];
        $term = Term::create($term_values);

        $term->setName($localOption);
        $term->set('gathercontent_option_ids', $id);
        $term->save();
        $this->erImported++;
      }
    }
  }

  /**
   * Prepare options for every language for every field.
   *
   * @param \Cheppers\GatherContent\DataTypes\Template $template
   *   GatherContent template object.
   *
   * @return array
   *   Array with options.
   */
  public function prepareOptions(Template $template) {
    $options = [];
    foreach ($this->entityReferenceFields as $field => $gcMapping) {
      foreach ($gcMapping as $lang => $fieldSettings) {
        foreach ($template->config as $tab) {
          if ($tab->id === $fieldSettings['tab']) {
            foreach ($tab->elements as $element) {
              if ($element->id === $fieldSettings['name']) {
                foreach ($element->options as $option) {
                  if (!isset($option->value)) {
                    $options[$option->name] = $option->label;
                  }
                }
              }
            }
          }
        }
      }
    }
    return $options;
  }

  /**
   * Validate if gathercontent_option_ids field exists on specified vocabulary.
   *
   * If field doesn't exists, create it for specified vocabulary.
   *
   * @param string $vid
   *   Taxonomy vocabulary identifier.
   */
  public function gcOptionIdsFieldExists($vid) {
    if ($this->entityTypeManager->hasDefinition('taxonomy_term')) {
      $entityFieldManager = \Drupal::service('entity_field.manager');
      $definitions = $entityFieldManager->getFieldStorageDefinitions('taxonomy_term');
      if (!isset($definitions['gathercontent_option_ids'])) {
        FieldStorageConfig::create([
          'field_name' => 'gathercontent_option_ids',
          'entity_type' => 'taxonomy_term',
          'type' => 'string',
          'cardinality' => FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED,
          'locked' => TRUE,
          'persist_with_no_fields' => TRUE,
          'settings' => [
            'is_ascii' => FALSE,
            'case_sensitive' => FALSE,
          ],
        ])->save();
      }

      $field_config = FieldConfig::loadByName('taxonomy_term', $vid, 'gathercontent_option_ids');
      if (is_null($field_config)) {
        FieldConfig::create([
          'field_name' => 'gathercontent_option_ids',
          'entity_type' => 'taxonomy_term',
          'bundle' => $vid,
          'label' => 'GatherContent Option IDs',
        ])->save();
      }
    }
  }

  /**
   * Handle manual type of taxonomy terms.
   *
   * @param array|null $languages
   *   Array with languages available for mapping.
   * @param \Drupal\Core\Entity\EntityStorageInterface $entityStorage
   *   Storage object for taxonomy terms.
   * @param array $row
   *   Array with mapping options.
   */
  public function manualErImport($languages, EntityStorageInterface $entityStorage, array $row) {
    if (!empty($languages) && !empty($row['terms'])) {
      $terms = $entityStorage->loadByProperties(['gathercontent_option_ids' => $row[$languages[0]]]);
      /** @var \Drupal\taxonomy\Entity\Term $term */
      $term = array_shift($terms);
      // If term already exists.
      if (!empty($term)) {
        // If term was changed, remove option ids for every
        // language.
        if ($term->id() !== $row['terms']) {
          // We don't know how many languages are translated.
          $translation_languages = $term->getTranslationLanguages(TRUE);
          foreach ($translation_languages as $language) {
            if ($term->hasTranslation($language) && !empty($row[$language])) {
              $option_ids = $term->getTranslation($language)
                ->get('gathercontent_option_ids');
              foreach ($option_ids as $i => $option_id) {
                if ($option_id == $row[$language]) {
                  unset($option_ids[$i]);
                }
              }
              $term->getTranslation($language)
                ->set('gathercontent_option_ids', $option_ids);
            }
          }
        }
      }

      // Set new values to correct term.
      $term = Term::load($row['terms']);
      if (!empty($languages)) {
        foreach ($languages as $language) {
          $term->getTranslation($language)
            ->set('gathercontent_option_ids', $row[$language]);
        }
      }
      $term->save();
      $this->erImported++;
    }
    elseif (empty($languages) && !empty($row['terms'])) {
      $und_lang_value = $row[LanguageInterface::LANGCODE_NOT_SPECIFIED];
      if (!empty($und_lang_value)) {
        $terms = $entityStorage->loadByProperties(['gathercontent_option_ids' => $und_lang_value]);
        /** @var \Drupal\taxonomy\Entity\Term $term */
        $term = array_shift($terms);
        // If term already exists.
        if (!empty($term)) {
          // If term was changed, remove option ids for every
          // language.
          if ($term->id() !== $row['terms']) {
            $option_ids = $term->get('gathercontent_option_ids');
            foreach ($option_ids as $i => $option_id) {
              if ($option_id == $und_lang_value) {
                unset($option_ids[$i]);
              }
            }
            $term->set('gathercontent_option_ids', $option_ids);
          }
        }
        // Set new values to correct term.
        $term = Term::load($row['terms']);
        $term->set('gathercontent_option_ids', $und_lang_value);
        $term->save();
        $this->erImported++;
      }
    }
  }

  /**
   * Handle semiautomatic import of taxonomy terms.
   *
   * @param array|null $languages
   *   Array with languages available for mapping.
   * @param \Drupal\Core\Entity\EntityStorageInterface $entityStorage
   *   Storage object for taxonomy terms.
   * @param array $row
   *   Array with mapping options.
   * @param array $options
   *   GatherContent options for every language and every field.
   * @param string $vid
   *   Taxonomy vocabulry identifier.
   */
  public function semiErImport($languages, EntityStorageInterface $entityStorage, array $row, array $options, $vid) {
    if (!empty($languages)) {
      $terms = $entityStorage->loadByProperties(['gathercontent_option_ids' => $row[$languages[0]]]);
      /** @var \Drupal\taxonomy\Entity\Term $term */
      $term = array_shift($terms);
      if (!empty($term)) {
        foreach ($languages as $language) {
          if (!empty($row[$language]) && $term->hasTranslation($language) && $term->getTranslation($language)->label() !== $options[$row[$language]]) {
            $term->getTranslation($language)
              ->setName($options[$row[$language]]);
          }
        }
        $term->save();
        $this->erImported++;
      }
      else {
        $term = Term::create([
          'vid' => $vid,
        ]);
        foreach ($languages as $language) {
          if (!empty($row[$language])) {
            if (!$term->hasTranslation($language)) {
              $term->addTranslation($language);
            }
            $term->getTranslation($language)
              ->set('gathercontent_option_ids', $row[$language]);
            $term->getTranslation($language)
              ->setName($options[$row[$language]]);
          }
        }
        if (!empty($term->getTranslationLanguages())) {
          $term->save();
          $this->erImported++;
        }
      }
    }
    else {
      /** @var \Drupal\taxonomy\Entity\Term $term */
      $und_lang_value = $row[LanguageInterface::LANGCODE_NOT_SPECIFIED];
      if (!empty($und_lang_value)) {
        $terms = $entityStorage->loadByProperties(['gathercontent_option_ids' => $und_lang_value]);
        $term = array_shift($terms);
        if (!empty($term)) {
          if ($term->label() !== $options[$und_lang_value]) {
            $term->setName($options[$und_lang_value]);
          }
          $term->save();
          $this->erImported++;
        }
        else {
          $term = Term::create([
            'vid' => $vid,
            'gathercontent_option_ids' => $und_lang_value,
          ]);
          $term->setName($options[$und_lang_value]);
          $term->save();
          $this->erImported++;
        }
      }
    }
  }

  /**
   * Get available languages from currect row.
   *
   * @param array $row
   *   Currect row from mapping.
   *
   * @return array
   *   Array with available languages.
   */
  public function getAvailableLanguages(array $row) {
    $languages = array_keys($row);

    foreach ($languages as $i => $language) {
      if ($language === 'und') {
        unset($languages[$i]);
      }
      elseif ($language === 'terms') {
        unset($languages[$i]);
      }
    }
    return $languages;
  }

  /**
   * Get vocabulary identifier for field in content type.
   *
   * @param string $field_name
   *   Name of local field.
   *
   * @return string
   *   Identifier of vocabulary.
   */
  public function getVocabularyId($field_name) {
    // Load vocabulary.
    $field_config = FieldConfig::loadByName('node', $this->contentType, $field_name);
    $settings = $field_config->getSetting('handler_settings');
    /** @var \Drupal\taxonomy\Entity\Term[] $terms */
    if (!empty($settings['auto_create_bundle'])) {
      $vid = $settings['auto_create_bundle'];
      return $vid;
    }
    else {
      $vid = reset($settings['target_bundles']);
      return $vid;
    }
  }

  /**
   * Extract mapping data from submitted form values.
   *
   * @param array $formValues
   *   Array with all submitted values.
   *
   * @return array
   *   Mapping data.
   */
  public function extractMappingData(array $formValues) {
    $form_definition_elements = [
      'return',
      'form_build_id',
      'form_token',
      'form_id',
      'op',
    ];
    $non_data_elements = array_merge($form_definition_elements, [
      'gc_template',
      'content_type',
      'id',
      'updated',
      'gathercontent_project',
      'gathercontent_template',
    ]);

    $mapping_data = [];
    foreach ($formValues as $key => $value) {
      if (!in_array($key, $non_data_elements) && substr_compare($key, 'tab', 0, 3) === 0) {
        $mapping_data[$key] = $value;
      }
    }

    $this->mappingData = $mapping_data;
    return $mapping_data;
  }

  /**
   * {@inheritdoc}
   */
  protected function actions(array $form, FormStateInterface $form_state) {
    $actions = parent::actions($form, $form_state);
    $actions['submit']['#value'] = ($this->new ? $this->t('Create mapping') : $this->t('Update mapping'));
    $actions['close'] = [
      '#type' => 'submit',
      '#value' => t('Cancel'),
    ];
    unset($actions['delete']);
    return $actions;
  }

}
