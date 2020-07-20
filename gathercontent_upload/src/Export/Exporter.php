<?php

namespace Drupal\gathercontent_upload\Export;

use Cheppers\GatherContent\DataTypes\Item;
use Cheppers\GatherContent\GatherContentClientInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Language\Language;
use Drupal\Core\Language\LanguageInterface;
use Drupal\field\Entity\FieldConfig;
use Drupal\gathercontent\Entity\MappingInterface;
use Drupal\gathercontent\MetatagQuery;
use Drupal\gathercontent_upload\Event\GatherUploadContentEvents;
use Drupal\gathercontent_upload\Event\PostNodeUploadEvent;
use Drupal\gathercontent_upload\Event\PreNodeUploadEvent;
use Exception;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Class for handling import/update logic from GatherContent to Drupal.
 */
class Exporter implements ContainerInjectionInterface {

  /**
   * Drupal GatherContent Client.
   *
   * @var \Drupal\gathercontent\DrupalGatherContentClient
   */
  protected $client;

  /**
   * Meta tag Query.
   *
   * @var \Drupal\gathercontent\MetatagQuery
   */
  protected $metatag;

  /**
   * Entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Event dispatcher.
   *
   * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
   */
  protected $eventDispatcher;

  /**
   * Module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * Content translation manager.
   *
   * @var \Drupal\content_translation\ContentTranslationManagerInterface
   */
  protected $contentTranslation;

  /**
   * Filesystem service.
   *
   * @var \Drupal\Core\File\FileSystemInterface
   */
  protected $fileSystem;

  /**
   * Collected reference revisions.
   *
   * @var array
   */
  protected $collectedReferenceRevisions = [];

  /**
   * Collected file fields.
   *
   * @var array
   */
  protected $collectedFileFields = [];

