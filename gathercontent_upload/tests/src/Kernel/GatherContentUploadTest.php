<?php

namespace Drupal\Tests\gathercontent_upload\Kernel;

use Cheppers\GatherContent\DataTypes\Item;
use Drupal\gathercontent_upload\Export\Exporter;
use Drupal\KernelTests\Core\Entity\EntityKernelTestBase;

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

    $this->installConfig(['gathercontent_upload_test_config']);

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

}
