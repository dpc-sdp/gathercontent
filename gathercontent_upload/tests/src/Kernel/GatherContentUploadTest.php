<?php

namespace Drupal\Tests\gathercontent_upload\Kernel;

use Cheppers\GatherContent\DataTypes\Item;
use Drupal\file\Entity\File;
use Drupal\node\NodeInterface;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\taxonomy\Entity\Term;

/**
 * @coversDefaultClass \Drupal\gathercontent_upload\Export\Exporter
 * @group gathercontent_upload
 */
class GatherContentUploadTest extends GatherContentUploadTestBase {

  /**
   * Tests success of mapping get.
   *
   * @covers ::getMapping
   */
  public function testMappingGet() {
    $gc_item = new Item([
      'project_id' => 86701,
      'template_id' => 791717,
    ]);

    $mapping = $this->exporter->getMapping($gc_item);
    $this->assertEquals(791717, $mapping->id(), 'Mapping loaded successfully');
  }

  /**
   * Tests failure of mapping get.
   *
   * @covers ::getMapping
   */
  public function testMappingGetFail() {
    $gc_item = new Item();

    $this->setExpectedException('Exception',
      'Operation failed: Template not mapped.');
    $this->exporter->getMapping($gc_item);
  }

  /**
   * Tests the field manipulation.
   */
  public function testProcessPanes() {
    $node = $this->getSimpleNode();

    $gc_item = $this->getSimpleItem();

    $modified_item = $this->exporter->processPanes($gc_item, $node);

    $this->assertItemChanged($modified_item, $node);
  }

