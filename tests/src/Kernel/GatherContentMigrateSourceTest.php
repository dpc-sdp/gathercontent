<?php

namespace Drupal\Tests\gathercontent\Kernel;

/**
 * Class GatherContentMigrateSourceTest.
 *
 * @covers \Drupal\gathercontent\Plugin\migrate\source\GatherContentMigrateSource
 * @group gathercontent
 */
class GatherContentMigrateSourceTest extends GatherContentMigrateSourceTestBase {

  /**
   * {@inheritdoc}
   */
  public function providerSource() {
    $tests = [];

    $item = $this->createObj([
      'id' => 1,
      'templateId' => 1,
      'config' => [
        $this->createObj([
          'id' => 'tab234',
          'elements' => [
            'el1234567' => 'Test title',
            'el1234568' => 'Test body',
          ],
        ]),
      ],
    ]);

    // The source data.
    $tests[0]['source_data'] = [
      'project_items' => [
        1 => [
          $item,
        ],
      ],
      'detailed_items' => [
        $item->id => $item,
      ],
    ];

    // The expected results.
    $tests[0]['expected_data'] = [
      get_object_vars($item),
    ];

    // The expected count.
    $tests[0]['expected_count'] = NULL;

    // Configuration.
    $tests[0]['configuration'] = [
      'projectId' => 1,
      'templateId' => 1,
      'tabId' => 'tab234',
      'fields' => [],
    ];

    return $tests;
  }

  /**
   * Returns new item object.
   *
   * @param array $fields
   *   Fields value array.
   *
   * @return \stdClass
   *   Pseudo item object.
   */
  public function createObj(array $fields) {
    $item = new \stdClass();

    foreach ($fields as $key => $value) {
      $item->{$key} = $value;
    }

    return $item;
  }

}
