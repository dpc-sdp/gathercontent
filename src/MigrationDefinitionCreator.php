<?php

namespace Drupal\gathercontent;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\datetime\Plugin\Field\FieldType\DateTimeItemInterface;
use Drupal\field\Entity\FieldConfig;
use Drupal\gathercontent\Entity\MappingInterface;
use Drupal\migrate_plus\Entity\Migration;

/**
 * Create dynamic migration definitions.
 */
class MigrationDefinitionCreator {

  const BASIC_SCHEMA_GC_DESTINATION_CONFIG = [
    'langcode' => '',
    'status' => TRUE,
    'id' => '',
    'label' => '',
    'source' => [
      'plugin' => 'gathercontent_migration',
      'projectId' => '',
      'templateId' => '',
      'templateName' => '',
      'fields' => [],
      'metatagFields' => [],
    ],
    'process' => [],
    'destination' => [
      'plugin' => '',
    ],
    'migration_dependencies' => [],
    'migration_tags' => [],
  ];

  /**
   * Mapping object.
   *
   * @var \Drupal\gathercontent\Entity\MappingInterface
   */
  protected $mapping;

  /**
   * Mapping data array.
   *
   * @var array
   */
  protected $mappingData;

  /**
   * Config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Default language.
   *
   * @var string
   */
  protected $siteDefaultLangCode;

  /**
   * List of the generated migration definition ids.
   *
   * @var array
   */
  protected $migrationDefinitionIds = [];

  /**
   * List of the collected reference fields.
   *
   * @var array
   */
  protected $collectedReferences = [];

  /**
   * MetatagQuery helper object.
   *
   * @var \Drupal\gathercontent\MetatagQuery
   */
  protected $metatagQuery;

  /**
   * Module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * Database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * MigrationDefinitionCreator constructor.
   *
   * {@inheritdoc}
   */
  public function __construct(
    ConfigFactoryInterface $configFactory,
    EntityTypeManagerInterface $entityTypeManager,
    ModuleHandlerInterface $moduleHandler,
    Connection $database,
    MetatagQuery $metatagQuery
  ) {
    $this->configFactory = $configFactory;
    $this->entityTypeManager = $entityTypeManager;
    $this->moduleHandler = $moduleHandler;
    $this->database = $database;

    $this->siteDefaultLangCode = $this
      ->configFactory
      ->getEditable('system.site')
      ->get('langcode');

    $this->metatagQuery = $metatagQuery;
  }

  /**
   * Set the mapping object.
   */
  public function setMapping(MappingInterface $mapping) {
    $this->mapping = $mapping;
    return $this;
  }

  /**
   * Set mapping data array.
   */
  public function setMappingData(array $mappingData) {
    $this->mappingData = $mappingData;
    return $this;
  }

  /**
   * Determine if the configuration is new.
   */
  protected function isNewConfiguration($definitionId): bool {
    $configuration = $this->configFactory->get($definitionId);

    return $configuration ? FALSE : TRUE;
  }

  /**
   * Return the concatenated definitions for the given template.
   */
  protected function getGroupedDefinitions() {
    $groupedData = [];

    foreach ($this->mappingData as $data) {
      $language = $this->siteDefaultLangCode;

      if (isset($data['language'])) {
        $language = $data['language'];
      }

      if (
        !empty($data['type'])
        && $data['type'] === 'metatag'
        && $this->moduleHandler->moduleExists('metatag')
        && $this->metatagQuery->checkMetatag(
          $this->mapping->getMappedEntityType(),
          $this->mapping->getContentType()
        )
      ) {
        $data['metatag_elements'] = $data['elements'];
        $data['elements'] = [];
      }

      if (empty($groupedData[$language]['data'])) {
        $groupedData[$language]['data'] = [];
      }

      $groupedData[$language]['data'] = $this->arrayMergeRecursiveDistinct($groupedData[$language]['data'], $data);
    }

    return $this->getDefinitions($groupedData);
  }

  /**
   * Return the concatenated definitions created from the grouped data.
   */
  protected function getDefinitions(array $groupedData) {
    $definitions = [];

    foreach ($groupedData as $data) {
      $definition = $this->buildMigrationDefinition([
        'projectId' => $this->mapping->getGathercontentProjectId(),
        'templateId' => $this->mapping->getGathercontentTemplateId(),
        'entityType' => $this->mapping->getMappedEntityType(),
        'contentType' => $this->mapping->getContentType(),
      ], $data['data'], 'gc_entity');

      $definitions[$definition['id']] = $definition;
    }

    return $definitions;
  }

