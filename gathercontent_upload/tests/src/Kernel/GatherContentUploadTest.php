<?php

namespace Drupal\Tests\gathercontent_upload\Kernel;

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
  public function testProcessGroupsMultilang() {
    $node = $this->getMultilangNode();
    $gcItem = $this->getMultilangItem();
    $mapping = $this->getMapping($gcItem);

    $modifiedItem = $this->exporter->processGroups($node, $mapping);

    $this->assertNotEmpty($modifiedItem);
    $this->assertItemChangedMultilang($modifiedItem, $node);
  }

  /**
   * Checks if all the fields are correctly set for multilingual content.
   *
   * @param array $content
   *   Content array.
   * @param \Drupal\node\NodeInterface $entity
   *   Node entity object.
   */
  public function assertItemChangedMultilang(array $content, NodeInterface $entity) {
    foreach ($content as $id => $fieldValue) {
      switch ($id) {
        case 'a91274c7-d273-4bad-82e4-caacc2175285':
          $this->assertEquals($entity->getTranslation('en')->getTitle(), $fieldValue);
          break;

        case '97c8625d-e304-44ec-a610-c7f193330fc8':
          $value = $entity->getTranslation('en')->get('body')->getValue()[0]['value'];
          $this->assertEquals($value, $fieldValue);
          break;

        case '20a410c9-28ba-44d4-a2e7-907b935da5fa':
          $radio = $entity->getTranslation('en')->get('field_radio');
          $this->assertSelection($fieldValue, $radio);
          break;

        case '8fb45eed-3453-4d29-8977-2a7c9d982c5e':
          $checkbox = $entity->getTranslation('en')->get('field_tags');
          $this->assertSelection($fieldValue, $checkbox);
          break;

        case '25e99975-d918-4cc3-a676-500d839a14c5':
          $paragraph = $entity->getTranslation('en')->get('field_para');
          $this->assertParagraphText($fieldValue, $paragraph);
          break;

        case '215538c7-ec2e-41d6-a433-c23d46bf1e60':
          $paragraph = $entity->getTranslation('en')->get('field_para');
          $this->assertParagraphText($fieldValue, $paragraph, TRUE);
          break;

        case 'f00dad7a-8429-4939-8014-498d5a4f41bd':
          $value = $entity->getTranslation('en')->get('field_guidodo')->getValue()[0]['value'];
          $this->assertNotEquals($value, $fieldValue);
          break;

        case 'beb19611-0685-483b-b409-7a47e696eb4b':
          $this->assertEquals($entity->getTranslation('hu')->getTitle(), $fieldValue);
          break;

        case 'c3dc73e4-1614-4f56-a09b-997664bd00f4':
          $value = $entity->getTranslation('hu')->get('body')->getValue()[0]['value'];
          $this->assertEquals($value, $fieldValue);
          break;

        case 'e33e4ada-a977-4c63-bfa5-df325f65e65d':
          $radio = $entity->getTranslation('hu')->get('field_radio');
          $this->assertSelection($fieldValue, $radio);
          break;

        case '64896363-bd4a-4f54-9a82-fec9f0137a3d':
          $checkbox = $entity->getTranslation('hu')->get('field_tags');
          $this->assertSelection($fieldValue, $checkbox);
          break;

        case 'e167651a-20ee-48cd-b4ac-5baaeae27c19':
          $paragraph = $entity->getTranslation('hu')->get('field_para');
          $this->assertParagraphText($fieldValue, $paragraph, FALSE, TRUE);
          break;

        case '53295e60-dad8-430b-af3c-cc190eab4a39':
          $paragraph = $entity->getTranslation('hu')->get('field_para');
          $this->assertParagraphText($fieldValue, $paragraph, TRUE, TRUE);
          break;

        case '81b210c6-b1fb-444f-a320-db58836b68de':
          $value = $entity->getTranslation('hu')->get('field_guidodo')->getValue()[0]['value'];
          $this->assertNotEquals($value, $fieldValue);
          break;

        case '715695f8-07db-4c5e-926f-bcec64412430':
        case '2859eea4-5aff-4eab-9fcb-88120deea6cc':
        case '135e837c-a9dd-4079-9b95-ce49a3b94cce':
        case 'cb711089-9121-4257-8927-b1577d6e59e9':
          // Image upload is not implemented yet.
          break;
      }
    }
  }

  /**
   * Tests field manipulation for metatag content.
   */
  public function testProcessPanesMetatag() {
    $node = $this->getMetatagNode();
    $gcItem = $this->getMetatagItem();
    $mapping = $this->getMapping($gcItem);

    $modifiedItem = $this->exporter->processGroups($node, $mapping);

    $this->assertNotEmpty($modifiedItem);
    $this->assertItemChangedMetatag($modifiedItem, $node);
  }

  /**
   * Checks if all the fields are correctly set for metatag content.
   *
   * @param array $content
   *   Content array.
   * @param \Drupal\node\NodeInterface $entity
   *   Node entity object.
   */
  public function assertItemChangedMetatag(array $content, NodeInterface $entity) {
    foreach ($content as $id => $fieldValue) {
      switch ($id) {
        case 'c59b2682-e22a-413b-88d1-f63dfccb3e8b':
          $this->assertEquals($entity->getTitle(), $fieldValue);
          break;

        case '45a1ef4d-16c5-41a8-aafb-bdc0b5dffe3b':
          $value = $entity->get('body')->getValue()[0]['value'];
          $this->assertEquals($value, $fieldValue);
          break;

        case '5188d4ef-d391-4286-baa4-667b103145fd':
          $meta_value = unserialize($entity->get('field_meta_test')->value);
          $this->assertEquals($meta_value['title'], $fieldValue);
          break;

        case 'ff93aedd-8add-413b-8313-23231f0045f8':
          $meta_value = unserialize($entity->get('field_meta_test')->value);
          $this->assertEquals($meta_value['description'], $fieldValue);
          break;
      }
    }
  }

  /**
   * Tests field manipulation for metatag content.
   */
  public function testProcessPanesMetatagMultilang() {
    $node = $this->getMetatagMultilangNode();
    $gcItem = $this->getMetatagMultilangItem();
    $mapping = $this->getMapping($gcItem);

    $modifiedItem = $this->exporter->processGroups($node, $mapping);

    $this->assertNotEmpty($modifiedItem);
    $this->assertItemChangedMetatagMultilang($modifiedItem, $node);
  }

  /**
   * Checks if all the fields are correctly set for metatag content.
   *
   * @param array $content
   *   Content array.
   * @param \Drupal\node\NodeInterface $entity
   *   Node entity object.
   */
  public function assertItemChangedMetatagMultilang(array $content, NodeInterface $entity) {
    foreach ($content as $id => $fieldValue) {
      switch ($id) {
        case 'be66d719-ae0e-4c31-ad57-9a07ba3b1aaf':
          $this->assertEquals($entity->getTitle(), $fieldValue);
          break;

        case '66da5837-604a-45d9-a72e-484cdd963076':
          $value = $entity->get('body')->getValue()[0]['value'];
          $this->assertEquals($value, $fieldValue);
          break;

        case '836a5f14-f93e-47c7-9ec3-0ac511b104b8':
          $meta_value = unserialize($entity->get('field_meta_alt')->value);
          $this->assertEquals($meta_value['title'], $fieldValue);
          break;

        case '8ea8bea0-8a78-4a48-a04b-3d8ff6c8c568':
          $meta_value = unserialize($entity->get('field_meta_alt')->value);
          $this->assertEquals($meta_value['description'], $fieldValue);
          break;
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
