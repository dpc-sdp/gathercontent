<?php

namespace Drupal\gathercontent\Entity;

use Drupal\views\EntityViewsData;
use Drupal\views\EntityViewsDataInterface;

/**
 * Provides Views data for Gathercontent operation item entities.
 */
class GathercontentOperationItemViewsData extends EntityViewsData implements EntityViewsDataInterface {

  /**
   * {@inheritdoc}
   */
  public function getViewsData() {
    $data = parent::getViewsData();

    $data['gathercontent_operation_item']['table']['base'] = array(
      'field' => 'id',
      'title' => $this->t('Gathercontent operation item'),
      'help' => $this->t('The Gathercontent operation item ID.'),
    );

    return $data;
  }

}