  /**
   * Create migration definitions.
   */
  public function createMigrationDefinition() {
    $definitions = $this->getGroupedDefinitions();

    if (!$definitions) {
      return;
    }

    $this->setLanguageDefinitions($definitions);
    $this->setReferenceDependencies($definitions);

    foreach ($definitions as $definition) {
      $migration = Migration::create($definition);
      $migration->save();

      $this->migrationDefinitionIds[] = $definition['id'];
    }

    $this->mapping->set('migration_definitions', $this->migrationDefinitionIds);
    $this->mapping->save();
  }

  /**
   * Set reference migration dependencies and processes.
   */
  public function setReferenceDependencies(array &$definitions) {
    if (empty($this->collectedReferences)) {
      return;
    }

    $collectedSubDependencies = [];

    foreach ($definitions as $definitionId => $definition) {
      if (!isset($this->collectedReferences[$definitionId])) {
        continue;
      }

      $references = $this->collectedReferences[$definitionId];

      foreach ($references as $element => $target) {
        $subDependencies = [];

        foreach ($target as $reference) {
          $subDefinition = $this->buildMigrationDefinition(
            [
              'projectId' => $reference['base_data']['projectId'],
              'templateId' => $reference['base_data']['templateId'],
              'entityType' => $reference['base_data']['entityType'],
              'contentType' => $reference['base_data']['contentType'],
            ],
            $reference['data'],
            'entity_reference_revisions'
          );

          $subDependencies[$subDefinition['id']] = $subDefinition;

          $key = implode('_', [
            $reference['base_data']['projectId'],
            $reference['base_data']['templateId'],
            $reference['base_data']['entityType'],
            $reference['base_data']['contentType'],
          ]);
          $collectedSubDependencies[$key][$subDefinition['id']] = $subDefinition;
        }
        $this->setReferenceDependencies($subDependencies);

        $collected = [];

        foreach ($subDependencies as $subDefinitionId => $subDefinition) {
          $this->migrationDefinitionIds[] = $subDefinitionId;

          $definitions[$definitionId]['migration_dependencies']['optional'][] = $subDefinitionId;
          $definitions[$definitionId]['process']['collect_' . $subDefinitionId] = [
            'plugin' => 'migration_lookup',
            'migration' => $subDefinitionId,
            'source' => 'id',
          ];

          $collected[] = '@collect_' . $subDefinitionId;
        }

        if (!empty($collected)) {
          $definitions[$definitionId]['process']['get_collected_' . $element] = [
            'plugin' => 'get',
            'source' => $collected,
          ];

          $definitions[$definitionId]['process'][$element] = [
            [
              'plugin' => 'gather_content_reference_revision',
              'source' => '@get_collected_' . $element,
            ],
            [
              'plugin' => 'sub_process',
              'process' => [
                'target_id' => [
                  'plugin' => 'extract',
                  'source' => 'id',
                  'index' => [0],
                ],
                'target_revision_id' => [
                  'plugin' => 'extract',
                  'source' => 'id',
                  'index' => [1],
                ],
              ],
            ],
          ];
        }
      }
    }

    foreach ($collectedSubDependencies as $dependencies) {
      $this->setLanguageDefinitions($dependencies);

      foreach ($dependencies as $subDefinitionId => $subDefinition) {
        $migration = Migration::create($subDefinition);
        $migration->save();
      }
    }
  }

