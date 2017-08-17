<?php

namespace Drupal\Tests\gathercontent\Kernel;

use Cheppers\GatherContent\DataTypes\Item;
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
    $this->terms = MockData::createTaxonomyTerms();
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
   * Test if entities are created correctly based on GC Items.
   */
  public function testCreateNode() {
    $gc_item = MockData::createItem($this->mapping);
    $options = new ImportOptions(NodeUpdateMethod::ALWAYS_CREATE);
    $operation = Operation::create([
      'type' => 'import',
    ]);
    $options->setOperationUuid($operation->uuid());
    $is_translatable = \Drupal::moduleHandler()
      ->moduleExists('content_translation')
      && \Drupal::service('content_translation.manager')
        ->isEnabled('node', $this->mapping->getContentType());
    $node = $this->processor->createNode($gc_item, $this->mapping, $is_translatable, [
      MockData::createFile($this->getDrupalRoot()),
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
          static::assertCheckboxFieldEqualsOptions($field, $element->options);
          break;

        case 'choice_radio':
          static::assertRadioFieldEqualsOptions($field, $element->options);
          break;

        default:
          throw new \Exception("Unexpected element type: {$element->type}");
      }

    }
  }

  /**
   * Assertion for checkbox elements.
   */
  public static function assertCheckboxFieldEqualsOptions(array $field, array $options) {
    $termIds = array_map(function ($propertyArray) {
      return $propertyArray['target_id'];
    }, $field);
    $terms = Term::loadMultiple($termIds);

    foreach ($options as $option) {
      $termsMatchingThisOption = array_filter($terms, function ($term) use ($option) {
        $isSameGcOptionId = $term->get('gathercontent_option_ids')->getValue()[0]['value'] == $option['name'];
        $isSameName = $term->get('name')->getValue()[0]['value'] == $option['label'];
        $isCheckboxTaxonomy = $term->get('vid')->getValue()[0]['target_id'] === MockData::CHECKBOX_TAXONOMY_NAME;
        return $isSameGcOptionId && $isSameName && $isCheckboxTaxonomy;
      });

      if ($option['selected']) {
        static::assertEquals(1, count($termsMatchingThisOption));
      }
      else {
        static::assertEmpty($termsMatchingThisOption);
      }
    }
  }

  /**
   * Assertion for radio elements.
   */
  public static function assertRadioFieldEqualsOptions(array $field, array $options) {
    $selectedOptions = array_filter($options, function ($option) {
      return $option['selected'];
    });

    // No radios selected.
    if (empty($field)) {
      static::assertEmpty($selectedOptions);
      return;
    }

    // If there are selected radios, there must only be one.
    static::assertEquals(1, count($field));
    $termId = reset($field)['target_id'];
    static::assertTrue(is_string($termId));

    $term = Term::load($termId);
    static::assertEquals(MockData::RADIO_TAXONOMY_NAME, $term->get('vid')->getValue()[0]['target_id']);
    static::assertNotEmpty($selectedOptions);

    $optionsMatchingThisTerm = array_filter($selectedOptions, function ($option) use ($term) {
      $isSameName = $term->get('name')->getValue()[0]['value'] == $option['label'];
      $isSameGcOptionId = $term->get('gathercontent_option_ids')->getValue()[0]['value'] == $option['name'];
      return $isSameName && $isSameGcOptionId;
    });

    static::assertEquals(1, count($optionsMatchingThisTerm));
  }

}
