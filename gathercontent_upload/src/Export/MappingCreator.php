<?php

namespace Drupal\gathercontent_upload\Export;

use Cheppers\GatherContent\DataTypes\Structure;
use Cheppers\GatherContent\GatherContentClientInterface;
use Drupal\Component\Uuid\UuidInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\gathercontent\Entity\Mapping;
use Drupal\gathercontent\MigrationDefinitionCreator;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class for handling import/update logic from GatherContent to Drupal.
 */
class MappingCreator implements ContainerInjectionInterface {

  /**
   * Drupal GatherContent Client.
   *
   * @var \Drupal\gathercontent\DrupalGatherContentClient
   */
  protected $client;

  /**
   * Entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Entity field manager.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected $entityFieldManager;

  /**
   * The entity bundle info.
   *
   * @var \Drupal\Core\Entity\EntityTypeBundleInfoInterface
   */
  protected $entityTypeBundleInfo;

  /**
   * UUID service.
   *
   * @var \Drupal\Component\Uuid\UuidInterface
   */
  protected $uuidService;

  /**
   * Module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * The language manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * Migration definition creator.
   *
   * @var \Drupal\gathercontent\MigrationDefinitionCreator
   */
  protected $migrationDefinitionCreator;

  /**
   * Content translation manager.
   *
   * @var \Drupal\content_translation\ContentTranslationManagerInterface
   */
  protected $contentTranslation;

  const FIELD_COMBINATIONS = [
    'file' => 'attachment',
    'image' => 'attachment',
    'text' => 'text',
    'text_long' => 'text',
    'text_with_summary' => 'text',
    'string_long' => 'plain',
    'string' => 'plain',
    'email' => 'plain',
    'telephone' => 'plain',
    'date' => 'plain',
    'datetime' => 'plain',
  ];

  /**
   * MappingCreator constructor.
   */
  public function __construct(
    GatherContentClientInterface $client,
    EntityTypeManagerInterface $entityTypeManager,
    EntityFieldManagerInterface $entityFieldManager,
    EntityTypeBundleInfoInterface $entityTypeBundleInfo,
    UuidInterface $uuidService,
    ModuleHandlerInterface $moduleHandler,
    LanguageManagerInterface $languageManager,
    MigrationDefinitionCreator $migrationDefinitionCreator
  ) {
    $this->client = $client;
    $this->entityTypeManager = $entityTypeManager;
    $this->entityFieldManager = $entityFieldManager;
    $this->entityTypeBundleInfo = $entityTypeBundleInfo;
    $this->uuidService = $uuidService;
    $this->moduleHandler = $moduleHandler;
    $this->languageManager = $languageManager;
    $this->migrationDefinitionCreator = $migrationDefinitionCreator;

    if ($this->moduleHandler->moduleExists('content_translation')) {
      $this->contentTranslation = \Drupal::service('content_translation.manager');
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('gathercontent.client'),
      $container->get('entity_type.manager'),
      $container->get('entity_field.manager'),
      $container->get('entity_type.bundle.info'),
      $container->get('uuid'),
      $container->get('module_handler'),
      $container->get('language_manager'),
      $container->get('gathercontent.migration_creator')
    );
  }

  /**
   * Generates template, mapping and migration definition for given entity type and bundle.
   *
   * @param string $entityTypeId
   *   Entity type.
   * @param string $bundle
   *   Bundle.
   * @param string $projectId
   *   Project ID.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function generateMapping(string $entityTypeId, string $bundle, string $projectId) {
    $groups = [];
    $mappingData = [];

    $bundles = $this->entityTypeBundleInfo->getBundleInfo($entityTypeId);
    $templateName = ucfirst($bundle);
    if (!empty($bundles[$bundle]['label'])) {
      $templateName = $bundles[$bundle]['label'];
    }
    $fields = $this->entityFieldManager->getFieldDefinitions($entityTypeId, $bundle);
    $languages = [$this->languageManager->getDefaultLanguage()];

    if (isset($this->contentTranslation)
      && $this->contentTranslation->isEnabled($entityTypeId, $bundle)
    ) {
      $languages = $this->languageManager->getLanguages();
    }

    $entityDefinition = $this->entityTypeManager->getDefinition($entityTypeId);
    $titleKey = $entityDefinition->getKey('label');

    foreach ($languages as $language) {
      $groupUuid = $this->uuidService->generate();
      $group = [
        'uuid' => $groupUuid,
        'name' => $language->getName(),
        'fields' => [],
      ];
      $mappingData[$groupUuid] = [
        'type' => 'content',
        'language' => $language->getId(),
        'elements' => [],
      ];

      $this->processFields($fields, $titleKey, $language, $group, $mappingData, $groupUuid);

      $groups[] = $group;
    }

    $template = $this->client->templatePost($projectId, $templateName, new Structure([
      'uuid' => $this->uuidService->generate(),
      'groups' => $groups,
    ]));
    $this->client->templateGet($template->id);

    /** @var \Drupal\gathercontent\Entity\Mapping $mapping */
    $mapping = Mapping::create([
      'id' => $template->id,
      'gathercontent_project_id' => $projectId,
      'gathercontent_project' => $this->getProjectName($projectId),
      'gathercontent_template_id' => $template->id,
      'gathercontent_template' => $templateName,
      'template' => serialize($this->client->getBody(TRUE)),
    ]);
    $mapping->setMappedEntityType($entityTypeId);
    $mapping->setContentType($bundle);
    $mapping->setContentTypeName($templateName);
    $mapping->setData(serialize($mappingData));
    $mapping->setUpdatedDrupal(time());
    $mapping->save();