  /**
   * Builds the migration definition.
   */
  public function buildMigrationDefinition(array $baseData, array $data, $plugin) {
    $entityDefinition = $this->entityTypeManager->getDefinition($baseData['entityType']);
    $baseDataLabel = [
      $this->mapping->getGathercontentProject(),
      $this->mapping->getGathercontentTemplate(),
      $this->mapping->getMappedEntityType(),
    ];

    $language = $this->siteDefaultLangCode;

    if (isset($data['language'])) {
      $language = $data['language'];
    }

    $definition = self::BASIC_SCHEMA_GC_DESTINATION_CONFIG;
    $definition['langcode'] = $language;
    $definition['id'] = implode('_', $baseData) . "_$language";
    $definition['label'] = implode('_', $baseDataLabel) . "_$language";

    $definition['source']['projectId'] = $this->mapping->getGathercontentProjectId();
    $definition['source']['templateId'] = $this->mapping->getGathercontentTemplateId();
    $definition['source']['templateName'] = $this->mapping->getGathercontentTemplate();
    $definition['source']['metatagFields'] = $data['metatag_elements'] ?? [];

    $bundleKey = $entityDefinition->getKey('bundle');

    if (!empty($bundleKey)) {
      $definition['process'][$bundleKey] = [
        'plugin' => 'default_value',
        'default_value' => $baseData['contentType'],
      ];
    }

    $definition['destination']['plugin'] = $plugin . ':' . $baseData['entityType'];

    $this->setDefinitionFieldProperties($definition, $data, $entityDefinition);

    if (!$this->isNewConfiguration($definition['id'])) {
      $config = $this->configFactory->getEditable('migrate_plus.migration.' . $definition['id']);
      $config->delete();
    }

    return $definition;
  }

  /**
   * Set the field process and destination properties.
   */
  protected function setDefinitionFieldProperties(array &$definition, array $data, EntityTypeInterface $entityDefinition) {
    $labelSet = FALSE;

    foreach ($data['elements'] as $elementId => $element) {
      if (!$element) {
        continue;
      }

      $elementKeys = explode('||', $element, 2);

      $targetFieldInfo = NULL;
      /** @var \Drupal\field\Entity\FieldConfig $fieldInfo */
      $fieldInfo = FieldConfig::load($elementKeys[0]);
      $fieldType = 'string';
      $isTranslatable = TRUE;
      $isMultiple = FALSE;

      if (!empty($fieldInfo)) {
        $fieldType = $fieldInfo->getType();
        $isTranslatable = $fieldInfo->isTranslatable();
        $isMultiple = $fieldInfo->getFieldStorageDefinition()->isMultiple();
      }

      $element = $this->getElementLastPart($elementKeys[0]);

      if (
        $element == $entityDefinition->getKey('label') ||
        $entityDefinition->getKey('label') === FALSE
      ) {
        $labelSet = TRUE;
      }

      if (!empty($elementKeys[1])) {
        $data['elements'][$elementId] = $elementKeys[1];
        $subElementKeys = explode('||', $elementKeys[1]);

        $targetFieldInfo = FieldConfig::load($subElementKeys[0]);
      }

      $definition['source']['fields'][] = $elementId;
      $this->setFieldDefinition($definition, $data, $elementId, $element, $fieldInfo, $fieldType, $targetFieldInfo, $isTranslatable, $isMultiple);
    }

    if (!$labelSet) {
      $definition['source']['fields'][] = 'item_title';
      $definition['process'][$entityDefinition->getKey('label')] = 'item_title';
    }

    if (
      !empty($data['metatag_elements'])
      && $this->moduleHandler->moduleExists('metatag')
      && $this->metatagQuery->checkMetatag(
        $this->mapping->getMappedEntityType(),
        $this->mapping->getContentType()
      )
    ) {
      $metatagField = $this->metatagQuery->getFirstMetatagField(
        $this->mapping->getMappedEntityType(),
        $this->mapping->getContentType()
      );
      $definition['source']['fields'][] = 'meta_tags';
      $definition['process'][$metatagField] = 'meta_tags';
    }
  }

