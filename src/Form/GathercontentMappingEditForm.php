<?php

namespace Drupal\gathercontent\Form;

use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\gathercontent\DAO\Template;
use Drupal\gathercontent\Entity\GathercontentMapping;

/**
 * Class GathercontentMappingEditForm.
 *
 * @package Drupal\gathercontent\Form
 */
class GathercontentMappingEditForm extends EntityForm {

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $form = parent::form($form, $form_state);
    /** @var $mapping GathercontentMapping */
    $mapping = $this->entity;

    $content_types = node_type_get_names();
    $tmp = new Template();
    $template = $tmp->getTemplate($mapping->getGathercontentTemplateId());
    $new = !$mapping->hasMapping();
    $form = array();
    $form['#attached']['library'][] = 'gathercontent/theme';
    $form['form_description'] = array(
      '#type' => 'html_tag',
      '#tag' => 'i',
      '#value' => t('Please map your GatherContent Template fields to your Drupal 
    Content Type Fields. Please note that a GatherContent field can only be 
    mapped to a single Drupal field. So each field can only be mapped to once.'),
    );

    if (!$new) {
      $mapping_data = unserialize($mapping->getData());
      $content_type = $mapping->getContentTypeName();

      $form['info'] = array(
        '#markup' =>
          '<div class="project-name">' .
          t('Project name: @name', array('@name' => $mapping->getGathercontentProject()))
          . '</div>'
          . '<div class="gather-content">'
          . t('GatherContent template: @gc_template', array('@gc_template' => $mapping->getGathercontentTemplate()))
          . '</div>'
          . '<div class="drupal-content-type">'
          . t('Drupal content type: @content_type', array('@content_type' => $content_type))
          . '</div>',

      );

      $form['mapping'] = array(
        '#prefix' => '<div id="edit-mapping">',
        '#suffix' => '</div>',
      );

      foreach ($template->config as $i => $fieldset) {
        if ($fieldset->hidden === FALSE) {
          $form['mapping'][$fieldset->name] = array(
            '#type' => 'fieldset',
            '#title' => $fieldset->label,
            '#collapsible' => TRUE,
            '#collapsed' => ($i === 0 ? FALSE : TRUE),
            '#tree' => TRUE,
          );

          foreach ($fieldset->elements as $gc_field) {
            $d_fields = array();
            if (isset($form_state->getTriggeringElement()['#name'])) {
              // We need different handling for changed fieldset.
              if ($form_state->getTriggeringElement()['#array_parents'][1] === $fieldset->name) {
                if ($form_state->getTriggeringElement()['#value'] === 'content') {
                  $d_fields = self::filter_fields($gc_field, $mapping->getContentType());
                }
              }
              else {
                if ($form_state->getValue($fieldset->name)['type'] === 'content') {
                  $d_fields = self::filter_fields($gc_field, $mapping->getContentType());
                }
              }
            }
            else {
              if ((isset($mapping_data[$fieldset->name]['type']) && $mapping_data[$fieldset->name]['type'] === 'content') || !isset($mapping_data[$fieldset->name]['type'])) {
                $d_fields = self::filter_fields($gc_field, $mapping->getContentType());
              }
            }
            $form['mapping'][$fieldset->name]['elements'][$gc_field->name] = array(
              '#type' => 'select',
              '#options' => $d_fields,
              '#title' => (isset($gc_field->label) ? $gc_field->label : $gc_field->title),
              '#default_value' => isset($mapping_data[$fieldset->name]['elements'][$gc_field->name]) ? $mapping_data[$fieldset->name]['elements'][$gc_field->name] : NULL,
              '#empty_option' => t("Don't map"),
            );
          }
        }
      }
    }
    else {
      $form['info'] = array(
        '#markup' => t('Project name: @name', array('@name' => $mapping->getGathercontentProject()))
          . '<br>'
          . t('GatherContent template: @gc_template', array('@gc_template' => $mapping->getGathercontentTemplate())),
      );

      $form['updated'] = array(
        '#type' => 'value',
        '#value' => $template->updated_at,
      );

      $form['content_type'] = array(
        '#type' => 'select',
        '#title' => t('Drupal Content Types'),
        '#options' => $content_types,
        '#required' => TRUE,
        '#ajax' => array(
          'callback' => 'Drupal\gathercontent\Form\GathercontentMappingEditForm::getMappingTable',
          'wrapper' => 'edit-mapping',
          'method' => 'replace',
          'effect' => 'fade',
        ),
        '#default_value' => $form_state->getValue('content_type'),
      );

      $form['mapping'] = array(
        '#prefix' => '<div id="edit-mapping">',
        '#suffix' => '</div>',
      );

      if ($form_state->hasValue('content_type')) {
        foreach ($template->config as $i => $fieldset) {
          if ($fieldset->hidden === FALSE) {
            $form['mapping'][$fieldset->name] = array(
              '#type' => 'details',
              '#title' => $fieldset->label,
              '#open' => ($i === 0 ? TRUE : FALSE),
              '#tree' => TRUE,
            );

            if ($i === 0) {
              $form['mapping'][$fieldset->name]['#prefix'] = '<div id="edit-mapping">';
            }
            if (end($template->config) === $fieldset) {
              $form['mapping'][$fieldset->name]['#suffix'] = '</div>';
            }

            foreach ($fieldset->elements as $gc_field) {
              $d_fields = array();
              $content_type = $form_state->getValue('content_type');
              if ($form_state->getTriggeringElement()['#name'] !== 'content_type') {
                // We need different handling for changed fieldset.
                if ($form_state->getTriggeringElement()['#array_parents'][1] === $fieldset->name) {
                  if ($form_state->getTriggeringElement()['#value'] === 'content') {
                    $d_fields = self::filter_fields($gc_field, $content_type);
                  }
                }
                else {
                  if ($form_state['values'][$fieldset->name]['type'] === 'content') {
                    $d_fields = self::filter_fields($gc_field, $content_type);
                  }
                }
              }
              else {
                $d_fields = self::filter_fields($gc_field, $content_type);
              }
              $form['mapping'][$fieldset->name]['elements'][$gc_field->name] = array(
                '#type' => 'select',
                '#options' => $d_fields,
                '#title' => (isset($gc_field->label) ? $gc_field->label : $gc_field->title),
                '#empty_option' => t("Don't map"),
              );
            }
          }
        }
      }
    }
    return $form;
  }

