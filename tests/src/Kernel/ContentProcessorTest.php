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
 * Class for testing node import.
 *
 * @group gathercontent
 */
class ContentProcessorTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'gathercontent', 'test_module', 'node', 'text', 'field',
    'image', 'file', 'taxonomy', 'language', 'content_translation',
  ];

  /**
   * The mapping bundled with the test module.
   *
   * @var \Drupal\gathercontent\Entity\Mapping
   */
  protected $mapping;

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();
    $this->installConfig(['test_module']);
    $this->installEntitySchema('gathercontent_operation');
    $this->installEntitySchema('file');
    $this->installSchema('file', ['file_usage']);
    $this->installEntitySchema('taxonomy_term');
    MockData::$drupalRoot = $this->getDrupalRoot();
    $this->mapping = MockData::getMapping();
    $terms = MockData::createTaxonomyTerms();
    foreach ($terms as $term) {
      $term->save();
    }
  }

  /**
   * Data provider for createNodeTest.
   *
   * Unfortunately real data providers get called before any other method.
   *
   * I couldn't find a better way to generate test cases
   * based on the bootstrapped Drupal installation (done in setUp)
   * than creating my own "data provider" like test function.
   */
  public function testCreateNode() {
    $item = MockData::createItem(
      $this->mapping,
      [TRUE, FALSE, TRUE],
      [TRUE, FALSE, FALSE]
    );
    $importOptions = new ImportOptions(NodeUpdateMethod::ALWAYS_CREATE);

    $cases = [
      'no checkboxes, no radioboxes' => [
        MockData::createItem(
          $this->mapping,
          [FALSE, FALSE, FALSE],
          [FALSE, FALSE, FALSE]
        ),
        $importOptions,
        [],
      ],
      'no checkboxes, 1 radiobox' => [
        MockData::createItem(
          $this->mapping,
          [FALSE, FALSE, FALSE],
          [TRUE, FALSE, FALSE]
        ),
        $importOptions,
        [],
      ],
      'no checkboxes, 3 radioboxes' => [
        MockData::createItem(
          $this->mapping,
          [FALSE, FALSE, FALSE],
          [TRUE, TRUE, TRUE]
        ),
        $importOptions,
        [],
      ],
      'all checkboxes, no radioboxes' => [
        MockData::createItem(
          $this->mapping,
          [TRUE, TRUE, TRUE],
          [FALSE, FALSE, FALSE]
        ),
        $importOptions,
        [],
      ],
      '1 file' => [
        $item, $importOptions, [
          MockData::createFile($item->id),
        ],
      ],
      '3 files' => [
        $item, $importOptions, [
          MockData::createFile($item->id),
          MockData::createFile($item->id),
          MockData::createFile($item->id),
        ],
      ],
    ];

    foreach ($cases as $caseName => $params) {
      call_user_func_array([$this, 'createNodeTest'], $params);
    }
  }

  /**
   * Test if entities are created correctly based on GC Items.
   */
  public function createNodeTest(Item $gcItem, ImportOptions $importOptions, array $files) {
    $operation = Operation::create([
      'type' => 'import',
    ]);
    $importOptions->setOperationUuid($operation->uuid());

    $is_translatable = \Drupal::moduleHandler()
      ->moduleExists('content_translation')
      && \Drupal::service('content_translation.manager')
        ->isEnabled('node', $this->mapping->getContentType());

    $node = static::getProcessor()->createNode($gcItem, $importOptions, $this->mapping, $files, $is_translatable);
    static::assertNodeEqualsGcItem($node, $gcItem, $this->mapping, $files);
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
   * Checks whether a node and a GC item contains the same data.
   */
  public static function assertNodeEqualsGcItem(NodeInterface $node, Item $gcItem, Mapping $mapping, array $files) {
    $tabs = unserialize($mapping->getData());
    $itemMapping = reset($tabs)['elements'];

    static::assertEquals($node->getTitle(), $gcItem->name);

    $fields = $node->toArray();
    /** @var \Cheppers\GatherContent\DataTypes\Element[] $elements */
    $elements = reset($gcItem->config)->elements;

    foreach ($itemMapping as $gcId => $fieldId) {
      $fieldId = explode('.', $fieldId)[2];
      $element = $elements[$gcId];
      $field = $fields[$fieldId];

      switch ($element->type) {
        case 'text':
          static::assertEquals($element->value, reset($field)['value']);
          break;

        case 'section':
          $section = '<h3>' . $element->title . '</h3>' . $element->subtitle;
          static::assertEquals($section, reset($field)['value']);
          break;

        case 'files':
          static::assertFileFieldEqualsResponseFiles($field, $files);
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
   * Assertion for file elements.
   */
  public static function assertFileFieldEqualsResponseFiles(array $field, array $files) {
    // No files attached to GC item.
    if (empty($files)) {
      static::assertEmpty($field);
      return;
    }

    // Always insert the latest file gotten from API.
    // There must only be one image in the field.
    static::assertEquals(1, count($field));
    /** @var \Drupal\file\Entity\File $fileField */
    $fileField = reset($field)['target_id'];
    /** @var \Cheppers\GatherContent\DataTypes\File $fileResponse */
    $fileResponse = end($files);

    static::assertEquals($fileResponse->url, $fileField->get('uri')->getValue()[0]['value']);
    static::assertEquals($fileResponse->id, $fileField->get('gc_id')->getValue()[0]['value']);
    static::assertEquals($fileResponse->fileName, $fileField->get('filename')->getValue()[0]['value']);
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
