<?php

namespace Drupal\Tests\gathercontent_upload\Kernel;

use Cheppers\GatherContent\DataTypes\Item;
use Drupal\file\Entity\File;
use Drupal\gathercontent_upload\Export\Exporter;
use Drupal\KernelTests\Core\Entity\EntityKernelTestBase;
use Drupal\node\Entity\Node;
use Drupal\node\NodeInterface;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\taxonomy\Entity\Term;

/**
 * @coversDefaultClass \Drupal\gathercontent_upload\Export\Exporter
 * @group gathercontent_upload
 */
class GatherContentUploadTest extends EntityKernelTestBase {

  /**
   * Exporter class.
   *
   * @var \Drupal\gathercontent_upload\Export\Exporter
   */
  public $exporter;

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = [
    'node',
    'field',
    'image',
    'file',
    'taxonomy',
    'language',
    'content_translation',
    'entity_reference_revisions',
    'paragraphs',
    'gathercontent',
    'gathercontent_upload',
    'gathercontent_upload_test_config',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $this->installSchema('node', 'node_access');
    $this->installEntitySchema('node');
    $this->installConfig(['gathercontent_upload_test_config']);
    $this->installEntitySchema('file');
    $this->installSchema('file', ['file_usage']);
    $this->installEntitySchema('paragraph');
    $this->installEntitySchema('taxonomy_term');

    $container = \Drupal::getContainer();
    $this->exporter = Exporter::create($container);
  }

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
    $image = File::create(['uri' => 'public://example1.png']);
    $image->save();

    $paragraph_1 = Paragraph::create([
      'type' => 'para',
      'field_text' => 'Test paragraph field',
      'field_image' => [['target_id' => $image->id()]],
    ]);
    $paragraph_1->save();

    $paragraph_2 = Paragraph::create([
      'type' => 'para_2',
      'field_text' => 'Test paragraph 2 field',
    ]);
    $paragraph_2->save();

    $term_1 = Term::create([
      'vid' => 'tags',
      'name' => 'First choice',
      'gathercontent_option_ids' => 'op1501678793028',
    ]);
    $term_1->save();

    $term_2 = Term::create([
      'vid' => 'tags',
      'name' => 'Choice1',
      'gathercontent_option_ids' => 'op1500994449663',
    ]);
    $term_2->save();

    $node = Node::create([
      'title' => 'Test node',
      'type' => 'page',
      'body' => 'Test body',
      'field_guidodo' => 'Test guide',
      'field_image' => [['target_id' => $image->id()]],
      'field_radio' => [['target_id' => $term_1->id()]],
      'field_tags_alt' => [['target_id' => $term_2->id()]],
      'field_para' => [
        ['target_id' => $paragraph_1->id()],
        ['target_id' => $paragraph_2->id()],
      ],
    ]);

