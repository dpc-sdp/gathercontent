<?php

namespace Drupal\gathercontent\Form;

use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\gathercontent\DAO\Project;
use Drupal\gathercontent\DAO\Template;
use Drupal\gathercontent\Entity\Mapping;

/**
 * Class MappingImportForm.
 *
 * @package Drupal\gathercontent\Form
 */
class MappingImportForm extends EntityForm {

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $form = parent::form($form, $form_state);

    $pr_obj = new Project();
    $projects = $pr_obj->getProjects();

    $template_obj = new Template();

    $form['description'] = array(
      '#type' => 'html_tag',
      '#tag' => 'div',
      '#value' => t("Please select the GatherContent Templates you'd like to map. Only Templates you've not selected will be listed."),
      '#attributes' => array(
        'class' => array('description'),
      ),
    );

    $form['projects'] = array(
      '#type' => 'vertical_tabs',
    );

    $form['template_counter'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => [
          'gather-content-counter-message',
        ],
      ],
      '#attached' => [
        'library' => [
          'gathercontent/template_counter',
        ],
      ],
    ];

    $created_mapping_ids = Mapping::loadMultiple();
    $local_templates = array();

    foreach ($created_mapping_ids as $mapping) {
      /** @var Mapping $mapping */
      $local_templates[$mapping->getGathercontentTemplateId()] = $mapping->getGathercontentTemplate();
    }

    foreach ($projects as $project_id => $project) {
      $remote_templates = $template_obj->getTemplates($project_id);
      $templates = array_diff_assoc($remote_templates, $local_templates);

      if (empty($templates)) {
        continue;
      }

      $form['p' . $project_id] = array(
        '#type' => 'details',
        '#title' => $project,
        '#group' => 'projects',
        '#tree' => TRUE,
      );
      $form['p' . $project_id]['templates'] = array(
        '#type' => 'checkboxes',
        '#title' => $project,
        '#options' => $templates,
        '#attributes' => [
          'class' => [
            'gather-content-counted',
          ],
        ],
      );
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    if ($form_state->getTriggeringElement()['#id'] == 'edit-submit') {
      // Load all projects.
      $pr_obj = new Project();
      $projects = $pr_obj->getProjects();
      $values = $form_state->getValues();
      foreach ($values as $k => $tree) {
        if (!is_array($tree)) {
          continue;
        }
        $templates = array_filter($values[$k]['templates']);
        foreach ($templates as $template_id => $selected) {
          $tmp_obj = new Template();
          $template = $tmp_obj->getTemplate($template_id);
          $mapping_values = array(
            'id' => $template_id,
            'gathercontent_project_id' => $template->project_id,
            'gathercontent_project' => $projects[$template->project_id],
            'gathercontent_template_id' => $template_id,
            'gathercontent_template' => $template->name,
            'template' => serialize($template),
          );
          $mapping = \Drupal::entityManager()
            ->getStorage('gathercontent_mapping')
            ->create($mapping_values);
          if (is_object($mapping)) {
            $mapping->save();
          }
        }
      }
    }

    $form_state->setRedirect('entity.gathercontent_mapping.collection');
  }

  /**
   * @inheritdoc
   */
  protected function actions(array $form, FormStateInterface $form_state) {
    $actions = parent::actions($form, $form_state);
    $actions['submit']['#value'] = $this->t('Select');
    $actions['close'] = array(
      '#type' => 'submit',
      '#value' => t('Close'),
    );
    return $actions;
  }

}
