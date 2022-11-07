<?php

namespace Drupal\Tests\gathercontent_upload\Kernel;

use GatherContent\DataTypes\Item;
use Drupal\file\Entity\File;
use Drupal\gathercontent\MappingLoader;
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
abstract class GatherContentUploadTestBase extends EntityKernelTestBase {

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
  protected static $modules = [
    'node',
    'field',
    'image',
    'file',
    'taxonomy',
    'language',
    'content_translation',
    'entity_reference_revisions',
    'paragraphs',
    'migrate',
    'migrate_tools',
    'migrate_plus',
    'token',
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
   * Returns mapping for a GatherContent Item.
   *
   * @param \GatherContent\DataTypes\Item $gcItem
   *   GatherContent Item object.
   *
   * @return mixed
   *   Mapping object.
   */
  public function getMapping(Item $gcItem) {
    return MappingLoader::load($gcItem);
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
      'gathercontent_option_ids' => 'ad10caf0-239b-473f-b106-6f615a35f574',
    ]);
    $term_1->save();

    $term_2 = Term::create([
      'vid' => 'tags',
      'name' => 'Choice1',
      'gathercontent_option_ids' => 'd009aae5-a91d-4a57-bc00-e8888b738c8d',
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
   * @return \GatherContent\DataTypes\Item
   *   Item object.
   */
  public function getSimpleItem() {
    return new Item([
      'project_id' => 86701,
      'template_id' => 791717,
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
    $paragraph_1_hu->save();

    $paragraph_2 = Paragraph::create([
      'type' => 'para_2',
      'langcode' => 'en',
      'field_text' => 'Test paragraph 2 field',
    ]);
    $paragraph_2->save();
    $paragraph_2_hu = $paragraph_2->addTranslation('hu');
    $paragraph_2_hu->field_text->setValue('Test multilang paragraph 2 HU');
    $paragraph_2_hu->save();

    $term_1 = Term::create([
      'vid' => 'tags',
      'langcode' => 'en',
      'name' => 'First choice',
      'gathercontent_option_ids' => '1d4674fa-764e-40e9-839e-67093c1398f0',
    ]);
    $term_1->save();

    $term_1_hu = Term::create([
      'vid' => 'tags',
      'langcode' => 'en',
      'name' => 'Second choice',
      'gathercontent_option_ids' => '35961a8e-7f64-4ba7-8a12-07e4bb3e1361',
    ]);
    $term_1_hu->save();

    $term_2 = Term::create([
      'vid' => 'tags',
      'langcode' => 'en',
      'name' => 'Choice1',
      'gathercontent_option_ids' => 'f61122ad-bada-47d2-8481-0e8c72448c3f',
    ]);
    $term_2->save();

    $term_2_hu = Term::create([
      'vid' => 'tags',
      'langcode' => 'en',
      'name' => 'Choice2',
      'gathercontent_option_ids' => '9c304ce9-0619-48eb-8a8e-b7e2ec157b28',
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
   * @return \GatherContent\DataTypes\Item
   *   Item object.
   */
  public function getMultilangItem() {
    return new Item([
      'project_id' => 86701,
      'template_id' => 821317,
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
   * @return \GatherContent\DataTypes\Item
   *   Item object.
   */
  public function getMetatagItem() {
    return new Item([
      'project_id' => 86701,
      'template_id' => 823399,
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
   * @return \GatherContent\DataTypes\Item
   *   Item object.
   */
  public function getMetatagMultilangItem() {
    return new Item([
      'project_id' => 86701,
      'template_id' => 429623,
    ]);
  }

}
