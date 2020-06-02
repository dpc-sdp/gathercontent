<?php

namespace Drupal\gathercontent_upload\Export;

use Cheppers\GatherContent\GatherContentClientInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
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
   * DI GatherContent Client.
   */
  public function __construct(
    GatherContentClientInterface $client,
    MetatagQuery $metatag,
    EntityTypeManagerInterface $entityTypeManager
  ) {
    $this->client = $client;
    $this->metatag = $metatag;
    $this->entityTypeManager = $entityTypeManager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('gathercontent.client'),
      $container->get('gathercontent.metatag'),
      $container->get('entity_type.manager')
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
   * @param int $gcId
   *   GatherContent ID.
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   Entity entity object.
   * @param \Drupal\gathercontent\Entity\MappingInterface $mapping
   *   Mapping object.
   *
   * @return int|null|string
   *   Returns entity ID.
   *
   * @throws \Exception
   */
  public function export($gcId, EntityInterface $entity, MappingInterface $mapping) {
    $content = $this->processGroups($entity, $mapping);

    $event = \Drupal::service('event_dispatcher')
      ->dispatch(GatherUploadContentEvents::PRE_NODE_UPLOAD, new PreNodeUploadEvent($entity, $content));

    /** @var \Drupal\gathercontent_upload\Event\PreNodeUploadEvent $event */
    $content = $event->getGathercontentValues();
    $this->client->itemUpdatePost($gcId, $content);

    \Drupal::service('event_dispatcher')
      ->dispatch(GatherUploadContentEvents::POST_NODE_UPLOAD, new PostNodeUploadEvent($entity, $content));

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
    $content = [];

    foreach ($templateData->related->structure->groups as $group) {
      $isTranslatable = \Drupal::moduleHandler()->moduleExists('content_translation')
        && \Drupal::service('content_translation.manager')
          ->isEnabled('node', $mapping->getContentType())
        && isset($mappingData[$group->uuid]['language'])
        && ($mappingData[$group->uuid]['language'] != Language::LANGCODE_NOT_SPECIFIED);

      if ($isTranslatable) {
        $language = $mappingData[$group->uuid]['language'];
      }
      else {
        $language = Language::LANGCODE_NOT_SPECIFIED;
      }

      $content += $this->processFields($group, $entity, $mappingData, $isTranslatable, $language);
    }

    return $content;
  }

  /**
   * Processes field data.
   *
   * @param $group
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
   *   Returns pane.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function processFields($group, EntityInterface $entity, array $mappingData, $isTranslatable, $language) {
    $exportedFields = [];
    $values = [];

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

        if ($localIdArray[0] === $titleField) {
          $currentFieldName = 'title';
        }
        else {
          $currentFieldName = $fieldInfo->getName();
          $type = $fieldInfo->getType();
          $bundle = $fieldInfo->getTargetBundle();
        }

        // Get the deepest field's value, we need tih sto collect the referenced entities values.
        $this->processTargets($currentEntity, $currentFieldName, $type, $bundle, $exportedFields, $localIdArray, $isTranslatable, $language);

        $values[$field->uuid] = $this->processSetFields($field, $currentEntity, $isTranslatable, $language, $currentFieldName, $bundle);
      }
      elseif ($mappingData[$group->id]['type'] === 'metatag') {
        if (\Drupal::moduleHandler()->moduleExists('metatag')
          && $this->metatag->checkMetatag($entity->getEntityTypeId(), $entity->bundle())
        ) {
          $values[$field->uuid] = $this->processMetaTagFields($entity, $localFieldId, $isTranslatable, $language);
        }
      }
    }

    return $values;
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

      if ($isTranslatable) {
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
    $metatagFields = $this->metatag->getMetatagFields($entity->getType(), $entity->bundle());

    foreach ($metatagFields as $metatagField) {
      if ($isTranslatable) {
        $currentValue = unserialize($entity->getTranslation($language)->{$metatagField}->value);
      }
      else {
        $currentValue = unserialize($entity->{$metatagField}->value);
      }

      return $currentValue[$localFieldName];
    }

    return '';
  }

  /**
   * Set value of the field.
   *
   * @param $field
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
    $value = null;

    switch ($field->field_type) {
      case 'attachment':
        // TODO: Implement a file tracking method and create the uploading functionality.
        break;

      case 'choice_radio':
      case 'choice_checkbox':
        // Fetch local selected option.
        if ($isTranslatable) {
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
            \Drupal::service('content_translation.manager')
              ->isEnabled('taxonomy_term', $bundle) &&
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
          if ($isTranslatable) {
            $value = $entity->getTranslation($language)->getTitle();
          }
          else {
            $value = $entity->getTitle();
          }
        }
        else {
          if ($isTranslatable) {
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
   * Check if the given option ID is valid for the template.
   *
   * @param array $options
   * @param $optionId
   *
   * @return bool
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
