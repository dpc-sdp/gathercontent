<?php

namespace Drupal\gathercontent;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\datetime\Plugin\Field\FieldType\DateTimeItemInterface;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\FieldConfigInterface;
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
      'tabId' => '',
      'fields' => [],
    ],
    'process' => [],
    'destination' => [
      'plugin' => '',
    ],
    'migration_dependencies' => [],
  ];

  protected $definitions;

  protected $mapping;

  protected $mappingData;

  protected $projectId;

  protected $templateId;

  protected $entityType;

  protected $contentType;

  protected $configFactory;

  protected $entityTypeManager;

  protected $gcImportConfig;

  protected $siteDefaultLangCode;

  /**
   * MigrationDefinitionCreator constructor.
   */
  public function __construct(ConfigFactoryInterface $configFactory, EntityTypeManagerInterface $entityTypeManager) {
    $this->configFactory = $configFactory;
    $this->entityTypeManager = $entityTypeManager;

    $this->siteDefaultLangCode = $this
      ->configFactory
      ->getEditable('system.site')
      ->get('langcode');
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
   * Create migration definitions.
   */
  public function createMigrationDefinition() {
    $migrationDefinitionIds = [];
    $this->setBasicData();
    $this->setDefinitionBaseProperties();
    $this->setDefinitionSourceProperties();
    $this->setMigrationDependencies();

    foreach ($this->definitions as $tabId => $definition) {
      $this->setDefinitionFieldProperties($tabId);
      $definitionID = $this->definitions[$tabId]['id'];

      $isNew = $this->isNewConfiguration($definitionID);
      if (!$isNew) {
        $config = $this->configFactory->getEditable('migrate_plus.migration.' . $definitionID);
        $config->delete();
      }

      $migration = Migration::create($this->definitions[$tabId]);
      $migration->save();
      $migrationDefinitionIds[] = $definitionID;
    }

    $this->mapping->set('migration_definitions', $migrationDefinitionIds);
    $this->mapping->save();
  }

  /**
   * Set the basic data for the definition.
   */
  protected function setBasicData() {
    $this->projectId = $this->mapping->getGathercontentProjectId();
    $this->templateId = $this->mapping->getGathercontentTemplateId();
    $this->contentType = $this->mapping->getContentType();
    // TODO: Make configurable.
    $this->entityType = 'node';
  }

  /**
   * Determine if the configuration is new.
   */
  protected function isNewConfiguration($definitionId): bool {
    $configuration = $this->configFactory->get($definitionId);

    return $configuration ? FALSE : TRUE;
  }

  /**
   * Set the basic source properties.
   */
  protected function setDefinitionSourceProperties() {
    foreach ($this->definitions as $tabId => $definition) {
      $this->definitions[$tabId]['source']['projectId'] = $this->projectId;
      $this->definitions[$tabId]['source']['templateId'] = $this->templateId;
      $this->definitions[$tabId]['source']['tabId'] = $tabId;
    }
  }

  /**
   * Set the field process and destination properties.
   */
  protected function setDefinitionFieldProperties(string $tabId) {
    $entityDefinition = $this->entityTypeManager->getDefinition($this->entityType);

    if (
      $this->definitions[$tabId]['langcode'] != $this->siteDefaultLangCode &&
      $this->definitions[$tabId]['langcode'] != 'und'
    ) {
      $defaultLangMigrationId = $this->getDefaultMigrationId();

      $this->definitions[$tabId]['destination']['translations'] = TRUE;
      $this->definitions[$tabId]['process'][$entityDefinition->getKey('id')] = [
        'plugin' => 'migration_lookup',
        'source' => 'id',
        'migration' => $defaultLangMigrationId,
      ];
      $this->definitions[$tabId]['process']['langcode'] = [
        'plugin' => 'default_value',
        'default_value' => $this->definitions[$tabId]['langcode'],
      ];
    }

    $this->definitions[$tabId]['destination']['plugin'] = 'gc_entity:' . $this->entityType;

    $this->definitions[$tabId]['process'][$entityDefinition->getKey('bundle')] = [
      'plugin' => 'default_value',
      'default_value' => $this->contentType,
    ];

    $labelSet = FALSE;

    foreach ($this->mappingData[$tabId]['elements'] as $elementId => $element) {
      if (!$element) {
        continue;
      }
      $fieldInfo = FieldConfig::load($element);
      $fieldType = 'string';
      $isTranslatable = TRUE;

      if (!empty($fieldInfo)) {
        $fieldType = $fieldInfo->getType();
        $isTranslatable = $fieldInfo->isTranslatable();
      }

      $element = $this->getElementLastPart($element);

      $this->definitions[$tabId]['source']['fields'][] = $elementId;
      $this->setFieldDefinition($tabId, $elementId, $element, $fieldInfo, $fieldType, $isTranslatable);

      if ($element == $entityDefinition->getKey('label')) {
        $labelSet = TRUE;
      }
    }

    if (!$labelSet) {
      $this->definitions[$tabId]['source']['fields'][] = 'item_title';
      $this->definitions[$tabId]['process'][$entityDefinition->getKey('label')] = 'item_title';
    }
  }

  /**
   * Set field definition.
   */
  private function setFieldDefinition($tabId, $elementId, $element, $fieldInfo, $fieldType, $isTranslatable) {
    switch ($fieldType) {
      case 'image':
      case 'file':
        $fileDir = $fieldInfo->getSetting('file_directory');
        $uriScheme = $fieldInfo->getFieldStorageDefinition()->getSetting('uri_scheme') . '://';

        $this->definitions[$tabId]['process'][$element] = [
          'plugin' => 'gather_content_file',
          'source' => $elementId,
          'uri_scheme' => $uriScheme,
          'file_dir' => $fileDir,
          'language' => $this->siteDefaultLangCode,
        ];

        if ($this->definitions[$tabId]['langcode'] == 'und') {
          $this->definitions[$tabId]['process'][$element]['language'] = 'und';
        }

        break;

      case 'timestamp':
        $this->definitions[$tabId]['process'][$element] = [
          'plugin' => 'callback',
          'callable' => 'strtotime',
        ];
        break;

      case 'date':
      case 'datetime':
        $this->definitions[$tabId]['process'][$element][] = [
          'plugin' => 'callback',
          'callable' => 'strtotime',
        ];
        $this->definitions[$tabId]['process'][$element][] = [
          'plugin' => 'format_date',
          'from_format' => 'U',
          'to_format' => DateTimeItemInterface::DATETIME_STORAGE_FORMAT,
        ];
        break;

      case 'text':
      case 'text_with_summary':
        $this->definitions[$tabId]['process'][$element] = [
          'plugin' => 'get',
          'source' => $elementId,
        ];

        $this->setTextFormat($tabId, $elementId, $element);
        break;

      default:
        $this->definitions[$tabId]['process'][$element] = [
          'plugin' => 'get',
          'source' => $elementId,
        ];
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

        $this->definitions[$tabId]['process'][$element] = [
          'plugin' => 'sub_process',
          'source' => $elementId,
          'process' => [
            'target_id' => [
              'plugin' => 'gather_content_taxonomy',
              'bundle' => $bundle,
              'source' => 'value',
            ],
          ],
        ];
        break;
    }

    if (
      $this->definitions[$tabId]['langcode'] != $this->siteDefaultLangCode &&
      $this->definitions[$tabId]['langcode'] != 'und' &&
      $isTranslatable
    ) {
      $this->definitions[$tabId]['process'][$element]['language'] = $this->definitions[$tabId]['langcode'];
    }
  }

  /**
   * Set the text format for the text type fields.
   */
  protected function setTextFormat(
    string $tabId,
    string $elementId,
    string $element
  ) {
    if (isset($this->mappingData[$tabId]['element_text_formats'][$elementId])
      && !empty($this->mappingData[$tabId]['element_text_formats'][$elementId])
    ) {
      unset($this->definitions[$tabId]['process'][$element]);
      $this->definitions[$tabId]['process'][$element . '/format'] = [
        'plugin' => 'default_value',
        'default_value' => $this->mappingData[$tabId]['element_text_formats'][$elementId],
      ];
      $this->definitions[$tabId]['process'][$element . '/value'] = [
        'plugin' => 'get',
        'source' => $elementId,
      ];

      if (
        $this->definitions[$tabId]['langcode'] != $this->siteDefaultLangCode &&
        $this->definitions[$tabId]['langcode'] != 'und'
      ) {
        $this->definitions[$tabId]['process'][$element . '/value']['language'] = $this->definitions[$tabId]['langcode'];
      }
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
   * Set the definition base properties.
   */
  protected function setDefinitionBaseProperties() {
    $definitions = [];

    $baseDataId = [
      $this->projectId,
      $this->templateId,
      $this->contentType,
    ];

    $baseDataLabel = [
      $this->mapping->getGathercontentProject(),
      $this->mapping->getGathercontentTemplate(),
    ];

    $tabIds = array_keys($this->mappingData);
    $language = $this->configFactory->get('system.site')->get('langcode');

    foreach ($tabIds as $tabId) {
      if (isset($this->mappingData[$tabId]['language'])) {
        $language = $this->mappingData[$tabId]['language'];
      }

      $definitions[$tabId] = self::BASIC_SCHEMA_GC_DESTINATION_CONFIG;
      $definitions[$tabId]['langcode'] = $language;
      $definitions[$tabId]['id'] = implode('_', $baseDataId) . "_$tabId";
      $definitions[$tabId]['label'] = implode('_', $baseDataLabel) . "_$language";
    }

    $this->definitions = $definitions;
  }

  /**
   * Set migration dependencies.
   */
  protected function setMigrationDependencies() {
    $defaultLangMigrationId = $this->getDefaultMigrationId();

    foreach ($this->definitions as $tabId => $tab) {
      if (
        $this->definitions[$tabId]['langcode'] != $this->siteDefaultLangCode &&
        $this->definitions[$tabId]['langcode'] != 'und'
      ) {
        $this->definitions[$tabId]['migration_dependencies']['optional'][] = $defaultLangMigrationId;
      }
    }

  }

  /**
   * Returns the main/default migration id for sub migrations.
   */
  protected function getDefaultMigrationId() {
    $defaultLangMigrationId = '';

    foreach ($this->definitions as $tabId => $tab) {
      if ($this->definitions[$tabId]['langcode'] == $this->siteDefaultLangCode) {
        $defaultLangMigrationId = $this->definitions[$tabId]['id'];
      }
    }

    return $defaultLangMigrationId;
  }

}
