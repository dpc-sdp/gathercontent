<?php

namespace Drupal\gathercontent_ui\Traits;

use GatherContent\DataTypes\ElementComponent;

trait MappingTrait {

  /**
   * Flattens a nested array of fields (e.g. with Components).
   *
   * @param \GatherContent\DataTypes\Element[] $elements
   *   Nested fields array.
   *
   * @return \GatherContent\DataTypes\Element[]
   *   Flat array of fields.
   */
  protected function flattenGroup($elements): array {
    $flat_elements = [];
    foreach ($elements as $element) {
      if ($element instanceof ElementComponent) {
        $children = $element->getChildrenFields();
        foreach ($children as $child_element) {
          $child_element->label = t('@component: @label', ['@component' => $element->label, '@label' => $child_element->label]);
          $child_element->id = $element->id . '/' . $child_element->id;
          $flat_elements[] = $child_element;
        }
      }
      else {
        $flat_elements[] = $element;
      }
    }
    return $flat_elements;
  }

}