  /**
   * Set field definition.
   */
  private function setFieldDefinition(array &$definition, array $data, $elementId, $element, EntityInterface $fieldInfo = NULL, string $fieldType, EntityInterface $targetFieldInfo = NULL, bool $isTranslatable, bool $isMultiple) {
    switch ($fieldType) {
      case 'image':
      case 'file':
        $fileDir = $fieldInfo->getSetting('file_directory');
        $uriScheme = $fieldInfo->getFieldStorageDefinition()->getSetting('uri_scheme') . '://';

        $definition['process'][$element] = [
          'plugin' => 'gather_content_file',
          'source' => $elementId,
          'uri_scheme' => $uriScheme,
          'file_dir' => $fileDir,
          'language' => $this->siteDefaultLangCode,
        ];

        if ($definition['langcode'] == 'und') {
          $definition['process'][$element]['language'] = 'und';
        }

        break;

      case 'timestamp':
        $definition['process'][$element] = [
          'plugin' => 'callback',
          'callable' => 'strtotime',
        ];
        break;

      case 'date':
      case 'datetime':
        $definition['process'][$element][] = [
          'plugin' => 'callback',
          'callable' => 'strtotime',
        ];
        $definition['process'][$element][] = [
          'plugin' => 'format_date',
          'from_format' => 'U',
          'to_format' => DateTimeItemInterface::DATETIME_STORAGE_FORMAT,
        ];
        break;

      case 'text':
      case 'text_long':
      case 'text_with_summary':
        $this->setTextFieldDefinition($definition, $element, $elementId, $isMultiple);
        $this->setTextFormat($definition, $data, $elementId, $element, $isMultiple);
        break;

      case 'entity_reference':
        if (!empty($fieldInfo->getSetting('handler_settings')['auto_create_bundle'])) {
          $bundle = $fieldInfo->getSetting('handler_settings')['auto_create_bundle'];
        }
        else {
          $handler_settings = $fieldInfo->getSetting('handler_settings');
          $handler_settings = reset($handler_settings);
          $bundle = array_shift($handler_settings);
        }

        $definition['process'][$element] = [
          'plugin' => 'sub_process',
          'source' => $elementId,
          'process' => [
            'target_id' => [
              'plugin' => 'gather_content_taxonomy',
              'bundle' => $bundle,
              'source' => 'gc_id',
            ],
          ],
        ];
        break;

      case 'entity_reference_revisions':
        $targetEntityBundle = $targetFieldInfo->getTargetBundle();
        $language = $this->siteDefaultLangCode;

        if (isset($data['language'])) {
          $language = $data['language'];
        }

        $this->collectedReferences[$definition['id']][$element][$targetEntityBundle]['data']['language'] = $language;
        $this->collectedReferences[$definition['id']][$element][$targetEntityBundle]['data']['elements'][$elementId] = $data['elements'][$elementId];

        if (!empty($data['element_text_formats'][$elementId])) {
          $this->collectedReferences[$definition['id']][$element][$targetEntityBundle]['data']['element_text_formats'][$elementId] = $data['element_text_formats'][$elementId];
        }

        $this->collectedReferences[$definition['id']][$element][$targetEntityBundle]['base_data'] = [
          'projectId' => $this->mapping->getGathercontentProjectId(),
          'templateId' => $this->mapping->getGathercontentTemplateId(),
          'entityType' => $targetFieldInfo->getTargetEntityTypeId(),
          'contentType' => $targetEntityBundle,
        ];
        $this->collectedReferences[$definition['id']][$element][$targetEntityBundle]['reference_data'][] = $elementId;
        break;

      default:
        $this->setTextFieldDefinition($definition, $element, $elementId, $isMultiple);
        break;
    }

    if (
      isset($definition['process'][$element])
      && $definition['langcode'] != $this->siteDefaultLangCode
      && $definition['langcode'] != 'und'
      && $isTranslatable
    ) {
      $definition['process'][$element]['language'] = $definition['langcode'];
    }
  }

  /**
   * Set the text format for the text type fields.
   */
  protected function setTextFormat(array &$definition, array $data, string $elementId, string $element, bool $isMultiple) {
    if (isset($data['element_text_formats'][$elementId])
      && !empty($data['element_text_formats'][$elementId])
      && isset($definition['process'][$element])
    ) {
      if (!$isMultiple) {
        $origElement = $definition['process'][$element];
        unset($definition['process'][$element]);

        $definition['process'][$element . '/format'] = [
          'plugin' => 'default_value',
          'default_value' => $data['element_text_formats'][$elementId],
        ];
        $definition['process'][$element . '/value'] = $origElement;
      }
      else {
        $definition['process'][$element]['process']['format'] = [
          'plugin' => 'default_value',
          'default_value' => $data['element_text_formats'][$elementId],
        ];
      }

      if (
        $definition['langcode'] != $this->siteDefaultLangCode &&
        $definition['langcode'] != 'und'
      ) {
        if (!$isMultiple) {
          $definition['process'][$element . '/value']['language'] = $definition['langcode'];
        }
        else {
          $definition['process'][$element]['process']['language'] = $definition['langcode'];
        }
      }
    }
  }

