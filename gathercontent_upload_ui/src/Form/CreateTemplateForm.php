<?php

namespace Drupal\gathercontent_upload_ui\Form;

use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\gathercontent_upload\Export\MappingCreator;
use Symfony\Component\DependencyInjection\ContainerInterface;

class CreateTemplateForm extends FormBase {

  /**
   * Entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The entity bundle info.
   *
   * @var \Drupal\Core\Entity\EntityTypeBundleInfoInterface
   */
  protected $entityTypeBundleInfo;

  /**
   * Mapping creator.
   *
   * @var \Drupal\gathercontent_upload\Export\MappingCreator
   */
  protected $mappingCreator;

  /**
   * MappingCreator constructor.
   */
  public function __construct(
    EntityTypeManagerInterface $entityTypeManager,
    EntityTypeBundleInfoInterface $entityTypeBundleInfo,
    MappingCreator $mappingCreator
  ) {
    $this->entityTypeManager = $entityTypeManager;
    $this->entityTypeBundleInfo = $entityTypeBundleInfo;
    $this->mappingCreator = $mappingCreator;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('entity_type.bundle.info'),
      $container->get('gathercontent_upload.mapping_creator')
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
    $form['gathercontent']['project_id'] = [
      '#type' => 'select',
      '#title' => $this->t('Project ID'),
      '#options' => $this->getProjects(),
      '#required' => TRUE,
      '#wrapper_attributes' => [
        'class' => [
          'inline-label',
        ],
      ],
      '#default_value' => $form_state->getValue('project_id'),
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

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->mappingCreator->generateMapping(
      $form_state->getValue('entity_type'),
      $form_state->getValue('content_type'),
      $form_state->getValue('project_id')
    );

    $this->messenger()->addMessage('Mapping created successfully');
  }

  /**
   * Get list of bundle types.
   *
   * @param string $entityType
   *   Entity type ID.
   *
   * @return array
   *   Assoc array of bundle types.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function getBundles($entityType) {
    $mappingStorage = $this->entityTypeManager->getStorage('gathercontent_mapping');
    $bundleTypes = $this->entityTypeBundleInfo->getBundleInfo($entityType);
    $response = [];

    foreach ($bundleTypes as $key => $value) {
      $mapping = $mappingStorage->loadByProperties([
        'entity_type' => $entityType,
        'content_type' => $key,
      ]);

      if ($mapping) {
        continue;
      }

      $response[$key] = $value['label'];
    }

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

  /**
   * Returns all projects for given account.
   *
   * @return array
   */
  public function getProjects() {
    return $this->mappingCreator->getProjects();
  }

}
