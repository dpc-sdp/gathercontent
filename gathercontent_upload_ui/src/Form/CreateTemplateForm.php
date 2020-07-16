<?php

namespace Drupal\gathercontent_upload_ui\Form;

use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class CreateTemplateForm extends FormBase {

  /**
   * The entity bundle info.
   *
   * @var \Drupal\Core\Entity\EntityTypeBundleInfoInterface
   */
  protected $entityTypeBundleInfo;

  /**
   * MappingCreator constructor.
   */
  public function __construct(EntityTypeBundleInfoInterface $entityTypeBundleInfo) {
    $this->entityTypeBundleInfo = $entityTypeBundleInfo;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.bundle.info')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'gathercontent_create_template_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['gathercontent']['entity_type'] = [
      '#type' => 'select',
      '#title' => $this->t('Drupal entity type'),
      '#options' => $this->getEntityTypes(),
      '#required' => TRUE,
      '#wrapper_attributes' => [
        'class' => [
          'inline-label',
        ],
      ],
      '#ajax' => [
        'callback' => '::getContentTypes',
        'wrapper' => 'content-type-select',
        'method' => 'replace',
        'effect' => 'fade',
      ],
      '#default_value' => $form_state->getValue('entity_type'),
    ];

    $form['gathercontent']['content_type'] = [
      '#type' => 'select',
      '#title' => $this->t('Drupal bundle type'),
      '#options' => $this->getBundles($form_state->getValue('entity_type')),
      '#required' => TRUE,
      '#wrapper_attributes' => [
        'class' => [
          'inline-label',
        ],
      ],
      '#prefix' => '<div id="content-type-select">',
      '#suffix' => '</div>',
    ];

    $form['actions']['#type'] = 'actions';
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Create'),
      '#button_type' => 'primary',
      '#weight' => 10,
    ];

    return $form;
  }

  public function submitForm(array &$form, FormStateInterface $form_state) {
    // TODO: Implement submitForm() method.
  }

  /**
   * Get list of bundle types.
   *
   * @param string $entityType
   *   Entity type ID.
   *
   * @return array
   *   Assoc array of bundle types.
   */
  public function getBundles($entityType) {
    $bundleTypes = $this->entityTypeBundleInfo->getBundleInfo($entityType);
    $response = [];

    foreach ($bundleTypes as $key => $value) {
      $response[$key] = $value['label'];
    };

    return $response;
  }

  /**
   * Get list of entity types.
   *
   * @return array
   *   Assoc array of entity types.
   */
  public function getEntityTypes() {
    $entityTypes = \Drupal::entityTypeManager()->getDefinitions();
    $unsupportedTypes = [
      'user',
      'file',
      'menu_link_content',
    ];
    $response = [];

    foreach ($entityTypes as $key => $value) {
      if ($value) {
        $class = $value->getOriginalClass();
        if (in_array(FieldableEntityInterface::class, class_implements($class))
          && !in_array($key, $unsupportedTypes)) {
          $label = (string) $value->getLabel();
          $response[$key] = $label;
        }
      }
    }

    return $response;
  }

  /**
   * Ajax callback for mapping multistep form.
   *
   * @return array
   *   Array of form elements.
   *
   * @inheritdoc
   */
  public function getContentTypes(array &$form, FormStateInterface $form_state) {
    $form_state->setRebuild(TRUE);
    return $form['gathercontent']['content_type'];
  }

}
