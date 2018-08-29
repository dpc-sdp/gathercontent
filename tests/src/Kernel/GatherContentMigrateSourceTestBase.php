<?php

namespace Drupal\Tests\gathercontent\Kernel;

use Drupal\gathercontent_test\MockData;
use Drupal\gathercontent_test\MockDrupalGatherContentClient;
use Drupal\Tests\migrate\Kernel\MigrateSourceTestBase;

/**
 * Class GatherContentMigrateSourceTestBase.
 *
 * @package Drupal\Tests\gathercontent\Kernel
 */
abstract class GatherContentMigrateSourceTestBase extends MigrateSourceTestBase {

  /**
   * {@inheritdoc}
   */
  public static $modules = [
    'node', 'text', 'field', 'user', 'image', 'file', 'taxonomy', 'language',
    'content_translation', 'paragraphs', 'entity_reference_revisions', 'system',
    'metatag', 'menu_ui', 'menu_link_content', 'link', 'gathercontent', 'gathercontent_test',
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
    $this->installEntitySchema('menu_link_content');
    $this->installConfig(['gathercontent_test']);

    MockData::$drupalRoot = $this->getDrupalRoot();

    /** @var \Drupal\taxonomy\Entity\Term[] $terms */
    $terms = MockData::createTaxonomyTerms();

    foreach ($terms as $term) {
      $term->save();
    }
  }

  /**
   * Return Mock client for testing.
   *
   * @param array $source_data
   *   The source data that the source plugin will read.
   *
   * @return \Drupal\gathercontent_test\MockDrupalGatherContentClient
   *   Mock client object.
   */
  protected function getClient(array $source_data) {
    $client = new MockDrupalGatherContentClient(
      \Drupal::service('http_client')
    );

    $client->setMockItems($source_data);

    return $client;
  }

  /**
   * {@inheritdoc}
   */
  public function testSource(array $source_data, array $expected_data, $expected_count = NULL, array $configuration = [], $high_water = NULL) {
    $plugin = $this->getPlugin($configuration);

    // Since we don't yet inject the gathercontent client, we need to use a
    // reflection hack to set it in the plugin instance.
    $reflector = new \ReflectionObject($plugin);
    $property = $reflector->getProperty('client');
    $property->setAccessible(TRUE);
    $property->setValue($plugin, $this->getClient($source_data));

    parent::testSource($source_data, $expected_data, $expected_count, $configuration, $high_water);
  }

}
