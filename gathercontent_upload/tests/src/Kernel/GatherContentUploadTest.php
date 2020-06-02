<?php

namespace Drupal\Tests\gathercontent_upload\Kernel;

use Cheppers\GatherContent\DataTypes\Element;
use Cheppers\GatherContent\DataTypes\Item;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\node\NodeInterface;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\taxonomy\Entity\Term;

/**
 * @coversDefaultClass \Drupal\gathercontent_upload\Export\Exporter
 * @group gathercontent_upload
 */
class GatherContentUploadTest extends GatherContentUploadTestBase {

  /**
   * Tests the field manipulation.
   */
  public function testProcessGroups() {
    $node = $this->getSimpleNode();
    $gcItem = $this->getSimpleItem();
    $mapping = $this->getMapping($gcItem);

    $modifiedItem = $this->exporter->processGroups($node, $mapping);

    $this->assertNotEmpty($modifiedItem);
    $this->assertItemChanged($modifiedItem, $node);
  }

  /**
   * Checks if all the fields are correctly set.
   *
   * @param array $content
   *   Content array.
   * @param \Drupal\node\NodeInterface $entity
   *   Node entity object.
   */
  public function assertItemChanged(array $content, NodeInterface $entity) {
    foreach ($content as $id => $fieldValue) {
      switch ($id) {
        case 'a9d89661-9d89-4c6d-86d3-353bfcf3214c':
          $this->assertEquals($entity->getTitle(), $fieldValue);
          break;

        case '9c7f806b-ff35-4ffa-9363-169770ac6e50':
          $value = $entity->get('field_guidodo')->getValue()[0]['value'];
          $this->assertNotEquals($value, $fieldValue);
          break;

        case 'dc73c531-d911-4acc-9055-984a1aeca0cb':
          $radio = $entity->get('field_radio');
          $this->assertSelection($fieldValue, $radio);
          break;

        case '427bc71f-844d-4730-a5d2-5e87d03fdbf0':
          $value = $entity->get('body')->getValue()[0]['value'];
          $this->assertEquals($value, $fieldValue);
          break;

        case '192775f3-354b-4884-bec9-0f4ecf153882':
          $checkbox = $entity->get('field_tags_alt');
          $this->assertSelection($fieldValue, $checkbox);
          break;

        case '361b0476-643e-41e7-97bb-a5065ad6fa1b':
          $paragraph = $entity->get('field_para');
          $this->assertParagraphText($fieldValue, $paragraph);
          break;

        case 'f88f8389-ad24-46ec-a669-6f293a07b4f7':
          $paragraph = $entity->get('field_para');
          $this->assertParagraphText($fieldValue, $paragraph, TRUE);
          break;

        case 'b11e3729-2a80-4f14-9842-87a4882fa190':
        case 'd8cbeeda-9cdf-4d3f-b94a-72a465a7cc46':
          // Not implemented yet!
          break;
      }
    }
  }

  /**
   * Tests field manipulation for multilingual content.
   */
  public function testProcessPanesMultilang() {
    $node = $this->getMultilangNode();

    $gc_item = $this->getMultilangItem();

    $modified_item = $this->exporter->processPanes($gc_item, $node);
    $this->assertItemChangedMultilang($modified_item, $node);
  }

  /**
   * Checks if all the fields are correctly set for multilingual content.
   *
   * @param \Cheppers\GatherContent\DataTypes\Item $gc_item
   *   Item object.
   * @param \Drupal\node\NodeInterface $entity
   *   Node entity object.
   */
  public function assertItemChangedMultilang(Item $gc_item, NodeInterface $entity) {
    foreach ($gc_item->config as $pane) {
      foreach ($pane->elements as $field) {
        switch ($field->id) {
          case 'el1502959595615':
            $this->assertEquals($entity->getTranslation('en')->getTitle(), $field->getValue());
            break;

          case 'el1502959226216':
            $value = $entity->getTranslation('en')->get('body')->getValue()[0]['value'];
            $this->assertEquals($value, $field->getValue());
            break;

          case 'el1503046753703':
            $radio = $entity->getTranslation('en')->get('field_radio');
            $this->assertSelection($field, $radio);
            break;

          case 'el1503046763382':
            $checkbox = $entity->getTranslation('en')->get('field_tags');
            $this->assertSelection($field, $checkbox);
            break;

          case 'el1503046796344':
            $paragraph = $entity->getTranslation('en')->get('field_para');
            $this->assertParagraphText($field, $paragraph);
            break;

          case 'el1503046917174':
            $paragraph = $entity->getTranslation('en')->get('field_para');
            $this->assertParagraphText($field, $paragraph, TRUE);
            break;

          case 'el1503050151209':
            $value = $entity->getTranslation('en')->get('field_guidodo')->getValue()[0]['value'];
            $this->assertNotEquals($value, $field->getValue());
            break;

          case 'el1503046938794':
            $this->assertEquals($entity->getTranslation('hu')->getTitle(), $field->getValue());
            break;

          case 'el1503046938795':
            $value = $entity->getTranslation('hu')->get('body')->getValue()[0]['value'];
            $this->assertEquals($value, $field->getValue());
            break;

          case 'el1503046938797':
            $radio = $entity->getTranslation('hu')->get('field_radio');
            $this->assertSelection($field, $radio);
            break;

          case 'el1503046938798':
            $checkbox = $entity->getTranslation('hu')->get('field_tags');
            $this->assertSelection($field, $checkbox);
            break;

          case 'el1503046938799':
            $paragraph = $entity->getTranslation('hu')->get('field_para');
            $this->assertParagraphText($field, $paragraph, FALSE, TRUE);
            break;

          case 'el1503046938801':
            $paragraph = $entity->getTranslation('hu')->get('field_para');
            $this->assertParagraphText($field, $paragraph, TRUE, TRUE);
            break;

          case 'el1503050171534':
            $value = $entity->getTranslation('hu')->get('field_guidodo')->getValue()[0]['value'];
            $this->assertNotEquals($value, $field->getValue());
            break;

          case 'el1503046930689':
          case 'el1503046889180':
          case 'el1503046938800':
          case 'el1503046938796':
            // No possibility to upload image!
            break;
        }
      }
    }
  }

