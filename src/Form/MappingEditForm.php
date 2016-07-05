<?php

namespace Drupal\gathercontent\Form;

use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\field\Entity\FieldConfig;
use Drupal\gathercontent\DAO\Template;
use Drupal\gathercontent\Entity\Mapping;

/**
 * Class MappingEditForm.
 *
 * @package Drupal\gathercontent\Form
 */
class MappingEditForm extends EntityForm {

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    /** @var Mapping $mapping */
    $mapping = $this->entity;

    $content_types = node_type_get_names();
    $tmp = new Template();
    $template = $tmp->getTemplate($mapping->getGathercontentTemplateId());
    $new = !$mapping->hasMapping();
    $form['#attached']['library'][] = 'gathercontent/theme';
    $form['form_description'] = array(
      '#type' => 'html_tag',
      '#tag' => 'i',
      '#value' => t('Please map your GatherContent Template fields to your Drupal 
    Content Type Fields. Please note that a GatherContent field can only be 
    mapped to a single Drupal field. So each field can only be mapped to once.'),
    );

    $form['gathercontent_project'] = [
      '#type' => 'item',
      '#title' => $this->t('Project name:'),
      '#markup' => $mapping->getGathercontentProject(),
      '#wrapper_attributes' => [
        'class' => [
          'inline-label',
        ],
      ],
    ];
    $form['gathercontent'] = [
      '#type' => 'container',
    ];

    $form['gathercontent']['gathercontent_template'] = [
      '#type' => 'item',
      '#title' => $this->t('GatherContent template:'),
      '#markup' => $mapping->getGathercontentTemplate(),
      '#wrapper_attributes' => [
        'class' => [
          'inline-label',
        ],
      ],
    ];

    if (!$new) {
      $mapping_data = unserialize($mapping->getData());
      $content_type = $mapping->getContentTypeName();

      $form['gathercontent']['content_type'] = [
        '#type' => 'item',
        '#title' => $this->t('Drupal content type:'),
        '#markup' => $content_type,
        '#wrapper_attributes' => [
          'class' => [
            'inline-label',
          ],
        ],
      ];

      $form['mapping'] = array(
        '#prefix' => '<div id="edit-mapping">',
        '#suffix' => '</div>',
      );

      foreach ($template->config as $i => $fieldset) {
        if ($fieldset->hidden === FALSE) {
          $form['mapping'][$fieldset->name] = array(
            '#type' => 'details',
            '#title' => $fieldset->label,
            '#open' => ($i === 0 ? TRUE : FALSE),
            '#tree' => TRUE,
          );

          if (\Drupal::moduleHandler()->moduleExists('metatag')) {
            $form['mapping'][$fieldset->name]['type'] = array(
              '#type' => 'select',
              '#options' => array(
                'content' => t('Content'),
                'metatag' => t('Metatag'),
              ),
              '#title' => t('Type'),
              '#default_value' => (isset($mapping_data[$fieldset->name]['type']) || $form_state->hasValue($fieldset->name)['type']) ? ($form_state->hasValue($fieldset->name)['type'] ? $form_state->getValue($fieldset->name)['type'] : $mapping_data[$fieldset->name]['type']) : 'content',
              '#ajax' => array(
                'callback' => '::getMappingTable',
                'wrapper' => 'edit-mapping',
                'method' => 'replace',
                'effect' => 'fade',
              ),
            );
          }

          if (\Drupal::moduleHandler()->moduleExists('content_translation') &&
            \Drupal::service('content_translation.manager')
              ->isEnabled('node', $form_state->getValue('content_type'))
          ) {

            $form['mapping'][$fieldset->name]['language'] = array(
              '#type' => 'select',
              '#options' => array('und' => t('None')) + $this->getLanguageList(),
              '#title' => t('Language'),
              '#default_value' => isset($mapping_data[$fieldset->name]['language']) ? $mapping_data[$fieldset->name]['language'] : 'und',
            );
          }

          foreach ($fieldset->elements as $gc_field) {
            $d_fields = array();
            if (isset($form_state->getTriggeringElement()['#name'])) {
              // We need different handling for changed fieldset.
              if ($form_state->getTriggeringElement()['#array_parents'][1] === $fieldset->name) {
                if ($form_state->getTriggeringElement()['#value'] === 'content') {
                  $d_fields = $this->filterFields($gc_field, $mapping->getContentType());
                }
                elseif ($form_state->getTriggeringElement()['#value'] === 'metatag') {
                  $d_fields = $this->filterMetatags($gc_field);
                }
              }
              else {
                if ($form_state->getValue($fieldset->name)['type'] === 'content') {
                  $d_fields = $this->filterFields($gc_field, $mapping->getContentType());
                }
                elseif ($form_state->getTriggeringElement()['#value'] === 'metatag') {
                  $d_fields = $this->filterMetatags($gc_field);
                }
              }
            }
            else {
              if ((isset($mapping_data[$fieldset->name]['type']) && $mapping_data[$fieldset->name]['type'] === 'content') || !isset($mapping_data[$fieldset->name]['type'])) {
                $d_fields = $this->filterFields($gc_field, $mapping->getContentType());
              }
              else {
                $d_fields = $this->filterMetatags($gc_field);
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
      $form['updated'] = array(
        '#type' => 'value',
        '#value' => $template->updated_at,
      );

      $form['gathercontent']['content_type'] = array(
        '#type' => 'select',
        '#title' => $this->t('Drupal content type'),
        '#options' => $content_types,
        '#required' => TRUE,
        '#wrapper_attributes' => [
          'class' => [
            'inline-label',
          ],
        ],
        '#ajax' => array(
          'callback' => '::getMappingTable',
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

            if (\Drupal::moduleHandler()->moduleExists('metatag')) {
              $form['mapping'][$fieldset->name]['type'] = array(
                '#type' => 'select',
                '#options' => array(
                  'content' => t('Content'),
                  'metatag' => t('Metatag'),
                ),
                '#title' => t('Type'),
                '#default_value' => $form_state->hasValue($fieldset->name)['type'] ? $form_state->getValue($fieldset->name)['type'] : 'content',
                '#ajax' => array(
                  'callback' => '::getMappingTable',
                  'wrapper' => 'edit-mapping',
                  'method' => 'replace',
                  'effect' => 'fade',
                ),
              );
            }

            if (\Drupal::moduleHandler()->moduleExists('content_translation') &&
              \Drupal::service('content_translation.manager')
                ->isEnabled('node', $form_state->getValue('content_type'))
            ) {

              $form['mapping'][$fieldset->name]['language'] = array(
                '#type' => 'select',
                '#options' => array('und' => t('None')) + $this->getLanguageList(),
                '#title' => t('Language'),
                '#default_value' => $form_state->hasValue($fieldset->name)['language'] ? $form_state->getValue($fieldset->name)['language'] : 'und',
              );
            }

            foreach ($fieldset->elements as $gc_field) {
              $d_fields = array();
              $content_type = $form_state->getValue('content_type');
              if ($form_state->getTriggeringElement()['#name'] !== 'content_type') {
                // We need different handling for changed fieldset.
                if ($form_state->getTriggeringElement()['#array_parents'][1] === $fieldset->name) {
                  if ($form_state->getTriggeringElement()['#value'] === 'content') {
                    $d_fields = $this->filterFields($gc_field, $content_type);
                  }
                  elseif ($form_state->getTriggeringElement()['#value'] === 'metatag') {
                    $d_fields = $this->filterMetatags($gc_field);
                  }
                }
                else {
                  if ($form_state->getValue($fieldset->name)['type'] === 'content') {
                    $d_fields = $this->filterFields($gc_field, $content_type);
                  }
                  elseif ($form_state->getValue($fieldset->name)['type'] === 'metatag') {
                    $d_fields = $this->filterMetatags($gc_field);
                  }
                }
              }
              else {
                $d_fields = $this->filterFields($gc_field, $content_type);
              }
              $form['mapping'][$fieldset->name]['elements'][$gc_field->name] = array(
                '#type' => 'select',
                '#options' => $d_fields,
                '#title' => (isset($gc_field->label) ? $gc_field->label : $gc_field->title),
                '#empty_option' => $this->t("Don't map"),
                '#default_value' => $form_state->hasValue($fieldset->name)['elements'][$gc_field->name]
                  ? $form_state->getValue($fieldset->name)['elements'][$gc_field->name] : NULL,
              );
            }
          }
        }
      }
    }
    return $form;
  }

  /**
   * Get list of languages as assoc array.
   *
   * @return array
   *   Assoc array of languages keyed by lang code, value is language name.
   */
  protected function getLanguageList() {
    $languages = \Drupal::service('language_manager')
      ->getLanguages(LanguageInterface::STATE_CONFIGURABLE);
    $language_list = [];
    foreach ($languages as $lang_code => $language) {
      /** @var \Drupal\Core\Language\Language $language */
      $language_list[$lang_code] = $language->getName();
    }
    return $language_list;
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
  protected function filterFields($gc_field, $content_type) {
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
        'string',
      ),
      'choice_checkbox' => array(
        'list_string',
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
   * Return only supported metatag fields.
   *
   * @param object $gathercontent_field
   *   Object of field from GatherContent.
   *
   * @return array
   *   Array of supported metatag fields.
   */
  protected function filterMetatags($gathercontent_field) {
    if ($gathercontent_field->type === 'text' && $gathercontent_field->plain_text) {
      return array(
        'title' => t('Title'),
        'description' => t('Description'),
        'abstract' => t('Abstract'),
        'keywords' => t('Keywords'),
      );
    }

    else {
      return array();
    }
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
  public function submitForm(array &$form, FormStateInterface $form_state) {
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
        'gathercontent_project',
        'gathercontent_template',
      ));

      $mapping_data = array();
      foreach ($form_state->getValues() as $key => $value) {
        if (!in_array($key, $non_data_elements) && substr_compare($key, 'tab', 0, 3) === 0) {
          $mapping_data[$key] = $value;
        }
      }
      /** @var Mapping $mapping */
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
      foreach ($template->config as $i => $fieldset) {
        if ($fieldset->hidden === FALSE) {
          foreach ($fieldset->elements as $gc_field) {
            $local_field_name = $mapping_data[$fieldset->name]['elements'][$gc_field->name];
            if ($gc_field->type === 'choice_checkbox') {
              if (!empty($local_field_name)) {
                $local_options = array();
                foreach ($gc_field->options as $option) {
                  $local_options[$option->name] = $option->label;
                }
                $field_info = FieldConfig::loadByName('node', $mapping->getContentType(), $local_field_name);
                $field_info = $field_info->getFieldStorageDefinition();
                // Make the change.
                $field_info->setSetting('allowed_values', $local_options);
                try {
                  $field_info->save();
                }
                catch (\Exception $e) {
                  // Log something.
                }
              }
            }
            elseif ($gc_field->type === 'choice_radio') {
              if (!empty($mapping_data[$fieldset->name]['elements'][$gc_field->name])) {
                $local_options = array();
                foreach ($gc_field->options as $option) {
                  if ($option != end($gc_field->options)) {
                    $local_options[] = $option->name . '|' . $option->label;
                  }
                }
                $entity = \Drupal::entityManager()
                  ->getStorage('entity_form_display')
                  ->load('node.'.$mapping->getContentType().'.default');
                /** @var \Drupal\Core\Entity\Entity\EntityFormDisplay $entity */
                $entity->getRenderer($local_field_name)
                  ->setSetting('available_options', implode("\n", $local_options));
              }
            }
          }
        }
      }
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
