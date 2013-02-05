<?php
/**
 * @file
 * Class to create Drupal nodes from GatherContent pages. Very simple for now.
 */

class GatherContentPages {

  /**
   * Fetch pages from GatherContent, and create nodes.
   *
   * @param array $page_ids
   *   GatherContent page identifiers.
   *
   * @param string $content_type
   *   Which type of content to create.
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

      // Create the node(s).
      // @TODO: Move this to a batch operation?
      $node->title = $gc_page->name;
      $node->type = $content_type;
      $node->language = LANGUAGE_NONE;
      $node->body[$node->language][] = array('value' => $body_content, 'format' => 'full_html');
      node_object_prepare($node);
      $node->created = $created;
      node_save($node);
      if ($node->nid) {
        drupal_set_message(t('%node_title created successfully.', array('%node_title' => $node->title)));
      }
      else {
        drupal_set_message(t('Something went wrong while creating %node_title:', array('%node_title' => $node->title . ' ')), 'error');
      }
    }
  }

  /**
   * Get pages from GatherContent for a given project.
   *
   * @param string $project_id
   *   The project to fetch pages for. 
   */
  public function getPages($project_id) {
    $pages = gathercontent_get_command('get_pages_by_project', array('id' => $project_id));
    $new_pages = array();
    $parents = array();
    
    foreach ($pages->pages as $page) {
      // Exclude 'meta' pages from pages list.
      if ($page->state != 'meta') {
        $parent_id = $page->parent_id;
        // @TODO: Not sure if this is still relevant in the GC API, checking
        // with them.
        // if ($page->repeatable_page_id > 0) {
        //   $parent_id = $page->repeatable_page_id;
        // }
        $parents[$parent_id][$page->id] = $page;
      }
    }
    // foreach ($page_children as $child_id => $child) {
    //   $page_children[$child_id] = $child;
    // }
    // If there are children, add them to the parent page's array.
    if (isset($parents[0])) {
      foreach ($parents[0] as $id => $page) {
        $new_pages[$id] = $page;
        $new_pages[$id]->children = $this->sortRecursive($parents, $id);
      }
    }
    $this->pages = $new_pages;
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
   * Generate tableselect rows.
   */
  public function pageImportForm($pages, $options, $index = -1) {
    $index++;
    foreach ($pages as $id => $page) {
      $options['gc_page_id_' . $id] = array(
        'title' => array(
          'data' => array(
            '#markup' => theme('indentation', array('size' => $index)) . $page->name,
            '#title' => check_plain($page->name),
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