  /**
   * Checks if all the fields are correctly set.
   *
   * @param \Cheppers\GatherContent\DataTypes\Item $gc_item
   *   Item object.
   * @param \Drupal\node\NodeInterface $entity
   *   Node entity object.
   */
  public function assertItemChanged(Item $gc_item, NodeInterface $entity) {
    foreach ($gc_item->config as $pane) {
      foreach ($pane->elements as $field) {
        switch ($field->id) {
          case 'el1501675275975':
            $this->assertEquals($entity->getTitle(), $field->getValue());
            break;

          case 'el1501679176743':
            $value = $entity->get('field_guidodo')->getValue()[0]['value'];
            $this->assertNotEquals($value, $field->getValue());
            break;

          case 'el1501678793027':
            $selected = NULL;
            foreach ($field->options as $option) {
              if ($option['selected']) {
                $selected = $option['name'];
              }
            }

            $radio = $entity->get('field_radio');
            $targets = $radio->getValue();
            $target = array_shift($targets);

            $term = Term::load($target['target_id']);
            $radio_value = $term->get('gathercontent_option_ids')->getValue()[0]['value'];

            $this->assertEquals($radio_value, $selected);
            break;

          case 'el1500994248864':
            $value = $entity->get('body')->getValue()[0]['value'];
            $this->assertEquals($value, $field->getValue());
            break;

          case 'el1501598415730':
            $image = $entity->get('field_image');
            $targets = $image->getValue();
            $target = array_shift($targets);

            $img = File::load($target['target_id']);
            $image_url = $img->url();

            $this->assertNotEquals($image_url, $field->url);
            break;

          case 'el1500994276297':
            $selected = NULL;
            foreach ($field->options as $option) {
              if ($option['selected']) {
                $selected = $option['name'];
              }
            }

            $checkbox = $entity->get('field_tags_alt');
            $targets = $checkbox->getValue();
            $target = array_shift($targets);

            $term = Term::load($target['target_id']);
            $checkbox_value = $term->get('gathercontent_option_ids')->getValue()[0]['value'];

            $this->assertEquals($checkbox_value, $selected);
            break;

          case 'el1501666239392':
            $paragraph = $entity->get('field_para');
            $targets = $paragraph->getValue();
            $target = array_shift($targets);

            $para = Paragraph::load($target['target_id']);
            $value = $para->get('field_text')->getValue()[0]['value'];

            $this->assertEquals($value, $field->getValue());
            break;

          case 'el1501666248919':
            $paragraph = $entity->get('field_para');
            $targets = $paragraph->getValue();
            $target = array_shift($targets);

            $para = Paragraph::load($target['target_id']);
            $para_targets = $para->get('field_image')->getValue();
            $para_target = array_shift($para_targets);

            $img = File::load($para_target['target_id']);
            $image_url = $img->url();

            $this->assertNotEquals($image_url, $field->url);
            break;

          case 'el1501772184393':
            $paragraph = $entity->get('field_para');
            $targets = $paragraph->getValue();
            $target = array_pop($targets);

            $para = Paragraph::load($target['target_id']);
            $value = $para->get('field_text')->getValue()[0]['value'];

            $this->assertEquals($value, $field->getValue());
            break;
        }
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

          case 'el1503046930689':
            $image = $entity->getTranslation('en')->get('field_image');
            $targets = $image->getValue();
            $target = array_shift($targets);

            $img = File::load($target['target_id']);
            $image_url = $img->url();

            $this->assertNotEquals($image_url, $field->url);
            break;

          case 'el1503046753703':
            $selected = NULL;
            foreach ($field->options as $option) {
              if ($option['selected']) {
                $selected = $option['name'];
              }
            }

            $radio = $entity->getTranslation('en')->get('field_radio');
            $targets = $radio->getValue();
            $target = array_shift($targets);

            $term = Term::load($target['target_id']);
            $radio_value = $term->get('gathercontent_option_ids')->getValue()[0]['value'];

            $this->assertEquals($radio_value, $selected);
            break;

          case 'el1503046763382':
            $selected = NULL;
            foreach ($field->options as $option) {
              if ($option['selected']) {
                $selected = $option['name'];
              }
            }

            $checkbox = $entity->getTranslation('en')->get('field_tags');
            $targets = $checkbox->getValue();
            $target = array_shift($targets);

            $term = Term::load($target['target_id']);
            $checkbox_value = $term->get('gathercontent_option_ids')->getValue()[0]['value'];

            $this->assertEquals($checkbox_value, $selected);
            break;

          case 'el1503046796344':
            $paragraph = $entity->getTranslation('en')->get('field_para');
            $targets = $paragraph->getValue();
            $target = array_shift($targets);

            $para = Paragraph::load($target['target_id']);
            $value = $para->get('field_text')->getValue()[0]['value'];

            $this->assertEquals($value, $field->getValue());
            break;

          case 'el1503046889180':
            $paragraph = $entity->getTranslation('en')->get('field_para');
            $targets = $paragraph->getValue();
            $target = array_shift($targets);

            $para = Paragraph::load($target['target_id']);
            $para_targets = $para->get('field_image')->getValue();
            $para_target = array_shift($para_targets);

            $img = File::load($para_target['target_id']);
            $image_url = $img->url();

            $this->assertNotEquals($image_url, $field->url);
            break;

          case 'el1503046917174':
            $paragraph = $entity->getTranslation('en')->get('field_para');
            $targets = $paragraph->getValue();
            $target = array_pop($targets);

            $para = Paragraph::load($target['target_id']);
            $value = $para->get('field_text')->getValue()[0]['value'];

            $this->assertEquals($value, $field->getValue());
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

          case 'el1503046938796':
            $image = $entity->getTranslation('hu')->get('field_image');
            $targets = $image->getValue();
            $target = array_shift($targets);

            $img = File::load($target['target_id']);
            $image_url = $img->url();

            $this->assertNotEquals($image_url, $field->url);
            break;

          case 'el1503046938797':
            $selected = NULL;
            foreach ($field->options as $option) {
              if ($option['selected']) {
                $selected = $option['name'];
              }
            }

            $radio = $entity->getTranslation('hu')->get('field_radio');
            $targets = $radio->getValue();
            $target = array_shift($targets);

            $term = Term::load($target['target_id']);
            $radio_value = $term->get('gathercontent_option_ids')->getValue()[0]['value'];

            $this->assertEquals($radio_value, $selected);
            break;

          case 'el1503046938798':
            $selected = NULL;
            foreach ($field->options as $option) {
              if ($option['selected']) {
                $selected = $option['name'];
              }
            }

            $checkbox = $entity->getTranslation('hu')->get('field_tags');
            $targets = $checkbox->getValue();
            $target = array_shift($targets);

            $term = Term::load($target['target_id']);
            $checkbox_value = $term->get('gathercontent_option_ids')->getValue()[0]['value'];

            $this->assertEquals($checkbox_value, $selected);
            break;

          case 'el1503046938799':
            $paragraph = $entity->getTranslation('hu')->get('field_para');
            $targets = $paragraph->getValue();
            $target = array_shift($targets);

            $para = Paragraph::load($target['target_id']);
            $value = $para->getTranslation('hu')->get('field_text')->getValue()[0]['value'];

            $this->assertEquals($value, $field->getValue());
            break;

          case 'el1503046938800':
            $paragraph = $entity->getTranslation('hu')->get('field_para');
            $targets = $paragraph->getValue();
            $target = array_shift($targets);

            $para = Paragraph::load($target['target_id']);
            $para_targets = $para->getTranslation('hu')->get('field_image')->getValue();
            $para_target = array_shift($para_targets);

            $img = File::load($para_target['target_id']);
            $image_url = $img->url();

            $this->assertNotEquals($image_url, $field->url);
            break;

          case 'el1503046938801':
            $paragraph = $entity->getTranslation('hu')->get('field_para');
            $targets = $paragraph->getValue();
            $target = array_pop($targets);

            $para = Paragraph::load($target['target_id']);
            $value = $para->getTranslation('hu')->get('field_text')->getValue()[0]['value'];

            $this->assertEquals($value, $field->getValue());
            break;

          case 'el1503050171534':
            $value = $entity->getTranslation('hu')->get('field_guidodo')->getValue()[0]['value'];
            $this->assertNotEquals($value, $field->getValue());
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

}