  /**
   * Helper function.
   *
   * Use for filtering only equivalent fields.
   *
   * @param object $gc_field
   *   Type of field in GatherContent.
   * @param string $content_type
   *   Name of Drupal content type.
   *
   * @return array
   *   Associative array with equivalent fields.
   */
  public function filter_fields($gc_field, $content_type) {
    $mapping_array = array(
      'files' => array(
        'file',
        'image',
      ),
      'section' => array(
        'text_long',
      ),
      'text' => array(
        'text',
        'text_long',
        'text_with_summary',
        'string_long',
        'string',
      ),
      'choice_radio' => array(
        'text',
      ),
      'choice_checkbox' => array(
        'list_text',
      ),
    );
    $instances = $this->entityManager->getFieldDefinitions('node', $content_type);
    $fields = array();
    // Fields.
    foreach ($instances as $name => $instance) {
      if (substr_compare($name, 'field', 0, 5) <> 0 && !in_array($name, array('body'))) {
        continue;
      }
      if (in_array($instance->getType(), $mapping_array[$gc_field->type])) {
        // Constrains:
        // - do not map plain text (Drupal) to rich text (GC).
        // - do not map radios (GC) to text (Drupal),
        // if widget isn't provided by select_or_other module.
        // - do not map section (GC) to plain text (Drupal).
        switch ($gc_field->type) {
          case 'text':
            if ($gc_field->plain_text && !in_array($instance->getType(), array(
                'string',
                'string_long'
              ))
            ) {
              continue 2;
            }
            break;

          case 'choise_radio':
            if ($instance['widget']['module'] !== 'select_or_other') {
              continue 2;
            }
            break;

          case 'section':
            if (in_array($instance->getType(), array(
              'string',
              'string_long'
            ))) {
              continue 2;
            }
            break;
        }
        $fields[$instance->getName()] = $instance->getLabel();
      }
    }

    if ($gc_field->type === 'text'
      && $gc_field->plain_text
    ) {
      $fields['title'] = 'Title';
    }

    return $fields;
  }

