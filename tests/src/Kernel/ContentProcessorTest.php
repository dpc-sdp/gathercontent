<?php

namespace Drupal\Tests\gathercontent\Kernel;

use Cheppers\GatherContent\DataTypes\Element;
use Cheppers\GatherContent\DataTypes\Item;
use Drupal\file\Entity\File;
use Drupal\gathercontent\Entity\Operation;
use Drupal\gathercontent\Import\ContentProcess\ContentProcessor;
use Drupal\gathercontent\Import\ImportOptions;
use Drupal\gathercontent\Import\NodeUpdateMethod;
use Drupal\gathercontent\MappingLoader;
use Drupal\KernelTests\KernelTestBase;
use Drupal\node\NodeInterface;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\taxonomy\Entity\Term;

/**
 * Class for testing core node import functionality.
 *
 * - basic fields import.
 * - paragraph fields import.
 * - entity translation import.
 * - metatag import.
 * - paragraph taxonomy term import.
 *
 * @group gathercontent
 */
class ContentProcessorTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'node', 'text', 'field', 'user', 'image', 'file', 'taxonomy', 'language',
    'content_translation', 'paragraphs', 'entity_reference_revisions', 'system',
    'gathercontent', 'test_module', 'metatag',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();
    $this->installSchema('node', 'node_access');
    $this->installEntitySchema('node');
    $this->installEntitySchema('gathercontent_operation');
    $this->installEntitySchema('file');
    $this->installSchema('file', ['file_usage']);
    $this->installEntitySchema('taxonomy_term');
    $this->installEntitySchema('paragraph');
    $this->installEntitySchema('user');
    $this->installConfig(['test_module']);
    MockData::$drupalRoot = $this->getDrupalRoot();
    /** @var \Drupal\taxonomy\Entity\Term[] $terms */
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
    $mapping = MockData::getMapping();
    $item = MockData::createItem(
      $mapping,
      [TRUE, FALSE, TRUE],
      [TRUE, FALSE, FALSE]
    );
    $importOptions = new ImportOptions(NodeUpdateMethod::ALWAYS_CREATE);

    $cases = [
      'no checkboxes, no radioboxes' => [
        MockData::createItem(
          $mapping,
          [FALSE, FALSE, FALSE],
          [FALSE, FALSE, FALSE]
        ),
        $importOptions,
        [],
      ],
      'no checkboxes, 1 radiobox' => [
        MockData::createItem(
          $mapping,
          [FALSE, FALSE, FALSE],
          [TRUE, FALSE, FALSE]
        ),
        $importOptions,
        [],
      ],
      'no checkboxes, 3 radioboxes' => [
        MockData::createItem(
          $mapping,
          [FALSE, FALSE, FALSE],
          [TRUE, TRUE, TRUE]
        ),
        $importOptions,
        [],
      ],
      'all checkboxes, no radioboxes' => [
        MockData::createItem(
          $mapping,
          [TRUE, TRUE, TRUE],
          [FALSE, FALSE, FALSE]
        ),
        $importOptions,
        [],
      ],
      '1 file' => [
        $item, $importOptions, MockData::createFile($item),
      ],
      '3 files' => [
        $item, $importOptions,
        MockData::createFile($item) +
        MockData::createFile($item) +
        MockData::createFile($item),
      ],
    ];

    foreach ($cases as $caseName => $params) {
      print $caseName . PHP_EOL;
      call_user_func_array([static::class, 'createNodeTest'], $params);;
    }
  }

  /**
   * Test if entities are created correctly based on GC Items.
   */
  public static function createNodeTest(Item $gcItem, ImportOptions $importOptions, array $files) {
    $operation = Operation::create([
      'type' => 'import',
    ]);
    $importOptions->setOperationUuid($operation->uuid());
    $node = static::getProcessor()->createNode($gcItem, $importOptions, $files);
    $node->save();
    static::assertNodeEqualsGcItem($node->getTranslation('en'), $gcItem, $files);
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
  public static function assertNodeEqualsGcItem(NodeInterface $node, Item $gcItem, array $files) {
    /** @var \Drupal\gathercontent\Entity\Mapping $mapping */
    $mapping = MappingLoader::load($gcItem);
    $tabs = unserialize($mapping->getData());
    $metatagTab = $tabs[MockData::METATAG_TAB]['elements'];
    unset($tabs[MockData::METATAG_TAB]);

    $fields = $node->toArray();
    $translation = $node->getTranslation('hu');
    $translatedFields = $translation->toArray();

    foreach ($tabs as $tabId => $tab) {
      /** @var \Cheppers\GatherContent\DataTypes\Element[] $elements */
      $elements = $gcItem->config[$tabId]->elements;
      $itemMapping = $tab['elements'];

      foreach ($itemMapping as $gcId => $fieldId) {
        $ids = explode('||', $fieldId);
        $filesMatchingThisElement = array_filter($files, function ($file) use ($gcId) {
          return $file->field == $gcId;
        });
        if (count($ids) > 1) {
          // Paragraph.
          if ($tabId === MockData::TRANSLATED_TAB) {
            $field = static::loadFieldFromNode($translation, $ids, $tab['language']);
            static::assertFieldEqualsElement($field, $elements[$gcId], $filesMatchingThisElement);
          }
          else {
            $field = static::loadFieldFromNode($node, $ids, $tab['language']);
            static::assertFieldEqualsElement($field, $elements[$gcId], $filesMatchingThisElement);
          }
        }
        else {
          if ($fieldId === 'title') {
            static::assertTranslatedEquals($node->getTitle(), $translation->getTitle());
            static::assertEquals($node->getTitle(), $gcItem->name);
          }
          else {
            // Basic fields.
            if ($tabId === MockData::TRANSLATED_TAB) {
              $fieldName = explode('.', $fieldId)[2];
              static::assertFieldEqualsElement($translatedFields[$fieldName], $elements[$gcId], $filesMatchingThisElement);
            }
            else {
              $fieldName = explode('.', $fieldId)[2];
              static::assertFieldEqualsElement($fields[$fieldName], $elements[$gcId], $filesMatchingThisElement);
            }
          }
        }
      }
    }

    // Metatags.
    $insertedMetatags = unserialize(reset($fields[MockData::METATAG_FIELD])['value']);
    $metatagElements = $gcItem->config[MockData::METATAG_TAB]->elements;
    foreach ($metatagTab as $gcId => $fieldName) {
      static::assertEquals($metatagElements[$gcId]->value, $insertedMetatags[$fieldName]);
    }
  }

  /**
   * Function for asserting that a translated value matches the original one.
   */
  public static function assertTranslatedEquals($original, $translated) {
    static::assertEquals($translated, $original . ' translated');
  }

  /**
   * Read field from id like "node.mytype.myfiled||paragraph.myptype.mypfield".
   */
  public static function loadFieldFromNode(NodeInterface $node, array $ids, $language) {
    if (count($ids) == 1) {
      throw new \InvalidArgumentException('"$ids" is not a nested id');
    }

    $currentEntity = $node;
    for ($i = 0; $i < count($ids) - 1; $i++) {
      $currentFieldName = explode('.', $ids[$i])[2];
      $targetField = reset($currentEntity->get($currentFieldName)->getValue());
      $currentEntity = Paragraph::load($targetField['target_id']);
    }

    $lastFieldName = explode('.', end($ids))[2];
    $currentEntity = $currentEntity->getTranslation($language);
    return $currentEntity->toArray()[$lastFieldName];
  }

  /**
   * Assertion for Drupal field and GC element.
   */
  public static function assertFieldEqualsElement(array $field, Element $element, array $files) {
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
    $fileId = reset($field)['target_id'];
    $fileField = File::load($fileId);
    static::assertNotNull($fileField);
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
    $termIds = array_map(function ($fieldTerm) {
      return $fieldTerm['target_id'];
    }, $field);
    $terms = Term::loadMultiple($termIds);

    foreach ($options as $option) {
      $termsMatchingThisOption = array_filter($terms, function ($term) use ($option) {
        $termOptionIdsMatchingThisOption = array_filter(
          $term->get('gathercontent_option_ids')->getValue(),
          function ($optionId) use ($option) {
            return $optionId['value'] === $option['name'];
          }
        );
        $isSameOptionId = 1 === count($termOptionIdsMatchingThisOption);
        $isSameName = $term->get('name')->getValue()[0]['value'] == $option['label'];
        $isCheckboxTaxonomy = $term->get('vid')->getValue()[0]['target_id'] === MockData::CHECKBOX_TAXONOMY_NAME;
        return $isSameOptionId && $isSameName && $isCheckboxTaxonomy;
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
      static::assertEmpty(
        $selectedOptions,
        "No taxonomy term inserted in node.\nExpected radio item:\n" .
        print_r($selectedOptions, TRUE)
      );
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
      $termOptionIdsMatchingThisOption = array_filter(
        $term->get('gathercontent_option_ids')->getValue(),
        function ($optionId) use ($option) {
          return $optionId['value'] === $option['name'];
        }
      );
      $isSameOptionId = 1 === count($termOptionIdsMatchingThisOption);
      return $isSameName && $isSameOptionId;
    });

    static::assertEquals(1, count($optionsMatchingThisTerm));
  }

}
