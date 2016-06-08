<?php

namespace Drupal\gathercontent\Entity;

use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityChangedTrait;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\user\UserInterface;

/**
 * Defines the Gathercontent operation entity.
 *
 * @ingroup gathercontent
 *
 * @ContentEntityType(
 *   id = "gathercontent_operation",
 *   label = @Translation("Gathercontent operation"),
 *   handlers = {
 *     "views_data" = "Drupal\gathercontent\Entity\GathercontentOperationViewsData",
 *   },
 *   base_table = "gathercontent_operation",
 *   admin_permission = "administer gathercontent operation entities",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "uuid",
 *     "uuid" = "uuid",
 *   }
 * )
 */
class GathercontentOperation extends ContentEntityBase implements GathercontentOperationInterface {

  use EntityChangedTrait;

  /**
   * {@inheritdoc}
   */
  public static function preCreate(EntityStorageInterface $storage_controller, array &$values) {
    parent::preCreate($storage_controller, $values);
  }

  /**
   * {@inheritdoc}
   */
  public function getType() {
    return $this->get('type')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setType($value) {
    $this->set('type', $value);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['type'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Type'))
      ->setDescription(t('Operation Type.'))
      ->setSettings(array(
        'max_length' => 50,
        'text_processing' => 0,
      ));

    return $fields;
  }

}
