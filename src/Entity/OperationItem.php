<?php

namespace Drupal\gathercontent\Entity;

use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityChangedTrait;
use Drupal\Core\Entity\EntityTypeInterface;

/**
 * Defines the Gathercontent operation item entity.
 *
 * @ingroup gathercontent
 *
 * @ContentEntityType(
 *   id = "gathercontent_operation_item",
 *   label = @Translation("Gathercontent operation item"),
 *   handlers = {
 *     "views_data" = "Drupal\gathercontent\Entity\OperationItemViewsData",
 *   },
 *   base_table = "gathercontent_operation_item",
 *   admin_permission = "administer gathercontent operation item entities",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "id",
 *     "uuid" = "uuid"
 *   }
 * )
 */
class OperationItem extends ContentEntityBase implements OperationItemInterface {

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
  public function getStatus() {
    return $this->get('status')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function getItemStatusColor() {
    return $this->get('item_status_color')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function getItemStatus() {
    return $this->get('item_status')->value;
  }

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['operation_uuid'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Operation UUID'));

    $fields['item_status'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Operation Item Status'))
      ->setDescription(t('Operation Item Status.'))
      ->setSettings(array(
        'max_length' => 10000,
        'text_processing' => 0,
      ));

    $fields['item_status_color'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Operation Item Status Color'))
      ->setDescription(t('Operation Item Status Color.'))
      ->setSettings(array(
        'max_length' => 7,
        'text_processing' => 0,
      ));

    $fields['item_name'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Operation Item Name'))
      ->setDescription(t('Operation Item Name.'))
      ->setSettings(array(
        'max_length' => 255,
        'text_processing' => 0,
      ));

    $fields['template_name'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Operation Item Template Name'))
      ->setDescription(t('Operation Item Template Name.'))
      ->setSettings(array(
        'max_length' => 255,
        'text_processing' => 0,
      ));

    $fields['status'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Operation Item Operation Status'))
      ->setDescription(t('Operation Item Operation Status.'))
      ->setSettings(array(
        'max_length' => 255,
        'text_processing' => 0,
      ));

    $fields['gc_id'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Operation Item GC ID'))
      ->setDescription(t('Operation Item GC ID'));

    $fields['nid'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Operation Item NID'))
      ->setDescription(t('Operation Item NID'));

    return $fields;
  }

}
