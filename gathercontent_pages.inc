<?php
/**
 * @file
 * Class to fetch pages from GatherContent and turn them into Drupal nodes.
 */

class GatherContentPages {

  /**
   * Fetch the selected pages from GatherContent, and create nodes.
   *
   * @param array $page_ids
   *   GatherContent page identifiers.
   *
   * @param string $content_type
   *   Which type of content to create.
   */
  public static function createNodes($page_ids, $content_type) {
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
   * Get pages from GatherContent for a given project, and order them by parent.
   *
   * @param string $project_id
   *   The project to fetch pages for. 
   * 
   * 
   * @return array $pages
   *   The project's pages, ordered by their parent page.
   */
  public static function getPages($project_id) {
    $gathercontent_pages = gathercontent_get_command('get_pages_by_project', array('id' => $project_id));
    $parents = array();
    $pages = array();

    // Populate $parents with parent pages ...
    foreach ($gathercontent_pages->pages as $page) {
      // Exclude 'meta' pages.
      if ($page->state != 'meta') {
        $parent_id = $page->parent_id;
        $parents[$parent_id][$page->id] = $page;
      }
    }
    // ... then loop through $parents[0] (since this contains all
    // the parent ids) and add child pages.
    if (isset($parents[0])) {
      foreach ($parents[0] as $id => $page) {
        $pages[$id] = $page;
        $pages[$id]->children = GatherContentPages::sortRecursive($parents, $id);
      }
    }
    return $pages;
  }

  /**
   * Sort page children recursively.
   */
  protected static function sortRecursive($pages, $current = 0) {
    $children = array();
    if (isset($pages[$current])) {
      $children = $pages[$current];
      foreach ($children as $id => $page) {
        $children[$id]->children = GatherContentPages::sortRecursive($pages, $id);
      }
    }
    return $children;
  }

  /**
   * Generate tableselect rows.
   */
  public static function pageImportForm($pages, $options, $index = -1) {
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
        $options = GatherContentPages::pageImportForm($page->children, $options, $index);
      }
    }
    return $options;
  }
}
