<?php

namespace Drupal\gathercontent_upload\Export;

use Cheppers\GatherContent\DataTypes\Structure;
use Cheppers\GatherContent\GatherContentClientInterface;
use Drupal\Component\Uuid\UuidInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Language\LanguageManagerInterface;
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
    LanguageManagerInterface $languageManager
  ) {
    $this->client = $client;
    $this->entityTypeManager = $entityTypeManager;
    $this->entityFieldManager = $entityFieldManager;
    $this->entityTypeBundleInfo = $entityTypeBundleInfo;
    $this->uuidService = $uuidService;
    $this->moduleHandler = $moduleHandler;
    $this->languageManager = $languageManager;

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
      $container->get('language_manager')
    );
  }

  public function generateMapping(EntityInterface $entity, string $projectId) {
    $bundles = $this->entityTypeBundleInfo->getBundleInfo($entity->getEntityTypeId());
    $templateName = $entity->label();
    if (!empty($bundles[$entity->bundle()]['label'])) {
      $templateName = $bundles[$entity->bundle()]['label'];
    }

    $structureData = [
      'uuid' => $this->uuidService->generate(),
      'groups' => [],
    ];
    $mappingArray = [
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
    $fields = $this->entityFieldManager->getFieldDefinitions($entity->getEntityTypeId(), $entity->bundle());

    $languages = [$this->languageManager->getDefaultLanguage()];

    if (isset($this->contentTranslation)
      && $this->contentTranslation->isEnabled($entity->getEntityTypeId(), $entity->bundle())
    ) {
      $languages = $this->languageManager->getLanguages();
    }

    foreach ($languages as $language) {
      $group = [
        'uuid' => $this->uuidService->generate(),
        'name' => $language->getName(),
        'fields' => [],
      ];

      foreach ($fields as $field) {
        if (empty($mappingArray[$field->getType()])
          || $field->getType() !== 'entity_reference'
        ) {
          continue;
        }

        $fieldType = 'text';
        $metadata = [
          'is_plain' => FALSE,
        ];

        if ($field->getType() === 'entity_reference') {
          if ($field->getTargetEntityTypeId() !== 'taxonomy_term') {
            continue;
          }

          $fieldType = 'choice_checkbox';
          if (!$field->isMultiple()) {
            $fieldType = 'choice_radio';
          }

          $termStorage = $this->entityTypeManager->getStorage('taxonomy_term');
          $values = [];

          if ($field->getTargetBundle()) {
            $values['vid'] = $field->getTargetBundle();
          }

          $terms = $termStorage->loadByProperties($values);

          $options = [];
          foreach ($terms as $term) {
            $uuid = $this->uuidService->generate();
            if ($term->hasTranslation($language->getId())) {
              $term = $term->getTranslation($language->getId());
            }

            $options[] = [
              'optionId' => $uuid,
              'label' => $term->getLabel(),
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

        if (!empty($mappingArray[$field->getType()])) {
          $fieldType = $mappingArray[$field->getType()];
        }

        if ($fieldType === 'plain') {
          $fieldType = 'text';
          $metadata = [
            'is_plain' => TRUE,
          ];
        }

        $group['fields'][] = [
          'uuid' => $this->uuidService->generate(),
          'field_type' => $fieldType,
          'label' => $field->getName(),
          'metadata' => $metadata,
        ];
      }

      $structureData['groups'][] = $group;
    }

    $template = $this->client->templatePost($projectId, $templateName, new Structure($structureData));
    var_dump($template->id);
  }

}
