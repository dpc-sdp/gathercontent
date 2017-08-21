<?php

namespace Drupal\Tests\gathercontent_upload\Kernel;

use Cheppers\GatherContent\DataTypes\Item;
use Drupal\file\Entity\File;
use Drupal\gathercontent_upload\Export\Exporter;
use Drupal\KernelTests\Core\Entity\EntityKernelTestBase;
use Drupal\node\Entity\Node;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\taxonomy\Entity\Term;

/**
 * Class GatherContentUploadTestBase.
 *
 * @package Drupal\Tests\gathercontent_upload\Kernel
 */
class GatherContentUploadTestBase extends EntityKernelTestBase {

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
    'metatag',
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
   * Returns the Node for the simple ProcessPane test.
   *
   * @return \Drupal\node\Entity\Node
   *   Node object.
   */
  public function getSimpleNode() {
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

    return Node::create([
      'title' => 'Test node',
      'type' => 'page',
      'body' => 'Test body',
      'field_guidodo' => 'Test guide',
      'field_image' => [['target_id' => $image->id()]],
      'field_radio' => [['target_id' => $term_1->id()]],
      'field_tags_alt' => [['target_id' => $term_2->id()]],
      'field_para' => [
        [
          'target_id' => $paragraph_1->id(),
          'target_revision_id' => $paragraph_1->getRevisionId(),
        ],
        [
          'target_id' => $paragraph_2->id(),
          'target_revision_id' => $paragraph_2->getRevisionId(),
        ],
      ],
    ]);
  }

