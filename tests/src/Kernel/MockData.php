<?php

namespace Drupal\Tests\gathercontent\Kernel;

use Cheppers\GatherContent\DataTypes\ElementFiles;
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

  public static $drupalRoot = '';

  /**
   * Utility function.
   */
  public static function getUniqueInt() {
    static $counter = 1;
    return $counter++;
  }

  /**
   * Create the default test taxonomy terms.
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
  public static function createItem(Mapping $mapping, array $selectedCheckboxes, array $selectedRadioboxes) {
    $template = unserialize($mapping->getTemplate())->data;
    $tabs = $template->config;

    $item = new Item();
    $item->id = static::getUniqueInt();
    $item->name = 'test item name ' . $item->id;
    $item->projectId = $template->project_id;
    $item->templateId = $template->id;

    foreach ($tabs as $tab) {
      $newTab = new Tab(json_decode(json_encode($tab), TRUE));
      foreach ($newTab->elements as $element) {
        switch ($element->type) {
          case 'text':
            $element->setValue('test text value ' . static::getUniqueInt());
            break;

          case 'files':
            // Files are not stored here.
            break;

          case 'section':
            $element->subtitle = 'test section subtitle ' . static::getUniqueInt();
            break;

          case 'choice_checkbox':
            foreach ($element->options as $i => $option) {
              $element->options[$i]['selected'] = $selectedCheckboxes[$i];
            }
            break;

          case 'choice_radio':
            foreach ($element->options as $i => $option) {
              $element->options[$i]['selected'] = $selectedRadioboxes[$i];
            }
            break;
        }
      }
      $item->config[$newTab->id] = $newTab;
    }

    return $item;
  }

  /**
   * Create a file for every file element in item.
   */
  public static function createFile(Item $item) {
    $fileElements = array_filter(reset($item->config)->elements, function ($element) {
      return $element instanceof ElementFiles;
    });
    $files = [];

    foreach ($fileElements as $element) {
      $file = new File();
      $file->id = static::getUniqueInt();
      $file->userId = static::getUniqueInt();
      $file->itemId = $item->id;
      $file->field = $element->id;
      $file->url = static::$drupalRoot . '/' . drupal_get_path('module', 'test_module') . '/images/test.jpg';
      $file->fileName = 'test.jpg';
      $file->size = 60892;
      $file->type = 'field';
      $file->createdAt = '2017-08-18 15:48:10';
      $file->updatedAt = '2017-08-18 15:48:10';
      $files[$file->id] = $file;
    }

    return $files;
  }

  /**
   * After installing the test configs read the mapping.
   */
  public static function getMapping() {
    $mapping_id = \Drupal::entityQuery('gathercontent_mapping')->execute();
    $mapping_id = reset($mapping_id);
    return Mapping::load($mapping_id);
  }

}
