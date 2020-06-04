<?php

namespace Drupal\gathercontent_ui\Form\MappingEditSteps;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Class MappingStepEdit.
 *
 * @package Drupal\gathercontent_ui\Form
 */
class MappingStepEdit extends MappingSteps {

  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public function getForm(FormStateInterface $formState) {
    $form = parent::getForm($formState);

    $filterFormats = filter_formats();
    $filterFormatOptions = [];

    foreach ($filterFormats as $key => $filterFormat) {
      $filterFormatOptions[$key] = $filterFormat->label();
    }

    $mappingData = unserialize($this->mapping->getData());
    $contentType = $this->mapping->getContentType();
    $entityType = $this->mapping->getMappedEntityType();

    $form['gathercontent']['content_type'] = [
      '#type' => 'item',
      '#title' => $this->t('Drupal bundle type:'),
      '#markup' => $this->mapping->getContentTypeName(),
      '#wrapper_attributes' => [
        'class' => [
          'inline-label',
        ],
      ],
    ];

    $form['mapping'] = [
      '#prefix' => '<div id="edit-mapping">',
      '#suffix' => '</div>',
    ];

    foreach ($this->template['related']->structure->groups as $i => $group) {
      $form['mapping'][$group->id] = [
        '#type' => 'details',
        '#title' => $group->name,
        '#open' => array_search(
          $i,
          array_keys($this->template['related']->structure->groups)
        ) === 0,
        '#tree' => TRUE,
      ];

      if (
        \Drupal::moduleHandler()->moduleExists('metatag')
        && $this->metatagQuery->checkMetatag($entityType, $contentType)
      ) {
        $form['mapping'][$group->id]['type'] = [
          '#type' => 'select',
          '#options' => [
            'content' => $this->t('Content'),
            'metatag' => $this->t('Metatag'),
          ],
          '#title' => $this->t('Type'),
          '#default_value' => (
            isset($mappingData[$group->id]['type'])
            || $formState->hasValue($group->id)
          )
          ? (
              $formState->hasValue($group->id)
                ? $formState->getValue($group->id)['type']
                : $mappingData[$group->id]['type'])
          : 'content',
          '#ajax' => [
            'callback' => '::getMappingTable',
            'wrapper' => 'edit-mapping',
            'method' => 'replace',
            'effect' => 'fade',
          ],
        ];
      }

      if (\Drupal::moduleHandler()->moduleExists('content_translation') &&
        \Drupal::service('content_translation.manager')
          ->isEnabled('node', $formState->getValue('content_type'))
      ) {

        $form['mapping'][$group->id]['language'] = [
          '#type' => 'select',
          '#options' => ['und' => $this->t('None')] + $this->getLanguageList(),
          '#title' => $this->t('Language'),
          '#default_value' => isset($mappingData[$group->id]['language']) ? $mappingData[$group->id]['language'] : 'und',
        ];
      }

      foreach ($group->fields as $gc_field) {
        $d_fields = [];
        if (isset($formState->getTriggeringElement()['#name'])) {
          // We need different handling for changed group.
          if ($formState->getTriggeringElement()['#array_parents'][1] === $group->id) {
            if ($formState->getTriggeringElement()['#value'] === 'content') {
              $d_fields = $this->filterFields($gc_field, $contentType, $entityType);
            }
            elseif ($formState->getTriggeringElement()['#value'] === 'metatag') {
              $d_fields = $this->filterMetatags($gc_field, $contentType);
            }
          }
          else {
            if ($formState->getValue($group->id)['type'] === 'content') {
              $d_fields = $this->filterFields($gc_field, $contentType, $entityType);
            }
            elseif ($formState->getTriggeringElement()['#value'] === 'metatag') {
              $d_fields = $this->filterMetatags($gc_field, $contentType);
            }
          }
        }
        else {
          if ((isset($mappingData[$group->id]['type']) && $mappingData[$group->id]['type'] === 'content') || !isset($mappingData[$group->id]['type'])) {
            $d_fields = $this->filterFields($gc_field, $contentType, $entityType);
          }
          else {
            $d_fields = $this->filterMetatags($gc_field, $contentType);
          }
        }
        $form['mapping'][$group->id]['elements'][$gc_field->id] = [
          '#type' => 'select',
          '#options' => $d_fields,
          '#title' => (!empty($gc_field->label) ? $gc_field->label : $gc_field->title),
          '#default_value' => isset($mappingData[$group->id]['elements'][$gc_field->id]) ? $mappingData[$group->id]['elements'][$gc_field->id] : NULL,
          '#empty_option' => $this->t("Don't map"),
          '#attributes' => [
            'class' => [
              'gathercontent-ct-element',
            ],
          ],
        ];

        if (
          in_array($gc_field->type, ['text', 'guidelines'])
          && (!isset($gc_field->metaData->isPlain) || !$gc_field->metaData->isPlain)
        ) {
          $form['mapping'][$group->id]['element_text_formats'][$gc_field->id] = [
            '#type' => 'select',
            '#options' => $filterFormatOptions,
            '#title' => (!empty($gc_field->label) ? $gc_field->label : $gc_field->title),
            '#default_value' => isset($mappingData[$group->id]['element_text_formats'][$gc_field->id]) ? $mappingData[$group->id]['element_text_formats'][$gc_field->id] : NULL,
            '#empty_option' => $this->t("Choose text format"),
            '#attributes' => [
              'class' => [
                'gathercontent-ct-element',
              ],
            ],
          ];
        }
      }

      if (!empty($form['mapping'][$group->id]['element_text_formats'])) {
        $form['mapping'][$group->id]['element_text_formats']['#type'] = 'details';
        $form['mapping'][$group->id]['element_text_formats']['#title'] = $this->t('Text format settings');
        $form['mapping'][$group->id]['element_text_formats']['#open'] = FALSE;
      }
    }
    $form['mapping']['er_mapping_type'] = [
      '#type' => 'radios',
      '#title' => $this->t('Taxonomy terms mapping type'),
      '#options' => [
        'automatic' => $this->t('Automatic'),
        'semiautomatic' => $this->t('Semi-automatic'),
        'manual' => $this->t('Manual'),
      ],
      '#attributes' => [
        'class' => ['gathercontent-er-mapping-type'],
      ],
      '#description' => $this->t("<strong>Automatic</strong> - taxonomy terms will be automatically created in predefined vocabulary. You cannot select translations. Field should be set as translatable for correct functionality.<br>
<strong>Semi-automatic</strong> - taxonomy terms will be imported into predefined vocabulary in the first language and we will offer you possibility to select their translations from other languages. For single language mapping this option will execute same action as 'Automatic' importField should not be set as translatable for correct functionality.<br>
<strong>Manual</strong> - you can map existing taxonomy terms from predefined vocabulary to translations in all languages."),
    ];

    return $form;
  }

}
