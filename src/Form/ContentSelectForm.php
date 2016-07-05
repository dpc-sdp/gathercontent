<?php

namespace Drupal\gathercontent\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\gathercontent\DAO\Content;
use Drupal\gathercontent\Entity\Mapping;
use Drupal\node\Entity\Node;

/**
 * Class ContentUpdateSelectForm.
 *
 * @package Drupal\gathercontent\Form
 */
class ContentSelectForm extends MultistepFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'content_select_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildForm($form, $form_state);

    $created_mapping_ids = Mapping::loadMultiple();
    $projects = $contents = array();
    $mapping_array = array();
    foreach ($created_mapping_ids as $mapping) {
      /** @var \Drupal\gathercontent\Entity\Mapping $mapping */
      if ($mapping->hasMapping()) {
        $projects[$mapping->getGathercontentProjectId()] = $mapping->getGathercontentProject();
        $mapping_array[$mapping->id()] = array(
          'gc_template' => $mapping->getGathercontentTemplate(),
          'ct' => $mapping->getContentTypeName(),
        );
      }
    }

    $node_ids = $this->entityQuery->get('node')
      ->condition('gc_id', NULL, 'IS NOT')
      ->condition('gc_mapping_id', NULL, 'IS NOT')
      ->execute();
    $nodes = Node::loadMultiple($node_ids);
    $selected_projects = array();
    $content_obj = new Content();

    foreach ($created_mapping_ids as $mapping) {
      if (!in_array($mapping->getGathercontentProjectId(), $selected_projects)) {
        $selected_projects[] = $mapping->getGathercontentProjectId();
        $content = $content_obj->getContents($mapping->getGathercontentProjectId());
        foreach ($content as $c) {
          $single_content = array();
          $single_content['gc_updated'] = $c->updated_at;
          $single_content['status'] = $c->status;
          $single_content['name'] = $c->name;
          $single_content['project_id'] = $c->project_id;
          $contents[$c->id] = $single_content;
        }
      }
    }

    $form['filter'] = [
      '#type' => 'markup',
      '#markup' => '<div class="gc-table--filter-wrapper clearfix"></div>',
      '#weight' => 0,
    ];

    $form['counter'] = [
      '#type' => 'markup',
      '#markup' => '<div class="gc-table--counter"></div>',
      '#weight' => 1,
    ];

    $base_url = 'http://' . \Drupal::config('gathercontent.settings')
        ->get('gathercontent_urlkey') . '.gathercontent.com/item/';

    $content_table = array();
    foreach ($nodes as $item) {
      if (!isset($contents[$item->gc_id->value]['status']->data->name)) {
        // Don't show deleted items or items which belong to another account.
        continue;
      }

      /** @var Node $item */
      $content_table[$item->id()] = array(
        'status' => array(
          'data' => array(
            'color' => array(
              '#type' => 'html_tag',
              '#tag' => 'div',
              '#value' => ' ',
              '#attributes' => array(
                'style' => 'width:20px; height: 20px; float: left; margin-right: 5px; background: ' . $contents[$item->gc_id->value]['status']->data->color,
              ),
            ),
            'label' => array(
              '#plain_text' => $contents[$item->gc_id->value]['status']->data->name,
            ),
          ),
          'class' => array('gc-item', 'status-item'),
        ),
        'gathercontent_project' => array(
          'data' => $projects[$contents[$item->gc_id->value]['project_id']],
        ),
        'title' => array(
          'data' => $item->getTitle(),
          'class' => array('gc-item', 'gc-item--name'),
        ),
        'gathercontent_title' => array(
          'data' => $contents[$item->gc_id->value]['name'],
        ),
        'gathercontent_updated' => array(
          'data' => date('F d, Y - H:i', strtotime($contents[$item->gc_id->value]['gc_updated']->date)),
          'class' => array('gc-item', 'gc-item-date'),
          'data-date' => date('Y-m-d.H:i:s', strtotime($contents[$item->gc_id->value]['gc_updated']->date)),
        ),
        'drupal_updated' => array(
          'data' => date('F d, Y - H:i', $item->getChangedTime()),
          'class' => array('gc-item', 'gc-item-date'),
          'data-date' => date('Y-m-d.H:i:s', $item->getChangedTime()),
        ),
        'content_type' => array(
          'data' => $mapping_array[$item->gc_mapping_id->value]['ct'],
        ),
        'gathercontent_template' => array(
          'data' => $mapping_array[$item->gc_mapping_id->value]['gc_template'],
          'class' => array('template-name-item'),
        ),
        'drupal_open' => array(
          'data' => Link::fromTextAndUrl($this->t('Open'), Url::fromUri('entity:node/' . $item->id()))
            ->toRenderable(),
        ),
        'gathercontent_open' => array(
          'data' => Link::fromTextAndUrl($this->t('Open'), Url::fromUri($base_url . $item->gc_id->value))
            ->toRenderable(),
        ),
      );
    }

    $header = array(
      'status' => $this->t('Status'),
      'gathercontent_project' => $this->t('GatherContent project'),
      'title' => $this->t('Item Name'),
      'gathercontent_title' => $this->t('GatherContent item name'),
      'drupal_updated' => $this->t('Last updated in Drupal'),
      'gathercontent_updated' => $this->t('Last updated in GatherContent'),
      'content_type' => $this->t('Content type name'),
      'gathercontent_template' => $this->t('GatherContent template'),
      'drupal_open' => $this->t('Open in Drupal'),
      'gathercontent_open' => $this->t('Open in GatherContent'),
    );

    $form['nodes'] = array(
      '#weight' => 2,
      '#type' => 'tableselect',
      '#header' => $header,
      '#options' => $content_table,
      '#empty' => t('No content available.'),
      '#default_value' => $this->store->get('nodes') ? $this->store->get('nodes') : NULL,
      '#attributes' => [
        'class' => [
          'tablesorter-enabled',
        ],
      ],
      '#attached' => [
        'library' => [
          'gathercontent/tablesorter-mottie',
          'gathercontent/filter',
        ],
        'drupalSettings' => [
          'gatherContent' => [
            'tableSorterOptionOverrides' => [
              'headers' => [
                '0' => [
                  'sorter' => FALSE,
                ],
                '9' => [
                  'sorter' => FALSE,
                ],
                '10' => [
                  'sorter' => FALSE,
                ],
              ],
            ],
          ],
        ],
      ],
    );

    $form['actions']['submit']['#value'] = $this->t('Next');

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    dsm($form_state->getValues());
  }
}
