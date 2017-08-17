<?php

namespace Drupal\Tests\gathercontent\Kernel;

use Cheppers\GatherContent\DataTypes\File;
use Cheppers\GatherContent\DataTypes\Item;
use Cheppers\GatherContent\DataTypes\Tab;
use Drupal\gathercontent\Entity\Mapping;
use Drupal\taxonomy\Entity\Term;

/**
 * A class for getting static test data.
 */
class MockData {

  const CHECKBOX_TAXONOMY_NAME = 'checkbox_test_taxonomy';
  const RADIO_TAXONOMY_NAME = 'radio_test_taxonomy';

  const TEST_VALUES = [
    'choice_radio' => [],
    'choice_checkbox' => [],
    'files' => '',
    'text' => 'test text',
    'section' => 'test section',
  ];

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
   * Creates a GC Item corresponding to a mapping.
   */
  public static function createItem(Mapping $mapping) {
    $template = unserialize($mapping->getTemplate())->data;
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
            $element->options[2]['selected'] = TRUE;
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
  public static function createFile($drupalRoot) {
    $file = new File();
    $file->id = 1;
    $file->userId = 1;
    $file->itemId = 1;
    $file->field = 'el1502871120855';
    $file->url = $drupalRoot . '/' . drupal_get_path('module', 'test_module') . '/images/test.jpg';
    $file->fileName = 'test.jpg';
    $file->size = 0;
    $file->createdAt = NULL;
    $file->updatedAt = NULL;
    return $file;
  }

}
