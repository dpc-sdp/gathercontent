<?php
/**
 * @file
 * Class to create Drupal nodes from GatherContent pages. Very simple for now.
 */

class GatherContentPages {

  /**
   * Fetch pages from GatherContent, and create nodes. 
   */
  public function createNodes($page_ids, $content_type) {
    foreach ($page_ids as $id) {
      $gc_page_id = substr($id, 11);
      // Get the page from GatherContent.
      $gc_page = gathercontent_get_command('get_page', array('id' => $gc_page_id));

      // Get the actual page.
      // @GC: Why is this an array inside an object, and not just an array?
      $gc_page = $gc_page->page[0];
      $body_content = '';
      // Get all the content fields.
      foreach ($gc_page->custom_field_values as $field_name => $field_value) {
        $body_content .= $field_value;
      }

      // Prepare creation date.
      $created = strtotime($gc_page->created_at);

      $node = new stdClass();

      // Create the node.
      // @TODO: Move this to a batch operation?
      $node->title = $gc_page->name;
      $node->type = $content_type;
      $node->language = LANGUAGE_NONE;
      $node->body[$node->language][] = array('value' => $body_content, 'format' => 'full_html');
      node_object_prepare($node);
      if ($node = node_submit($node)) {
        $node->created = $created;
        node_save($node);
        drupal_set_message(t('%node_title created successfully.', array('%node_title' => $node->title)));
      }
      else {
        drupal_set_message(t('Something went wrong while creating %node_title:', array('%node_title' => $node->title . ' ')), 'error');
      }
    }
  }

  /**
   * Get pages from GatherContent.
   */
  public function getPages($page_type) {
    $pages = gathercontent_get_command('get_pages_by_project', array('id' => variable_get('gathercontent_project')));
    $original = array();
    $new_pages = array();
    $parent_array = array();

    foreach ($pages->pages as $page) {
      if ($page->state != 'meta' && !($page_type == 'approved' && $page->state != 'signedoff')) {
        $original[$page->id] = $page;
        $parent_id = $page->parent_id;
        if ($page->repeatable_page_id > 0) {
          $parent_id = $page->repeatable_page_id;
          if (isset($original[$parent_id])) {
            $page->custom_field_config = $original[$parent_id]->custom_field_config;
          }
        }
        if (!isset($parent_array[$parent_id])) {
          $parent_array[$parent_id] = array();
        }
        $parent_array[$parent_id][$page->id] = $page;
      }
    }
    foreach ($parent_array as $parent_id => $page_array) {
      $array = $page_array;
      uasort($array, array(&$this, 'sortPages'));
      $parent_array[$parent_id] = $array;
    }
    if (isset($parent_array[0])) {
      foreach ($parent_array[0] as $id => $page) {
        $new_pages[$id] = $page;
        $new_pages[$id]->children = $this->sortRecursive($parent_array, $id);
      }
    }
    $this->pages = $new_pages;
    $this->original_array = $original;
    return $this->pages;
  }

  /**
   * Sort page children recursively.
   */
  protected function sortRecursive($pages, $current = 0) {
    $children = array();
    if (isset($pages[$current])) {
      $children = $pages[$current];
      foreach ($children as $id => $page) {
        $children[$id]->children = $this->sortRecursive($pages, $id);
      }
    }
    return $children;
  }

  /**
   * Sort pages.
   */
  protected function sortPages($a, $b) {
    if ($a->position == $b->position) {
      if ($a->id == $b->id) {
        return 0;
      }
      else {
        return ($a->id < $b->id) ? -1 : 1;
      }
    }
    return ($a->position < $b->position) ? -1 : 1;
  }

  /**
   * Generate tableselect rows.
   */
  public function pageImportForm($pages, $options, $index = -1) {
    $index++;
    foreach ($pages as $id => $page) {
      $options['gc_page_id_' . $id] = array(
        'title' => array(
          'data' => array(
            '#markup' => theme('indentation', array('size' => $index)) . $page->name,
            '#title' => $page->name,
          ),
        ),
      );

      // If the page has children, call this function recursively, so the
      // children are added to the form as well.
      if (isset($page->children) && count($page->children) > 0) {
        $options = $this->pageImportForm($page->children, $options, $index);
      }
    }
    return $options;
  }
}
