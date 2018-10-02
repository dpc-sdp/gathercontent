<?php

namespace Drupal\gathercontent;

use Drupal\Component\Render\PlainTextOutput;
use Drupal\Core\Config\ConfigFactoryInterface;
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
      'tabId' => '',
      'constants' => [
        'dst_bundle' => '',
      ],
      'fields' => [],
    ],
    'process' => [
      'type' => 'constants/dst_bundle',
    ],
    'destination' => [
      'plugin' => 'gc_entity:node',
    ],
    'migration_dependencies' => [],
  ];

  protected $definitions;

  /**
   * @var \Drupal\gathercontent\Entity\Mapping $mapping
   */
  protected $mapping;

  /**
   * Mapping data (elements, translation etc).
   *
   * @var array
   */
  protected $mappingData;

  protected $projectId;

  protected $templateId;

  protected $contentType;

  protected $configFactory;

  protected $gcImportConfig;

  protected $siteDefaultLangCode;

  public function __construct(ConfigFactoryInterface $configFactory) {
    $this->configFactory = $configFactory;

    $this->siteDefaultLangCode = $this
      ->configFactory
      ->getEditable('system.site')
      ->get('langcode');
  }

  public function setMapping(MappingInterface $mapping) {
    $this->mapping = $mapping;
    return $this;
  }

  public function setMappingData(array $mappingData) {
    $this->mappingData = $mappingData;
    return $this;
  }

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

  protected function setBasicData() {
    $this->projectId = $this->mapping->getGathercontentProjectId();
    $this->templateId = $this->mapping->getGathercontentTemplateId();
    $this->contentType = $this->mapping->getContentType();
  }

  protected function isNewConfiguration($definitionId): bool {
    $configuration = $this->configFactory->get($definitionId);

    return $configuration ? FALSE : TRUE;
  }

  protected function setDefinitionSourceProperties() {
    foreach ($this->definitions as $tabId => $definition) {
      $this->definitions[$tabId]['source']['projectId'] = $this->projectId;
      $this->definitions[$tabId]['source']['templateId'] = $this->templateId;
      $this->definitions[$tabId]['source']['tabId'] = $tabId;
      $this->definitions[$tabId]['source']['constants'] = [
        'dst_bundle' => $this->contentType,
      ];
    }
  }

  protected function setDefinitionFieldProperties(string $tabId) {
    if (
      $this->definitions[$tabId]['langcode'] != $this->siteDefaultLangCode &&
      $this->definitions[$tabId]['langcode'] != 'und'
    ) {
      $defaultLangMigrationId = $this->getDefaultMigrationId();

      $this->definitions[$tabId]['destination']['translations'] = TRUE;
      $this->definitions[$tabId]['process']['nid'] = [
        'plugin' => 'migration_lookup',
        'source' => 'id',
        'migration' => $defaultLangMigrationId,
      ];
      $this->definitions[$tabId]['process']['langcode'] = [
        'plugin' => 'default_value',
        'default_value' => $this->definitions[$tabId]['langcode'],
      ];
    }

    $titleSet = FALSE;

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
      $this->definitions[$tabId]['process'][$element] = [
        'plugin' => 'get',
        'source' => $elementId,
      ];

      switch ($fieldType) {
        case 'image':
        case 'file':
          $fileDir = $fieldInfo->getSetting('file_directory');
          $uriScheme = $fieldInfo->getFieldStorageDefinition()->getSetting('uri_scheme') . '://';

          $this->definitions[$tabId]['process'][$element]['plugin'] = 'gather_content_file';
          $this->definitions[$tabId]['process'][$element]['uri_scheme'] = $uriScheme;
          $this->definitions[$tabId]['process'][$element]['file_dir'] = $fileDir;
          $this->definitions[$tabId]['process'][$element]['language'] = $this->siteDefaultLangCode;

          if ($this->definitions[$tabId]['langcode'] == 'und') {
            $this->definitions[$tabId]['process'][$element]['language'] = 'und';
          }
          break;

        default:
          if ($element == 'title') {
            $titleSet = TRUE;
          }
          break;
      }

      if (
        $this->definitions[$tabId]['langcode'] != $this->siteDefaultLangCode &&
        $this->definitions[$tabId]['langcode'] != 'und' &&
        $isTranslatable
      ) {
        $this->definitions[$tabId]['process'][$element]['language'] = $this->definitions[$tabId]['langcode'];
      }

      $this->setTextFormat($tabId, $elementId, $element);
    }

    if (!$titleSet) {
      $this->definitions[$tabId]['source']['fields'][] = 'item_title';
      $this->definitions[$tabId]['process']['title'] = 'item_title';
    }
  }

  protected function setTextFormat(string $tabId, string $elementId, string $element) {
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

  protected function getElementLastPart(string $element) {
    if (strpos($element, '.')) {
      $parts = explode('.', $element);
      return end($parts);
    }
    return $element;
  }

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