  /**
   * Tests field manipulation for metatag content.
   */
  public function testProcessPanesMetatag() {
    $node = $this->getMetatagNode();

    $gc_item = $this->getMetatagItem();

    $modified_item = $this->exporter->processPanes($gc_item, $node);
    $this->assertItemChangedMetatag($modified_item, $node);
  }

  /**
   * Checks if all the fields are correctly set for metatag content.
   *
   * @param \Cheppers\GatherContent\DataTypes\Item $gc_item
   *   Item object.
   * @param \Drupal\node\NodeInterface $entity
   *   Node entity object.
   */
  public function assertItemChangedMetatag(Item $gc_item, NodeInterface $entity) {
    foreach ($gc_item->config as $pane) {
      foreach ($pane->elements as $field) {
        switch ($field->id) {
          case 'el1502978044104':
            $this->assertEquals($entity->getTitle(), $field->getValue());
            break;

          case 'el1475138048898':
            $value = $entity->get('body')->getValue()[0]['value'];
            $this->assertEquals($value, $field->getValue());
            break;

          case 'el1475138068185':
            $meta_value = unserialize($entity->get('field_meta_alt')->value);
            $this->assertEquals($meta_value['title'], $field->getValue());
            break;

          case 'el1475138069769':
            $meta_value = unserialize($entity->get('field_meta_alt')->value);
            $this->assertEquals($meta_value['description'], $field->getValue());
            break;
        }
      }
    }
  }

  /**
   * Tests field manipulation for metatag content.
   */
  public function testProcessPanesMetatagMultilang() {
    $node = $this->getMetatagMultilangNode();

    $gc_item = $this->getMetatagMultilangItem();

    $modified_item = $this->exporter->processPanes($gc_item, $node);
    $this->assertItemChangedMetatagMultilang($modified_item, $node);
  }

  /**
   * Checks if all the fields are correctly set for metatag content.
   *
   * @param \Cheppers\GatherContent\DataTypes\Item $gc_item
   *   Item object.
   * @param \Drupal\node\NodeInterface $entity
   *   Node entity object.
   */
  public function assertItemChangedMetatagMultilang(Item $gc_item, NodeInterface $entity) {
    foreach ($gc_item->config as $pane) {
      foreach ($pane->elements as $field) {
        switch ($field->id) {
          case 'el1502978044104':
            $this->assertEquals($entity->getTitle(), $field->getValue());
            break;

          case 'el1475138048898':
            $value = $entity->get('body')->getValue()[0]['value'];
            $this->assertEquals($value, $field->getValue());
            break;

          case 'el1475138068185':
            $meta_value = unserialize($entity->get('field_meta_alt')->value);
            $this->assertEquals($meta_value['title'], $field->getValue());
            break;

          case 'el1475138069769':
            $meta_value = unserialize($entity->get('field_meta_alt')->value);
            $this->assertEquals($meta_value['description'], $field->getValue());
            break;
        }
      }
    }
  }

  /**
   * Check radio and checkbox selection value.
   *
   * @param array $value
   *   Response value array.
   * @param \Drupal\Core\Field\FieldItemListInterface $itemList
   *   Item list.
   */
  public function assertSelection(array $value, FieldItemListInterface $itemList) {
    $selected = $value[0]['id'];

    $targets = $itemList->getValue();
    $target = array_shift($targets);

    $term = Term::load($target['target_id']);
    $checkbox_value = $term->get('gathercontent_option_ids')->getValue()[0]['value'];

    $this->assertEquals($checkbox_value, $selected);
  }

  /**
   * Check paragraph text value.
   *
   * @param string $fieldValue
   *   GatherContent field value.
   * @param \Drupal\Core\Field\FieldItemListInterface $itemList
   *   Item list.
   * @param bool $isPop
   *   Use array_pop or not.
   * @param bool $translated
   *   Is the content translated.
   */
  public function assertParagraphText($fieldValue, FieldItemListInterface $itemList, $isPop = FALSE, $translated = FALSE) {
    $targets = $itemList->getValue();
    if ($isPop) {
      $target = array_pop($targets);
    }
    else {
      $target = array_shift($targets);
    }

    $para = Paragraph::load($target['target_id']);
    if ($translated) {
      $value = $para->getTranslation('hu')->get('field_text')->getValue()[0]['value'];
    }
    else {
      $value = $para->get('field_text')->getValue()[0]['value'];
    }

    $this->assertEquals($value, $fieldValue);
  }

}
