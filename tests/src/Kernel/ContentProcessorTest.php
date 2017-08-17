<?php

namespace Drupal\Tests\gathercontent\Kernel;

use Cheppers\GatherContent\DataTypes\File;
use Cheppers\GatherContent\DataTypes\Item;
use Cheppers\GatherContent\DataTypes\Tab;
use Drupal\gathercontent\Entity\Mapping;
use Drupal\gathercontent\Entity\Operation;
use Drupal\gathercontent\Import\ContentProcess\ContentProcessor;
use Drupal\gathercontent\Import\ImportOptions;
use Drupal\gathercontent\Import\NodeUpdateMethod;
use Drupal\KernelTests\KernelTestBase;
use Drupal\node\NodeInterface;
use Drupal\taxonomy\Entity\Term;

/**
 * Node creation tests.
 *
 * @group gathercontent
 */
class ContentProcessorTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'gathercontent',
    'test_module',
    'node',
    'text',
    'field',
    'image',
    'file',
    'user',
    'taxonomy',
    'language',
    'content_translation',
    'entity_reference_revisions',
  ];

  const TEST_VALUES = [
    'choice_radio' => [],
    'choice_checkbox' => [],
    'files' => '',
    'text' => 'test text',
    'section' => 'test section',
  ];

  /**
   * The mapping bundled with the test module.
   *
   * @var \Drupal\gathercontent\Entity\Mapping
   */
  protected $mapping;

  /**
   * The ContentProcessor object.
   *
   * @var \Drupal\gathercontent\Import\ContentProcess\ContentProcessor
   */
  protected $processor;

  /**
   * Default test terms.
   *
   * @var \Drupal\taxonomy\Entity\Term[]
   */
  protected $terms;

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();
    $this->installSchema('node', 'node_access');
    $this->installEntitySchema('node');
    $this->installConfig(['test_module']);
    $this->installEntitySchema('gathercontent_operation');
    $this->installEntitySchema('file');
    $this->installSchema('file', ['file_usage']);
    $this->installEntitySchema('taxonomy_term');
    $this->init();
  }

  /**
   * Initialize member variables.
   */
  public function init() {
    $this->mapping = static::getMapping();
    $this->processor = static::getProcessor();
    $this->terms = static::createTaxonomyTerms();
    foreach ($this->terms as $term) {
      $term->save();
    }
  }

  /**
   * After installing the test configs read the mapping.
   */
  public static function getMapping() {
    $mapping_id = \Drupal::entityQuery('gathercontent_mapping')->execute();
    $mapping_id = reset($mapping_id);
    return Mapping::load($mapping_id);
  }

  /**
   * Get processor injected with mock object.
   */
  public static function getProcessor() {
    return new ContentProcessor(
      new MockDrupalGatherContentClient(
        \Drupal::service('http_client')
      )
    );
  }

  /**
   * Create the default test taxonomy terms after enabling the gathercontent_option_ids field.
   */
  public static function createTaxonomyTerms() {
    $terms = [];

    $terms[] = Term::create([
      'vid' => 'checkbox_test_taxonomy',
      'name' => 'First checkbox',
      'gathercontent_option_ids' => 'op1502871154842',
    ]);

    $terms[] = Term::create([
      'vid' => 'checkbox_test_taxonomy',
      'name' => 'Second checkbox',
      'gathercontent_option_ids' => 'op1502871154843',
    ]);

    $terms[] = Term::create([
      'vid' => 'checkbox_test_taxonomy',
      'name' => 'Third checkbox',
      'gathercontent_option_ids' => 'op1502871154844',
    ]);

    $terms[] = Term::create([
      'vid' => 'radio_test_taxonomy',
      'name' => 'First radio',
      'gathercontent_option_ids' => 'op1502871172350',
    ]);

    $terms[] = Term::create([
      'vid' => 'radio_test_taxonomy',
      'name' => 'Second radio',
      'gathercontent_option_ids' => 'op1502871172351',
    ]);

    $terms[] = Term::create([
      'vid' => 'radio_test_taxonomy',
      'name' => 'Third radio',
      'gathercontent_option_ids' => 'op1502871172352',
    ]);

    return $terms;
  }

  /**
   * Creates a GC Item corresponding to current mapping.
   */
  public function createItem() {
    $template = unserialize($this->mapping->getTemplate())->data;
    $tabs = $template->config;

    $item = new Item();
    $item->name = 'test item';
    $item->id = 1;
    $item->projectId = $template->project_id;
    $item->templateId = $template->template_id;

    foreach ($tabs as $tab) {
      $newTab = new Tab(json_decode(json_encode($tab), TRUE));
      foreach ($newTab->elements as $element) {
        switch ($element->type) {
          case 'text':
            $element->setValue(static::TEST_VALUES['text']);
            break;

          case 'files':
            // Files are handled elsewhere.
            break;

          case 'section':
            $element->subtitle = static::TEST_VALUES['section'];
            break;

          case 'choice_checkbox':
            $element->options[0]['selected'] = TRUE;
            break;

          case 'choice_radio':
            // Always pick the first one if more is selected.
            $element->options[0]['selected'] = TRUE;
            $element->options[1]['selected'] = TRUE;
            $element->options[2]['selected'] = TRUE;
            break;
        }
      }
      $item->config[$newTab->id] = $newTab;
    }

    return $item;
  }

  /**
   * Mock File response.
   */
  public function createFile() {
    $file = new File();
    $file->id = 1;
    $file->userId = 1;
    $file->itemId = 1;
    $file->field = 'el1502871120855';
    $file->url = $this->getDrupalRoot() . '/' . drupal_get_path('module', 'test_module') . '/images/test.jpg';
    $file->fileName = 'test.jpg';
    $file->size = 0;
    $file->createdAt = NULL;
    $file->updatedAt = NULL;
    return $file;
  }

  /**
   * Data provider for testCreateNode.
   */
  public function casesCreateNode() {
    return [
      'create node 1' => [
        new ImportOptions(NodeUpdateMethod::ALWAYS_CREATE),
      ],
    ];
  }

  /**
   * Test if entities are created correctly based on GC Items.
   *
   * @dataProvider casesCreateNode
   */
  public function testCreateNode(ImportOptions $options) {
    $gc_item = $this->createItem();
    $operation = Operation::create([
      'type' => 'import',
    ]);
    $options->setOperationUuid($operation->uuid());
    $is_translatable = \Drupal::moduleHandler()
      ->moduleExists('content_translation')
      && \Drupal::service('content_translation.manager')
        ->isEnabled('node', $this->mapping->getContentType());
    $node = $this->processor->createNode($gc_item, $this->mapping, $is_translatable, [
      $this->createFile(),
    ], $options);
    $this->assertNodeEqualsGcItem($node, $gc_item);
  }

  /**
   * Checks whether a node and a GC item contains the same data.
   */
  public function assertNodeEqualsGcItem(NodeInterface $node, Item $gc_item) {
    $tabs = unserialize($this->mapping->getData());
    $itemMapping = reset($tabs)['elements'];

    static::assertEquals($node->getTitle(), $gc_item->name);

    $fields = $node->toArray();
    /** @var \Cheppers\GatherContent\DataTypes\Element[] $elements */
    $elements = reset($gc_item->config)->elements;

    foreach ($itemMapping as $gcId => $fieldId) {
      $fieldId = explode('.', $fieldId)[2];
      $element = $elements[$gcId];
      $field = $fields[$fieldId];

      switch ($element->type) {
        case 'text':
          $nodeValue = reset($field)['value'];
          $gcItemValue = $element->value;
          static::assertEquals($nodeValue, $gcItemValue);
          break;

        case 'section':
          $nodeValue = reset($field)['value'];
          $gcItemValue = '<h3>' . $element->title . '</h3>' . $element->subtitle;
          static::assertEquals($nodeValue, $gcItemValue);
          break;

        case 'files':
          $testImageIds = \Drupal::entityQuery('file')
            ->condition('gc_id', 1)
            ->condition('filename', 'test.jpg')
            ->execute();
          static::assertEquals(count($testImageIds), 1);
          break;

        case 'choice_checkbox':
          $termId = reset($field)['target_id'];
          $term = Term::load($termId);
          static::assertEquals($term->get('gathercontent_option_ids')->getValue()[0]['value'], $element->options[0]['name']);
          break;

        case 'choice_radio':
          static::assertEquals(count($field), 1);
          $termId = reset($field)['target_id'];
          static::assertTrue(is_string($termId));
          $term = Term::load($termId);
          static::assertEquals($term->get('gathercontent_option_ids')->getValue()[0]['value'], $element->options[0]['name']);
          break;

        default:
          throw new \Exception("Unexpected element type: {$element->type}");
      }

    }
  }

}