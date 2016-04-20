<?php
/**
 * @file
 * Form elements and theme functions for GatherContent module.
 */

/**
 * Implements hook_element_info().
 */
function gc_element_info() {
  return array(
    'gc_tableselect' => array(
      '#input' => TRUE,
      '#js_select' => TRUE,
      '#tablesorter' => TRUE,
      '#filterwrapper' => array(),
      '#multiple' => TRUE,
      '#process' => array('form_process_tableselect'),
      '#options' => array(),
      '#empty' => '',
      '#theme' => 'gc_tableselect',
      '#type' => 'tableselect',
    ),
  );
}

/**
 * Implements hook_theme().
 */
function gc_theme() {
  return array(
    'gc_tableselect' => array(
      'render element' => 'element',
    ),
  );
}

/**
 * Returns HTML for a table with radio buttons or checkboxes.
 *
 * See Drupal core's theme_tableselect() as reference.
 *
 * @param array $variables
 *   An associative array containing:
 *   - element: An associative array containing the properties and children of
 *     the tableselect element. Properties used: #header, #options, #empty,
 *     and #js_select. The #options property is an array of selection options;
 *     each array element of #options is an array of properties. These
 *     properties can include #attributes, which is added to the
 *     table row's HTML attributes; see theme_table().
 *
 * @return string
 *   The rendered markup.
 *
 * @ingroup themeable
 */
function theme_gc_tableselect(array $variables) {
  $output = '';
  $element = $variables['element'];
  $gc_module_path = drupal_get_path('module', 'gc');

  // Libraries module and tablesorter plugin are optional.
  // If tablesorter available, add it to the scope.
  if (module_exists('libraries')) {
    $library = libraries_detect('tablesorter-mottie');
    if ($library['installed'] && $element['#tablesorter']) {
      $element['#attributes']['class'][] = 'tablesorter-enabled';
      drupal_add_js($gc_module_path . '/js/gc-tablesorter.js');
      libraries_load('tablesorter-mottie');
    }
  }

  $rows = array();
  $header = $element['#header'];
  if (!empty($element['#options'])) {
    // Generate a table row for each selectable item in #options.
    foreach (element_children($element) as $key) {
      $row = array();

      $row['data'] = array();
      if (isset($element['#options'][$key]['#attributes'])) {
        $row += $element['#options'][$key]['#attributes'];
      }
      // Render the checkbox / radio element.
      $row['data'][] = drupal_render($element[$key]);

      // As theme_table only maps header and row columns by order, create the
      // correct order by iterating over the header fields.
      foreach ($element['#header'] as $fieldname => $title) {
        $row['data'][] = $element['#options'][$key][$fieldname];
      }
      $rows[] = $row;
    }
    // Add an empty header or a "Select all" checkbox to provide room for the
    // checkboxes/radios in the first table column.
    if ($element['#js_select']) {
      // Add a "Select all" checkbox.
      drupal_add_js($gc_module_path . '/js/gc-tableselect.js');
      array_unshift($header, array(
        'class' => array('select-all'),
        'data-sorter' => 'false',
      ));
    }
    else {
      // Add an empty header when radio buttons are displayed or a "Select all"
      // checkbox is not desired.
      array_unshift($header, '');
    }
  }

  if (!empty($element['#filterwrapper']) && is_array($element['#filterwrapper'])) {
    $filterwrapper = array(
      '#type' => 'container',
      '#attributes' => array(
        'class' => array('gc-table--header'),
      ),
    );

    foreach ($element['#filterwrapper'] as $key => $classes) {
      $filterwrapper[$key] = array(
        '#type' => 'container',
        '#attributes' => array(
          'class' => is_array($classes) ? $classes : array($classes),
        ),
      );
    }
    $output .= drupal_render($filterwrapper);
  }

  $output .= theme('table', array(
    'header' => $header,
    'rows' => $rows,
    'empty' => $element['#empty'],
    'attributes' => $element['#attributes'],
  ));

  return $output;
}

/**
 * Creates checkbox or radio elements to populate a tableselect table.
 *
 * See Drupal's form_process_tableselect() as reference.
 *
 * @param array $element
 *   An associative array containing the properties and children of the
 *   tableselect element.
 *
 * @return array
 *   The processed element.
 */
function gc_process_gc_tableselect(array $element) {
  if ($element['#multiple']) {
    $value = is_array($element['#value']) ? $element['#value'] : array();
  }
  else {
    // Advanced selection behavior makes no sense for radios.
    $element['#js_select'] = FALSE;
  }

  $element['#tree'] = TRUE;

  if (count($element['#options']) > 0) {
    if (!isset($element['#default_value']) || $element['#default_value'] === 0) {
      $element['#default_value'] = array();
    }

    // Create a checkbox or radio for each item in #options in such a way that
    // the value of the tableselect element behaves as if it had been of type
    // checkboxes or radios.
    foreach ($element['#options'] as $key => $choice) {
      // Do not overwrite manually created children.
      if (!isset($element[$key])) {
        if ($element['#multiple']) {
          $title = '';
          if (!empty($element['#options'][$key]['title']['data']['#title'])) {
            $title = t('Update @title', array(
              '@title' => $element['#options'][$key]['title']['data']['#title'],
            ));
          }
          $element[$key] = array(
            '#type' => 'checkbox',
            '#title' => $title,
            '#title_display' => 'invisible',
            '#return_value' => $key,
            '#default_value' => isset($value[$key]) ? $key : NULL,
            '#attributes' => $element['#attributes'],
          );
        }
        else {
          // Generate the parents as the autogenerator does, so we will have a
          // unique id for each radio button.
          $parents_for_id = array_merge($element['#parents'], array($key));
          $element[$key] = array(
            '#type' => 'radio',
            '#title' => '',
            '#return_value' => $key,
            '#default_value' => ($element['#default_value'] == $key) ? $key : NULL,
            '#attributes' => $element['#attributes'],
            '#parents' => $element['#parents'],
            '#id' => drupal_html_id('edit-' . implode('-', $parents_for_id)),
            '#ajax' => isset($element['#ajax']) ? $element['#ajax'] : NULL,
          );
        }
        if (isset($element['#options'][$key]['#weight'])) {
          $element[$key]['#weight'] = $element['#options'][$key]['#weight'];
        }
      }
    }
  }
  else {
    $element['#value'] = array();
  }
  return $element;
}
