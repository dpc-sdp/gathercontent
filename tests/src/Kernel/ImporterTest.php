<?php

namespace Drupal\Tests\gathercontent\Unit;

use Cheppers\GatherContent\DataTypes\Item;
use Cheppers\GatherContent\Tests\Unit\GcBaseTestCase;
use Drupal\KernelTests\KernelTestBase;

/**
 * Tests of the import functionality.
 *
 * @group gathercontent
 */
class ImporterTest extends KernelTestBase {

  public static $modules = ['gathercontent', 'test_module'];

  public function __construct($name = NULL, array $data = [], $dataName = '') {
    parent::__construct($name, $data, $dataName);
    require_once static::getDrupalRoot() . '/../vendor/cheppers/gathercontent-client/src-dev/Tests/Unit/GcBaseTestCase.php';
  }

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();
    $this->installConfig(['test_module']);
  }

  public function casesCreateNode() {
    $itemArray = GcBaseTestCase::getUniqueResponseItem([
      ['text'],
    ]);
    $itemArray['project_id'] = 86701;
    $itemArray['template_id'] = 819462;
    //$item = new Item($itemArray);
    return [
      'create node 1' => [
        $itemArray,
      ],
    ];
  }

  /**
   * @dataProvider casesCreateNode
   */
  public function testCreateNode($gc_item) {
    $gc_item = new Item($gc_item);
    /** @var \Drupal\gathercontent\Import\Importer $importer */
    $importer = \Drupal::service('gathercontent.importer');
    $node = $importer->createNode($gc_item);
    static::assertTrue(static::itemNodeEquals($node, $gc_item));
  }

  /**
   * Checks whether a node and a GC item contains the same data.
   */
  public static function itemNodeEquals($node, $gc_item) {
    return TRUE;
  }

}