  /**
   * Set the text definition. Set concatenation if needed.
   */
  protected function setTextFieldDefinition(array &$definition, string $element, string $elementId, bool $isMultiple) {
    // Check if the field has a /value element to support different formats.
    $key = $element . '/value';
    if (!isset($definition['process'][$key])) {
      $key = $element;
    }

    if (isset($definition['process'][$key])) {
      if (!$isMultiple) {
        $definition['process'][$key]['plugin'] = 'concat';

        if (!is_array($definition['process'][$key]['source'])) {
          $definition['process'][$key]['source'] = [
            $definition['process'][$key]['source'],
          ];
        }

        $definition['process'][$key]['source'][] = $elementId;
        $definition['process'][$key]['delimiter'] = ' ';
      }
      else {
        $concatFieldName = 'concat_' . $element;

        if (!array_key_exists($concatFieldName, $definition['process'])) {
          $concatElement[$concatFieldName] = [
            'plugin' => 'gather_content_concat',
            'source' => [
              $definition['process'][$element]['source'],
            ],
            'delimiter' => ' ',
          ];

          $definition['process'] = $concatElement + $definition['process'];
          $definition['process'][$element]['source'] = '@' . $concatFieldName;
        }

        $definition['process'][$concatFieldName]['source'][] = $elementId;
      }

      return;
    }

    if ($isMultiple) {
      $definition['process'][$element] = [
        'plugin' => 'gather_content_sub_process',
        'source' => $elementId,
        'process' => [
          'value' => 'value',
        ],
      ];
    }
    else {
      $definition['process'][$element] = [
        'plugin' => 'gather_content_get',
        'source' => $elementId,
      ];
    }
  }

  /**
   * Return the entity field's name.
   */
  protected function getElementLastPart(string $element) {
    if (strpos($element, '.')) {
      $parts = explode('.', $element);
      return end($parts);
    }
    return $element;
  }

  /**
   * Set migration language dependencies.
   */
  protected function setLanguageDefinitions(&$definitions) {
    $defaultLangMigrationId = $this->getDefaultMigrationId($definitions);

    foreach ($definitions as $definitionId => $definition) {
      if (
        $definition['langcode'] != $this->siteDefaultLangCode &&
        $definition['langcode'] != 'und'
      ) {
        $plugin = explode(':', $definition['destination']['plugin']);
        $entityDefinition = $this->entityTypeManager->getDefinition($plugin[1]);

        $definitions[$definitionId]['destination']['translations'] = TRUE;

        if ($plugin[0] == 'entity_reference_revisions') {
          $element = $entityDefinition->getKey('id');

          $definitions[$definitionId]['process']['collect_' . $defaultLangMigrationId] = [
            'plugin' => 'migration_lookup',
            'source' => 'id',
            'migration' => $defaultLangMigrationId,
          ];

          $definitions[$definitionId]['process']['get_collected_' . $element] = [
            'plugin' => 'get',
            'source' => ['@collect_' . $defaultLangMigrationId],
          ];

          $definitions[$definitionId]['process'][$element] = [
            'plugin' => 'extract',
            'source' => '@get_collected_' . $element,
            'index' => [0, 0],
          ];
        }
        else {
          $definitions[$definitionId]['process'][$entityDefinition->getKey('id')] = [
            'plugin' => 'migration_lookup',
            'source' => 'id',
            'migration' => $defaultLangMigrationId,
          ];
        }

        $definitions[$definitionId]['process']['langcode'] = [
          'plugin' => 'default_value',
          'default_value' => $definition['langcode'],
        ];

        $definitions[$definitionId]['migration_dependencies']['optional'][] = $defaultLangMigrationId;
      }
    }
  }

  /**
   * Returns the main/default migration id for sub migrations.
   */
  protected function getDefaultMigrationId($definitions) {
    $defaultLangMigrationId = '';

    foreach ($definitions as $definition) {
      if ($definition['langcode'] == $this->siteDefaultLangCode) {
        $defaultLangMigrationId = $definition['id'];
      }
    }

    return $defaultLangMigrationId;
  }

  /**
   * Merge arrays recursively and override existing values.
   */
  protected function arrayMergeRecursiveDistinct(array &$array1, array &$array2) {
    $merged = $array1;

    foreach ($array2 as $key => &$value) {
      if (is_array($value) && isset($merged[$key]) && is_array($merged[$key])) {
        $merged[$key] = $this->arrayMergeRecursiveDistinct($merged[$key], $value);
      }
      else {
        $merged[$key] = $value;
      }
    }

    return $merged;
  }

}