    if (!empty($mappingData)) {
      $this->migrationDefinitionCreator
        ->setMapping($mapping)
        ->setMappingData($mappingData)
        ->createMigrationDefinition();
    }
  }

  /**
   * Process the fields.
   *
   * @param array $fields
   *   Fields list.
   * @param string $titleKey
   *   Title key.
   * @param \Drupal\Core\Language\LanguageInterface $language
   *   Language object.
   * @param array $group
   *   Group array.
   * @param array $mappingData
   *   Mapping data array.
   * @param string $groupUuid
   *   Group's UUID.
   * @param string $parentFieldId
   *   Parent field's ID.
   * @param string $parentFieldLabel
   *   Parent field's Label.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  protected function processFields(array $fields, string $titleKey, $language, array &$group, array &$mappingData, string $groupUuid, string $parentFieldId = '', string $parentFieldLabel = '') {
    foreach ($fields as $field) {
      if ($field instanceof BaseFieldDefinition
        && $titleKey !== $field->getName()
      ) {
        continue;
      }

      if (empty(static::FIELD_COMBINATIONS[$field->getType()])
        && $field->getType() !== 'entity_reference'
        && $field->getType() !== 'entity_reference_revisions'
      ) {
        continue;
      }

      $fieldType = 'text';
      $metadata = [
        'is_plain' => FALSE,
      ];

      if (!empty(static::FIELD_COMBINATIONS[$field->getType()])) {
        $fieldType = static::FIELD_COMBINATIONS[$field->getType()];
      }

      if ($fieldType === 'plain') {
        $fieldType = 'text';
        $metadata = [
          'is_plain' => TRUE,
        ];
      }

      if ($field->getType() === 'entity_reference') {
        if ($field->getSetting('handler') !== 'default:taxonomy_term') {
          continue;
        }

        $fieldType = 'choice_checkbox';
        if (!$field->getFieldStorageDefinition()->isMultiple()) {
          $fieldType = 'choice_radio';
        }

        $termStorage = $this->entityTypeManager->getStorage('taxonomy_term');
        $values = [
          'langcode' => $language->getId(),
        ];
        $settings = $field->getSetting('handler_settings');

        if (!empty($settings['target_bundles'])) {
          $values['vid'] = $settings['target_bundles'];
        }

        $terms = $termStorage->loadByProperties($values);

        $options = [];
        foreach ($terms as $term) {
          $uuid = $this->uuidService->generate();
          $options[] = [
            'optionId' => $uuid,
            'label' => $term->label(),
          ];

          $optionIds = $term->get('gathercontent_option_ids')->getValue();
          $mappedValues = array_map(function ($array) {
            return $array['value'];
          }, $optionIds);

          if (!in_array($uuid, $mappedValues)) {
            $term->gathercontent_option_ids->appendItem($uuid);
          }
          $term->save();
        }

        $metadata = [
          'choice_fields' => [
            'options' => $options,
          ],
        ];
      }

      if ($field->getType() === 'entity_reference_revisions') {
        $settings = $field->getSetting('handler_settings');

        if (!empty($settings['target_bundles'])) {
          $bundles = $settings['target_bundles'];

          if (!empty($settings['negate']) && !empty($settings['target_bundles_drag_drop'])) {
            $negated_bundles = array_filter(
              $settings['target_bundles_drag_drop'],
              function ($v) {
                return !$v['enabled'];
              }
            );

            $bundles = array_combine(array_keys($negated_bundles), array_keys($negated_bundles));
          }

          $targetType = $field->getFieldStorageDefinition()
            ->getSetting('target_type');

          foreach ($bundles as $bundle) {
            $childFields = $this->entityFieldManager->getFieldDefinitions($targetType, $bundle);
            $entityDefinition = $this->entityTypeManager->getDefinition($targetType);
            $childTitleKey = $entityDefinition->getKey('label');

            $this->processFields($childFields, $childTitleKey, $language, $group, $mappingData, $groupUuid, $field->id(), (string) $field->getLabel());
          }
        }
        continue;
      }

      $fieldUuid = $this->uuidService->generate();
      $fieldLabel = (string) $field->getLabel();
      if (!empty($parentFieldLabel)) {
        $fieldLabel = $parentFieldLabel . ' ' . $fieldLabel;
      }

      $group['fields'][] = [
        'uuid' => $fieldUuid,
        'field_type' => $fieldType,
        'label' => $fieldLabel,
        'metadata' => $metadata,
      ];

      if ($titleKey !== $field->getName()) {
        $fieldId = $field->id();
        if (!empty($parentFieldId)) {
          $fieldId = $parentFieldId . '||' . $fieldId;
        }

        $mappingData[$groupUuid]['elements'][$fieldUuid] = $fieldId;
      }
      else {
        $mappingData[$groupUuid]['elements'][$fieldUuid] = $titleKey;
      }
    }
  }

  /**
   * Returns the name of a given project.
   *
   * @return string
   */
  public function getProjectName($projectId) {
    if (empty($projectId)) {
      return '';
    }

    $project = $this->client->projectGet($projectId);

    if (empty($project)) {
      return '';
    }

    return $project->name;
  }

}