  /**
   * Ajax callback for mapping multistep form.
   *
   * @return array
   *   Array of form elements.
   *
   * @inheritdoc
   */
  public function getMappingTable(array &$form, FormStateInterface $form_state) {
    $form_state->setRebuild(TRUE);
    return $form['mapping'];
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    if ($form_state->getTriggeringElement()['#id'] == 'edit-submit') {
      $form_definition_elements = array(
        'return',
        'form_build_id',
        'form_token',
        'form_id',
        'op',
      );
      $non_data_elements = array_merge($form_definition_elements, array(
        'gc_template',
        'content_type',
        'id',
        'updated',
      ));

      $mapping_data = array();
      foreach ($form_state->getValues() as $key => $value) {
        if (!in_array($key, $non_data_elements) && substr_compare($key, 'tab', 0, 3) === 0) {
          $mapping_data[$key] = $value;
        }
      }
      /** @var $mapping GathercontentMapping */
      $mapping = $this->entity;

      $new = !$mapping->hasMapping();
      if ($new) {
        $mapping->setContentType($form_state->getValue('content_type'));
        $content_types = node_type_get_names();
        $mapping->setContentTypeName($content_types[$form_state->getValue('content_type')]);
      }
      $mapping->setData(serialize($mapping_data));
      $mapping->setUpdatedDrupal(time());

      $tmp = new Template();
      $template = $tmp->getTemplate($mapping->getGathercontentTemplateId());

      $mapping->setTemplate(serialize($template));
      $mapping->save();

      // We need to modify field for checkboxes and field instance for radios.
//      foreach ($template->config as $i => $fieldset) {
//        if ($fieldset->hidden === FALSE) {
//          foreach ($fieldset->elements as $gc_field) {
//            if ($gc_field->type === 'choice_checkbox') {
//              if (!empty($mapping_data[$gc_field->name])) {
//                $local_options = array();
//                foreach ($gc_field->options as $option) {
//                  $local_options[$option->name] = $option->label;
//                }
//
//                $field_data = array(
//                  'field_name' => $mapping_data[$gc_field->name],
//                  'settings' => array(
//                    'allowed_values' => $local_options,
//                  ),
//                );
//                try {
//                  $field_data->save();
//                }
//                catch (Exception $e) {
//                  // Log something.
//                }
//              }
//            }
//            elseif ($gc_field->type === 'choice_radio') {
//              if (!empty($mapping_data[$gc_field->name])) {
//                $local_options = array();
//                foreach ($gc_field->options as $option) {
//                  if ($option != end($gc_field->options)) {
//                    $local_options[] = $option->name . "|" . $option->label;
//                  }
//                }
//                $instance = field_read_instance('node', $mapping_data[$gc_field->name], $mapping->content_type);
//                // Make the change.
//                $instance['widget']['settings']['available_options'] = implode("\n", $local_options);
//                // Save the instance.
//                $instance->save();
//              }
//            }
//          }
//        }
//      }
      if ($new) {
        drupal_set_message(t('Mapping has been created.'));
      }
      else {
        drupal_set_message(t('Mapping has been updated.'));
      }
    }

    $form_state->setRedirect('entity.gathercontent_mapping.collection');
  }

  /**
   * Check if content type has any metatag fields.
   *
   * @param $content_type
   *   Machine name of content type.
   *
   * @return bool
   *   TRUE if metatag field exists.
   */
  public function checkMetatag($content_type) {
    $instances = $this->entityManager->getFieldDefinitions('node', $content_type);
    foreach ($instances as $name => $instance) {
      if ($instance->getType() === 'metatag') {
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * @inheritdoc
   */
  protected function actions(array $form, FormStateInterface $form_state) {
    $actions = parent::actions($form, $form_state);
    $actions['submit']['#value'] = ($this->entity->hasMapping() ? $this->t('Update mapping') : $this->t('Create mapping'));
    $actions['close'] = array(
      '#type' => 'submit',
      '#value' => t('Cancel'),
    );
    unset($actions['delete']);
    return $actions;
  }

}