  /**
   * Returns Item for the simple ProcessPane test.
   *
   * @return \Cheppers\GatherContent\DataTypes\Item
   *   Item object.
   */
  public function getSimpleItem() {
    return new Item([
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
  }

  /**
   * Returns the Node for the multilang ProcessPane test.
   *
   * @return \Drupal\node\Entity\Node
   *   Node object.
   */
  public function getMultilangNode() {
    $manager = \Drupal::service('content_translation.manager');
    $image = File::create(['uri' => 'public://example1.png']);
    $image->save();

    $image2 = File::create(['uri' => 'public://example2.png']);
    $image2->save();

    $paragraph_1 = Paragraph::create([
      'type' => 'para',
      'langcode' => 'en',
      'field_text' => 'Test paragraph field',
      'field_image' => [['target_id' => $image->id()]],
    ]);
    $paragraph_1->save();
    $paragraph_1_hu = $paragraph_1->addTranslation('hu');
    $paragraph_1_hu->field_text->setValue('Test multilang paragraph HU');
    $paragraph_1_hu->field_image->setValue([['target_id' => $image2->id()]]);
    $manager->getTranslationMetadata($paragraph_1_hu)->setSource('en');
    $paragraph_1_hu->save();

    $paragraph_2 = Paragraph::create([
      'type' => 'para_2',
      'langcode' => 'en',
      'field_text' => 'Test paragraph 2 field',
    ]);
    $paragraph_2->save();
    $paragraph_2_hu = $paragraph_2->addTranslation('hu');
    $paragraph_2_hu->field_text->setValue('Test multilang paragraph 2 HU');
    $manager->getTranslationMetadata($paragraph_2_hu)->setSource('en');
    $paragraph_2_hu->save();

    $term_1 = Term::create([
      'vid' => 'tags',
      'langcode' => 'en',
      'name' => 'First choice',
      'gathercontent_option_ids' => 'op1503046753704',
    ]);
    $term_1->save();

    $term_1_hu = Term::create([
      'vid' => 'tags',
      'langcode' => 'en',
      'name' => 'Second choice',
      'gathercontent_option_ids' => 'op15030467537057882',
    ]);
    $term_1_hu->save();

    $term_2 = Term::create([
      'vid' => 'tags',
      'langcode' => 'en',
      'name' => 'Choice1',
      'gathercontent_option_ids' => 'op1503046763383',
    ]);
    $term_2->save();

    $term_2_hu = Term::create([
      'vid' => 'tags',
      'langcode' => 'en',
      'name' => 'Choice2',
      'gathercontent_option_ids' => 'op1503046763384321',
    ]);
    $term_2_hu->save();

    $node = Node::create([
      'title' => 'Test multilang node',
      'langcode' => 'en',
      'type' => 'test_content',
      'body' => 'Test multilang body',
      'field_guidodo' => 'Test guide',
      'field_image' => [['target_id' => $image->id()]],
      'field_radio' => [['target_id' => $term_1->id()]],
      'field_tags' => [['target_id' => $term_2->id()]],
      'field_para' => [
        [
          'target_id' => $paragraph_1->id(),
          'target_revision_id' => $paragraph_1->getRevisionId(),
        ],
        [
          'target_id' => $paragraph_2->id(),
          'target_revision_id' => $paragraph_2->getRevisionId(),
        ],
      ],
    ]);
    $node->save();

    $node_hu = $node->addTranslation('hu');
    $node_hu->setTitle('Test multilang node HU');
    $node_hu->body->setValue('Test multilang body HU');
    $node_hu->field_guidodo->setValue('Test multilang guide HU');
    $node_hu->field_image->setValue([['target_id' => $image2->id()]]);
    $node_hu->field_radio->setValue([['target_id' => $term_1_hu->id()]]);
    $node_hu->field_tags->setValue([['target_id' => $term_2_hu->id()]]);
    $node_hu->field_para->setValue([
      [
        'target_id' => $paragraph_1->id(),
        'target_revision_id' => $paragraph_1->getRevisionId(),
      ],
      [
        'target_id' => $paragraph_2->id(),
        'target_revision_id' => $paragraph_2->getRevisionId(),
      ],
    ]);
    $manager->getTranslationMetadata($node_hu)->setSource('en');
    $node_hu->save();

    return $node;
  }

  /**
   * Returns Item for the multilang ProcessPane test.
   *
   * @return \Cheppers\GatherContent\DataTypes\Item
   *   Item object.
   */
  public function getMultilangItem() {
    return new Item([
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
            [
              'name' => 'el1503046930689',
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
              'name' => 'el1503046753703',
              'type' => 'choice_radio',
              'label' => 'Radiogaga',
              'required' => FALSE,
              'microcopy' => '',
              'options' => [
                [
                  'name' => 'op1503046753704',
                  'label' => 'First choice',
                  'selected' => FALSE,
                ],
                [
                  'name' => 'op1503046753705',
                  'label' => 'Second choice',
                  'selected' => TRUE,
                ],
                [
                  'name' => 'op1503046753706',
                  'label' => 'Third choice',
                  'selected' => FALSE,
                ],
              ],
              'other_option' => FALSE,
            ],
            [
              'name' => 'el1503046763382',
              'type' => 'choice_checkbox',
              'label' => 'Tags',
              'required' => FALSE,
              'microcopy' => '',
              'options' => [
                [
                  'name' => 'op1503046763383',
                  'label' => 'Choice1',
                  'selected' => FALSE,
                ],
                [
                  'name' => 'op1503046763384',
                  'label' => 'Choice2',
                  'selected' => FALSE,
                ],
              ],
            ],
            [
              'name' => 'el1503046796344',
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
              'name' => 'el1503046889180',
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
              'name' => 'el1503046917174',
              'type' => 'text',
              'label' => 'Para 2 text',
              'required' => FALSE,
              'microcopy' => '',
              'limit_type' => 'words',
              'limit' => 0,
              'plain_text' => FALSE,
              'value' => 'Para 2 text gc item',
            ],
            [
              'name' => 'el1503050151209',
              'type' => 'section',
              'title' => 'Guido',
              'subtitle' => 'Guido gc item',
            ],
          ],
        ],
        [
          'name' => 'tab1503046938794',
          'label' => 'HU',
          'hidden' => FALSE,
          'elements' => [
            [
              'name' => 'el1503046938794',
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
              'name' => 'el1503046938795',
              'type' => 'text',
              'label' => 'Body',
              'required' => FALSE,
              'microcopy' => '',
              'limit_type' => 'words',
              'limit' => 0,
              'plain_text' => FALSE,
              'value' => 'Body gc item HU',
            ],
            [
              'name' => 'el1503046938796',
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
              'name' => 'el1503046938797',
              'type' => 'choice_radio',
              'label' => 'Radiogaga',
              'required' => FALSE,
              'microcopy' => '',
              'options' => [
                [
                  'name' => 'op15030467537046960',
                  'label' => 'First choice',
                  'selected' => TRUE,
                ],
                [
                  'name' => 'op15030467537057882',
                  'label' => 'Second choice',
                  'selected' => FALSE,
                ],
                [
                  'name' => 'op15030467537069199',
                  'label' => 'Third choice',
                  'selected' => FALSE,
                ],
              ],
              'other_option' => FALSE,
            ],
            [
              'name' => 'el1503046938798',
              'type' => 'choice_checkbox',
              'label' => 'Tags',
              'required' => FALSE,
              'microcopy' => '',
              'options' => [
                [
                  'name' => 'op1503046763383887',
                  'label' => 'Choice1',
                  'selected' => FALSE,
                ],
                [
                  'name' => 'op1503046763384321',
                  'label' => 'Choice2',
                  'selected' => FALSE,
                ],
              ],
            ],
            [
              'name' => 'el1503046938799',
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
              'name' => 'el1503046938800',
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
              'name' => 'el1503046938801',
              'type' => 'text',
              'label' => 'Para 2 text',
              'required' => FALSE,
              'microcopy' => '',
              'limit_type' => 'words',
              'limit' => 0,
              'plain_text' => FALSE,
              'value' => 'Para 2 text gc item',
            ],
            [
              'name' => 'el1503050171534',
              'type' => 'section',
              'title' => 'Guido',
              'subtitle' => 'Guido gc item',
            ],
          ],
        ],
      ],
    ]);
  }

  /**
   * Returns the Node for the meta tag ProcessPane test.
   *
   * @return \Drupal\node\Entity\Node
   *   Node object.
   */
  public function getMetatagNode() {
    $node = Node::create([
      'title' => 'Test metatag node',
      'type' => 'test_content_meta',
      'body' => 'Test metatag body',
    ]);
    $node->get('field_meta_test')->setValue(serialize([
      'title' => 'Test meta title',
      'description' => 'Test meta description',
    ]));

    return $node;
  }

  /**
   * Returns Item for the meta tag ProcessPane test.
   *
   * @return \Cheppers\GatherContent\DataTypes\Item
   *   Item object.
   */
  public function getMetatagItem() {
    return new Item([
      'project_id' => 86701,
      'template_id' => 823399,
      'config' => [
        [
          'name' => 'tab1503044944021',
          'label' => 'Content',
          'hidden' => FALSE,
          'elements' => [
            [
              'name' => 'el1503045026098',
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
              'name' => 'el1503045033295',
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
          'name' => 'tab1503045040084',
          'label' => 'Meta',
          'hidden' => FALSE,
          'elements' => [
            [
              'name' => 'el1503045047082',
              'type' => 'text',
              'label' => 'Title',
              'required' => FALSE,
              'microcopy' => '',
              'limit_type' => 'words',
              'limit' => 0,
              'plain_text' => TRUE,
              'value' => 'Title gc item meta',
            ],
            [
              'name' => 'el1503045054663',
              'type' => 'text',
              'label' => 'Description',
              'required' => FALSE,
              'microcopy' => '',
              'limit_type' => 'words',
              'limit' => 0,
              'plain_text' => FALSE,
              'value' => 'Description gc item meta',
            ],
          ],
        ],
      ],
    ]);
  }

  /**
   * Returns the Node for the meta tag multilang ProcessPane test.
   *
   * @return \Drupal\node\Entity\Node
   *   Node object.
   */
  public function getMetatagMultilangNode() {
    $node = Node::create([
      'title' => 'Test metatag node',
      'type' => 'test_content',
      'body' => 'Test metatag body',
    ]);
    $node->get('field_meta_alt')->setValue(serialize([
      'title' => 'Test meta title',
      'description' => 'Test meta description',
    ]));

    return $node;
  }

  /**
   * Returns Item for the meta tag multilang ProcessPane test.
   *
   * @return \Cheppers\GatherContent\DataTypes\Item
   *   Item object.
   */
  public function getMetatagMultilangItem() {
    return new Item([
      'project_id' => 86701,
      'template_id' => 429623,
      'config' => [
        [
          'name' => 'tab1475138035227',
          'label' => 'Content',
          'hidden' => FALSE,
          'elements' => [
            [
              'name' => 'el1502978044104',
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
              'name' => 'el1475138048898',
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
          'name' => 'tab1475138055858',
          'label' => 'Meta',
          'hidden' => FALSE,
          'elements' => [
            [
              'name' => 'el1475138068185',
              'type' => 'text',
              'label' => 'Title',
              'required' => FALSE,
              'microcopy' => '',
              'limit_type' => 'words',
              'limit' => 0,
              'plain_text' => TRUE,
              'value' => 'Title gc item meta',
            ],
            [
              'name' => 'el1475138069769',
              'type' => 'text',
              'label' => 'Description',
              'required' => FALSE,
              'microcopy' => '',
              'limit_type' => 'words',
              'limit' => 0,
              'plain_text' => FALSE,
              'value' => 'Description gc item meta',
            ],
          ],
        ],
      ],
    ]);
  }

}
