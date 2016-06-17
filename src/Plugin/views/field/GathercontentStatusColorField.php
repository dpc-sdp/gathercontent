<?php

namespace Drupal\gathercontent\Plugin\views\field;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Component\Utility\Random;
use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\views\ResultRow;

/**
 * A handler to provide a field that is completely custom by the administrator.
 *
 * @ingroup views_field_handlers
 *
 * @ViewsField("gathercontent_status_color_field")
 */
class GathercontentStatusColorField extends FieldPluginBase {

  var $field_alias = 'item_status';

  /**
   * {@inheritdoc}
   */
  public function usesGroupBy() {
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function query() {
    // Do nothing -- to override the parent query.
  }

  /**
   * {@inheritdoc}
   */
  protected function defineOptions() {
    $options = parent::defineOptions();

    $options['hide_alter_empty'] = array('default' => FALSE);
    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    parent::buildOptionsForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function render(ResultRow $values) {
    return array(
      '#type' => 'html_tag',
      '#tag' => 'div',
      '#value' => ' ',
      '#attributes' => array(
        'style' => 'width:20px; height: 20px; float: left; margin-right: 5px; background: ' . $values->_entity->getItemStatusColor(),
      ),
    );
  }

}