  /**
   * Exporter constructor.
   */
  public function __construct(
    GatherContentClientInterface $client,
    MetatagQuery $metatag,
    EntityTypeManagerInterface $entityTypeManager,
    EventDispatcherInterface $eventDispatcher,
    ModuleHandlerInterface $moduleHandler,
    FileSystemInterface $fileSystem
  ) {
    $this->client = $client;
    $this->metatag = $metatag;
    $this->entityTypeManager = $entityTypeManager;
    $this->eventDispatcher = $eventDispatcher;
    $this->moduleHandler = $moduleHandler;
    $this->fileSystem = $fileSystem;

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
      $container->get('gathercontent.metatag'),
      $container->get('entity_type.manager'),
      $container->get('event_dispatcher'),
      $container->get('module_handler'),
      $container->get('file_system')
    );
  }

  /**
   * Getter GatherContentClient.
   */
  public function getClient() {
    return $this->client;
  }

  /**
   * Exports the changes made in Drupal contents.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   Entity entity object.
   * @param \Drupal\gathercontent\Entity\MappingInterface $mapping
   *   Mapping object.
   * @param int|null $gcId
   *   GatherContent ID.
   * @param array $context
   *   Batch context.
   *
   * @return int|null|string
   *   Returns entity ID.
   *
   * @throws \Exception
   */
  public function export(EntityInterface $entity, MappingInterface $mapping, $gcId = NULL, &$context = []) {
    $this->collectedReferenceRevisions = [];
    $data = $this->processGroups($entity, $mapping);

    $event = $this->eventDispatcher
      ->dispatch(GatherUploadContentEvents::PRE_NODE_UPLOAD, new PreNodeUploadEvent($entity, $data));

    /** @var \Drupal\gathercontent_upload\Event\PreNodeUploadEvent $event */
    $data = $event->getGathercontentValues();

    if (!empty($gcId)) {
      $item = $this->client->itemUpdatePost($gcId, $data['content'], $data['assets']);
      $this->updateFileGcIds($item->assets);
    }
    else {
      $data['name'] = $entity->label();
      $data['template_id'] = $mapping->getGathercontentTemplateId();
      $item = $this->client->itemPost($mapping->getGathercontentProjectId(), new Item($data));
      $gcId = $item['data']->id;
      $this->updateFileGcIds($item['meta']->assets);
    }

    $this->eventDispatcher
      ->dispatch(GatherUploadContentEvents::POST_NODE_UPLOAD, new PostNodeUploadEvent($entity, $data));

    if (empty($context['results']['mappings'][$mapping->id()])) {
      $context['results']['mappings'][$mapping->id()] = [
        'mapping' => $mapping,
        'gcIds' => [
          $gcId => [],
        ],
      ];
    }

    $context['results']['mappings'][$mapping->id()]['gcIds'][$gcId][] = $entity;

    foreach ($this->collectedReferenceRevisions as $reference) {
      $context['results']['mappings'][$mapping->id()]['gcIds'][$gcId][] = $reference;
    }

    return $entity->id();
  }

  /**
   * Manages the panes and changes the Item object values.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   Entity object.
   * @param \Drupal\gathercontent\Entity\MappingInterface $mapping
   *   Mappig object.
   *
   * @return array
   *   Returns Content array.
   *
   * @throws \Exception
   */
  public function processGroups(EntityInterface $entity, MappingInterface $mapping) {
    $mappingData = unserialize($mapping->getData());

    if (empty($mappingData)) {
      throw new Exception("Mapping data is empty.");
    }

    $templateData = unserialize($mapping->getTemplate());
    $data = [
      'content' => [],
      'assets' => [],
    ];

    foreach ($templateData->related->structure->groups as $group) {
      $isTranslatable = $this->moduleHandler->moduleExists('content_translation')
        && $this->contentTranslation->isEnabled($mapping->getMappedEntityType(), $mapping->getContentType())
        && isset($mappingData[$group->uuid]['language'])
        && ($mappingData[$group->uuid]['language'] != Language::LANGCODE_NOT_SPECIFIED);

      if ($isTranslatable) {
        $language = $mappingData[$group->uuid]['language'];
      }
      else {
        $language = Language::LANGCODE_NOT_SPECIFIED;
      }

      $fields = $this->processFields($group, $entity, $mappingData, $isTranslatable, $language);
      $data['content'] += $fields['content'];
      $data['assets'] += $fields['assets'];
    }

    return $data;
  }

  /**
   * Processes field data.
   *
   * @param object $group
   *   Group object.
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   Entity.
   * @param array $mappingData
   *   Mapping array.
   * @param bool $isTranslatable
   *   Translatable.
   * @param string $language
   *   Language.
   *
   * @return array
   *   Returns data.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function processFields($group, EntityInterface $entity, array $mappingData, $isTranslatable, $language) {
    $exportedFields = [];
    $fields = [];
    $assets = [];

    foreach ($group->fields as $field) {
      // Skip field if it is not mapped.
      if (empty($mappingData[$group->uuid]['elements'][$field->uuid])) {
        continue;
      }

      $localFieldId = $mappingData[$group->uuid]['elements'][$field->uuid];
      if ((isset($mappingData[$group->uuid]['type'])
          && $mappingData[$group->uuid]['type'] === 'content')
        || !isset($mappingData[$group->uuid]['type'])
      ) {
        $localIdArray = explode('||', $localFieldId);
        $fieldInfo = FieldConfig::load($localIdArray[0]);
        $currentEntity = $entity;
        $type = '';
        $bundle = '';
        $titleField = $currentEntity->getEntityTypeId() . '.' . $currentEntity->bundle() . '.title';

        if ($localIdArray[0] === $titleField
          || $localIdArray[0] === 'title'
        ) {
          $currentFieldName = 'title';
        }
        else {
          $currentFieldName = $fieldInfo->getName();
          $type = $fieldInfo->getType();
          $bundle = $fieldInfo->getTargetBundle();
        }

        // Get the deepest field's value, we need this to collect
        // the referenced entities values.
        $this->processTargets($currentEntity, $currentFieldName, $type, $bundle, $exportedFields, $localIdArray, $isTranslatable, $language);
        $this->collectedReferenceRevisions[] = $currentEntity;

        $value = $this->processSetFields($field, $currentEntity, $isTranslatable, $language, $currentFieldName, $bundle);

        if (!empty($value)) {
          $fields[$field->uuid] = $value;
        }

        $asset = $this->processSetAssets($field, $currentEntity, $isTranslatable, $language, $currentFieldName);

        if (!empty($asset)) {
          $assets[$field->uuid] = $asset;
        }
      }
      elseif ($mappingData[$group->uuid]['type'] === 'metatag') {
        if ($this->moduleHandler->moduleExists('metatag')
          && $this->metatag->checkMetatag($entity->getEntityTypeId(), $entity->bundle())
        ) {
          $fields[$field->uuid] = $this->processMetaTagFields($entity, $localFieldId, $isTranslatable, $language);
        }
      }
    }

    return [
      'content' => $fields,
      'assets' => $assets,
    ];
  }

  /**
   * Processes the target ids for a field.
   *
   * @param \Drupal\Core\Entity\EntityInterface $currentEntity
   *   Entity object.
   * @param string $currentFieldName
   *   Current field name.
   * @param string $type
   *   Current type name.
   * @param string $bundle
   *   Current bundle name.
   * @param array $exportedFields
   *   Array of exported fields, preventing duplications.
   * @param array $localIdArray
   *   Array of mapped embedded field id array.
   * @param bool $isTranslatable
   *   Translatable.
   * @param string $language
   *   Language.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function processTargets(EntityInterface &$currentEntity, &$currentFieldName, &$type, &$bundle, array &$exportedFields, array $localIdArray, $isTranslatable, $language) {
    $idCount = count($localIdArray);

    // Loop through the references, going deeper and deeper.
    for ($i = 0; $i < $idCount - 1; $i++) {
      $localId = $localIdArray[$i];
      $fieldInfo = FieldConfig::load($localId);
      $currentFieldName = $fieldInfo->getName();
      $type = $fieldInfo->getType();
      $bundle = $fieldInfo->getTargetBundle();

      if ($isTranslatable && $currentEntity->hasTranslation($language)) {
        $targetFieldValue = $currentEntity->getTranslation($language)->get($currentFieldName)->getValue();
      }
      else {
        $targetFieldValue = $currentEntity->get($currentFieldName)->getValue();
      }

      // Load the targeted entity and process the data.
      if (!empty($targetFieldValue)) {
        $fieldTargetInfo = FieldConfig::load($localIdArray[$i + 1]);
        $entityStorage = $this->entityTypeManager
          ->getStorage($fieldTargetInfo->getTargetEntityTypeId());
        $childFieldName = $fieldTargetInfo->getName();
        $childType = $fieldInfo->getType();
        $childBundle = $fieldInfo->getTargetBundle();

        foreach ($targetFieldValue as $target) {
          $exportKey = $target['target_id'] . '_' . $childFieldName;

          // The field is already collected.
          if (!empty($exportedFields[$exportKey])) {
            continue;
          }

          $childEntity = $entityStorage->loadByProperties([
            'id' => $target['target_id'],
            'type' => $fieldTargetInfo->getTargetBundle(),
          ]);

          if (!empty($childEntity[$target['target_id']])) {
            $currentEntity = $childEntity[$target['target_id']];
            $currentFieldName = $childFieldName;
            $type = $childType;
            $bundle = $childBundle;

            if ($i == ($idCount - 2)) {
              $exportedFields[$exportKey] = TRUE;
            }
            break;
          }
        }
      }
    }
  }

  /**
   * Processes meta fields.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   Entity object.
   * @param string $localFieldName
   *   Field name.
   * @param bool $isTranslatable
   *   Translatable bool.
   * @param string $language
   *   Language string.
   *
   * @return string
   *   Returns value.
   */
  public function processMetaTagFields(EntityInterface $entity, $localFieldName, $isTranslatable, $language) {
    $fieldName = $this->metatag->getFirstMetatagField($entity->getEntityTypeId(), $entity->bundle());

    if ($isTranslatable && $entity->hasTranslation($language)) {
      $currentValue = unserialize($entity->getTranslation($language)->{$fieldName}->value);
    }
    else {
      $currentValue = unserialize($entity->{$fieldName}->value);
    }

    return $currentValue[$localFieldName] ?? '';
  }

  /**
   * Set value of the field.
   *
   * @param object $field
   *   Field object.
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   Entity object.
   * @param bool $isTranslatable
   *   Translatable bool.
   * @param string $language
   *   Language string.
   * @param string $localFieldName
   *   Field Name.
   * @param string $bundle
   *   Local field Info bundle string.
   *
   * @return array|string
   *   Returns value.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function processSetFields($field, EntityInterface $entity, $isTranslatable, $language, $localFieldName, $bundle) {
    $value = NULL;

    switch ($field->field_type) {
      case 'attachment':
        // Fetch file targets.
        if ($isTranslatable && $entity->hasTranslation($language)) {
          $targets = $entity->getTranslation($language)->{$localFieldName}->getValue();
        }
        else {
          $targets = $entity->{$localFieldName}->getValue();
        }

        $value = [];
        foreach ($targets as $target) {
          $file = $this->entityTypeManager
            ->getStorage('file')
            ->load($target['target_id']);

          if (empty($file) || $file->get('gc_file_id')->isEmpty()) {
            continue;
          }

          $value[] = $file->get('gc_file_id')->first()->getValue()['value'];
        }
        break;

      case 'choice_radio':
      case 'choice_checkbox':
        // Fetch local selected option.
        if ($isTranslatable && $entity->hasTranslation($language)) {
          $targets = $entity->getTranslation($language)->{$localFieldName}->getValue();
        }
        else {
          $targets = $entity->{$localFieldName}->getValue();
        }

        $value = [];

        foreach ($targets as $target) {
          $conditionArray = [
            'tid' => $target['target_id'],
          ];

          if (
            $isTranslatable &&
            $this->moduleHandler->moduleExists('content_translation') &&
            $this->contentTranslation->isEnabled('taxonomy_term', $bundle) &&
            $language !== LanguageInterface::LANGCODE_NOT_SPECIFIED
          ) {
            $conditionArray['langcode'] = $language;
          }

          $terms = $this->entityTypeManager
            ->getStorage('taxonomy_term')
            ->loadByProperties($conditionArray);

          /** @var \Drupal\taxonomy\Entity\Term $term */
          $term = array_shift($terms);
          if (!empty($term)) {
            $optionIds = $term->gathercontent_option_ids->getValue();
            $options = $field->metadata->choice_fields->options;

            foreach ($optionIds as $optionId) {
              if (!$this->validOptionId(
                $options,
                $optionId['value'])
              ) {
                continue;
              }

              $value[] = [
                'id' => $optionId['value'],
              ];
            }
          }
        }
        break;

      case 'guidelines':
        // We don't upload this because this field shouldn't be
        // edited.
        break;

      default:
        if ($localFieldName === 'title') {
          if ($isTranslatable && $entity->hasTranslation($language)) {
            $value = $entity->getTranslation($language)->getTitle();
          }
          else {
            $value = $entity->getTitle();
          }
        }
        else {
          if ($isTranslatable && $entity->hasTranslation($language)) {
            $value = $entity->getTranslation($language)->{$localFieldName}->value;
          }
          else {
            $value = $entity->{$localFieldName}->value;
          }
        }
        break;
    }

    return $value;
  }

  /**
   * Set assets.
   *
   * @param object $field
   *   Field object.
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   Entity object.
   * @param bool $isTranslatable
   *   Translatable bool.
   * @param string $language
   *   Language string.
   * @param string $localFieldName
   *   Field Name.
   *
   * @return array|string
   *   Returns value.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function processSetAssets($field, EntityInterface $entity, $isTranslatable, $language, $localFieldName) {
    $value = NULL;

    switch ($field->field_type) {
      case 'attachment':
        // Fetch file targets.
        if ($isTranslatable && $entity->hasTranslation($language)) {
          $targets = $entity->getTranslation($language)->{$localFieldName}->getValue();
        }
        else {
          $targets = $entity->{$localFieldName}->getValue();
        }

        $value = [];
        foreach ($targets as $target) {
          /** @var \Drupal\file\FileInterface $file */
          $file = $this->entityTypeManager
            ->getStorage('file')
            ->load($target['target_id']);

          if (empty($file) || !$file->get('gc_file_id')->isEmpty()) {
            continue;
          }

          $value[] = $this->fileSystem->realpath($file->getFileUri());
        }

        $this->collectedFileFields[$field->uuid] = $targets;
        break;
    }

    return $value;
  }

  /**
   * Updates the file managed table to include the new GC ID for given file.
   *
   * @param array $returnedAssets
   *   The assets returned by GC.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function updateFileGcIds(array $returnedAssets) {
    if (empty($this->collectedFileFields) || empty($returnedAssets)) {
      return;
    }

    foreach ($this->collectedFileFields as $fieldUuid => $fileField) {
      if (empty($returnedAssets[$fieldUuid])) {
        continue;
      }

      foreach ($fileField as $delta => $target) {
        /** @var \Drupal\file\FileInterface $file */
        $file = $this->entityTypeManager
          ->getStorage('file')
          ->load($target['target_id']);

        if (empty($file) || empty($returnedAssets[$fieldUuid][$delta])) {
          continue;
        }

        $file->set('gc_file_id', $returnedAssets[$fieldUuid][$delta]);
        $file->save();
      }
    }
  }

  /**
   * Check if the given option ID is valid for the template.
   *
   * @param array $options
   *   Options array.
   * @param string $optionId
   *   Option ID.
   *
   * @return bool
   *   Returns if the option ID is valid for a given template.
   */
  protected function validOptionId(array $options, $optionId) {
    foreach ($options as $option) {
      if ($option->optionId === $optionId) {
        return TRUE;
      }
    }

    return FALSE;
  }

}
