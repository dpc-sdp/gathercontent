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
use Drupal\gathercontent\DrupalGatherContentClient;
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
    $projects = $this->getProjects();
    $groups = [];
    $mappingData = [];
    $fieldCombinations = [
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
      $uuid = $this->uuidService->generate();
      $group = [
        'uuid' => $uuid,
        'name' => $language->getName(),
        'fields' => [],
      ];
      $mappingData[$uuid] = [
        'type' => 'content',
        'language' => $language->getId(),
        'elements' => [],
      ];

      foreach ($fields as $field) {
        if ($field instanceof BaseFieldDefinition
          && $titleKey !== $field->getName()
        ) {
          continue;
        }

        if (empty($fieldCombinations[$field->getType()])
          && $field->getType() !== 'entity_reference'
        ) {
          continue;
        }

        $fieldType = 'text';
        $metadata = [
          'is_plain' => FALSE,
        ];

        if (!empty($fieldCombinations[$field->getType()])) {
          $fieldType = $fieldCombinations[$field->getType()];
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

        $fieldUuid = $this->uuidService->generate();
        $group['fields'][] = [
          'uuid' => $fieldUuid,
          'field_type' => $fieldType,
          'label' => (string) $field->getLabel(),
          'metadata' => $metadata,
        ];
        $mappingData[$uuid]['elements'][$fieldUuid] = $field->id();
      }

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
      'gathercontent_project' => $projects[$projectId],
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
   * Returns all projects for given account.
   *
   * @return array
   */
  public function getProjects() {
    $accountId = DrupalGatherContentClient::getAccountId();
    /** @var \Cheppers\GatherContent\DataTypes\Project[] $projects */
    $projects = [];
    if ($accountId) {
      $projects = $this->client->getActiveProjects($accountId);
    }

    $formattedProjects = [];
    foreach ($projects['data'] as $project) {
      $formattedProjects[$project->id] = $project->name;
    }

    return $formattedProjects;
  }

}