    $gc_item = new Item([
      'project_id' => 86701,
      'template_id' => 791717,
      'config' => [
        [
          'name' => 'tab1500994234813',
          'label' => 'Tab label',
          'hidden' => FALSE,
          'elements' => [
            [
              'name' => 'el1501675275975',
              'type' => 'text',
              'label' => 'Title',
              'required' => FALSE,
              'microcopy' => '',
              'limit_type' => 'words',
              'limit' => 0,
              'plain_text' => TRUE,
              'value' => 'Title gc item',
            ],
            [
              'name' => 'el1501679176743',
              'type' => 'section',
              'title' => 'Guido',
              'subtitle' => 'Guido gc item',
            ],
            [
              'name' => 'el1501678793027',
              'type' => 'choice_radio',
              'label' => 'Radiogaga',
              'required' => FALSE,
              'microcopy' => '',
              'options' => [
                [
                  'name' => 'op1501678793028',
                  'label' => 'First choice',
                  'selected' => FALSE,
                ],
                [
                  'name' => 'op1501678793029',
                  'label' => 'Second choice',
                  'selected' => TRUE,
                ],
                [
                  'name' => 'op1501678793030',
                  'label' => 'Third choice',
                  'selected' => FALSE,
                ],
              ],
              'other_option' => FALSE,
            ],
            [
              'name' => 'el1500994248864',
              'type' => 'text',
              'label' => 'Body',
              'required' => FALSE,
              'microcopy' => '',
              'limit_type' => 'words',
              'limit' => 0,
              'plain_text' => FALSE,
              'value' => 'Body gc item',
            ],
            [
              'name' => 'el1501598415730',
              'type' => 'files',
              'label' => 'Image',
              'required' => FALSE,
              'microcopy' => '',
              'user_id' => 1,
              'item_id' => 1,
              'field' => 'el1501598415730',
              'url' => 'http://test.ts/example-image.jpg',
              'filename' => 'Test image gc item',
              'size' => '100',
              'created_at' => date('Y-m-d H:i:s', rand(0, time())),
              'updated_at' => date('Y-m-d H:i:s', rand(0, time())),
            ],
            [
              'name' => 'el1500994276297',
              'type' => 'choice_checkbox',
              'label' => 'Tags',
              'required' => FALSE,
              'microcopy' => '',
              'options' => [
                [
                  'name' => 'op1500994449663',
                  'label' => 'Choice1',
                  'selected' => FALSE,
                ],
                [
                  'name' => 'op1500994483697',
                  'label' => 'Choice2',
                  'selected' => FALSE,
                ],
              ],
            ],
            [
              'name' => 'el1501666239392',
              'type' => 'text',
              'label' => 'Para text',
              'required' => FALSE,
              'microcopy' => '',
              'limit_type' => 'words',
              'limit' => 0,
              'plain_text' => FALSE,
              'value' => 'Para text gc item',
            ],
            [
              'name' => 'el1501666248919',
              'type' => 'files',
              'label' => 'Para image',
              'required' => FALSE,
              'microcopy' => '',
              'user_id' => 1,
              'item_id' => 1,
              'field' => 'el1501666248919',
              'url' => 'http://test.ts/example-image.jpg',
              'filename' => 'Test para image gc item',
              'size' => '100',
              'created_at' => date('Y-m-d H:i:s', rand(0, time())),
              'updated_at' => date('Y-m-d H:i:s', rand(0, time())),
            ],
            [
              'name' => 'el1501772184393',
              'type' => 'text',
              'label' => 'Para 2 text',
              'required' => FALSE,
              'microcopy' => '',
              'limit_type' => 'words',
              'limit' => 0,
              'plain_text' => FALSE,
              'value' => 'Para 2 text gc item',
            ],
          ],
        ],
      ],
    ]);

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
    $node = Node::create([
      'langcode' => 'en',
      'title' => 'Test multilang node',
      'type' => 'test_content',
      'body' => 'Test multilang body',
    ]);
    $node->save();

    $node_hu = $node->addTranslation('hu');
    $node_hu->setTitle('Test multilang node HU');
    $node_hu->body->setValue('Test multilang body HU');
    $node_hu->save();

    $gc_item = new Item([
      'project_id' => 86701,
      'template_id' => 821317,
      'config' => [
        [
          'name' => 'tab1502959217871',
          'label' => 'EN',
          'hidden' => FALSE,
          'elements' => [
            [
              'name' => 'el1502959595615',
              'type' => 'text',
              'label' => 'Title',
              'required' => FALSE,
              'microcopy' => '',
              'limit_type' => 'words',
              'limit' => 0,
              'plain_text' => TRUE,
              'value' => 'Title gc item',
            ],
            [
              'name' => 'el1502959226216',
              'type' => 'text',
              'label' => 'Body',
              'required' => FALSE,
              'microcopy' => '',
              'limit_type' => 'words',
              'limit' => 0,
              'plain_text' => FALSE,
              'value' => 'Body gc item',
            ],
          ],
        ],
        [
          'name' => 'tab1502959263057',
          'label' => 'HU',
          'hidden' => FALSE,
          'elements' => [
            [
              'name' => 'el1502959611885',
              'type' => 'text',
              'label' => 'Title',
              'required' => FALSE,
              'microcopy' => '',
              'limit_type' => 'words',
              'limit' => 0,
              'plain_text' => TRUE,
              'value' => 'Title gc item HU',
            ],
            [
              'name' => 'el1502959286463',
              'type' => 'text',
              'label' => 'Body',
              'required' => FALSE,
              'microcopy' => '',
              'limit_type' => 'words',
              'limit' => 0,
              'plain_text' => FALSE,
              'value' => 'Body gc item HU',
            ],
          ],
        ],
      ],
    ]);

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

          case 'el1502959611885':
            $this->assertEquals($entity->getTranslation('hu')->getTitle(), $field->getValue());
            break;

          case 'el1502959286463':
            $value = $entity->getTranslation('hu')->get('body')->getValue()[0]['value'];
            $this->assertEquals($value, $field->getValue());
            break;
        }
      }
    }
  }

}
