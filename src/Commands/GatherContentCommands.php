<?php

namespace Drupal\gathercontent\Commands;

use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\gathercontent\Entity\MappingInterface;
use Drupal\gathercontent\DrupalGatherContentClient;
use Drupal\gathercontent\Import\ImportOptions;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\gathercontent\Entity\Mapping;
use Drush\Commands\DrushCommands;
use Drush\Exceptions\UserAbortException;
use Consolidation\AnnotatedCommand\CommandData;
use Symfony\Component\Console\Question\ChoiceQuestion;

/**
 * Defines Drush commands for GatherContent module.
 */
class GatherContentCommands extends DrushCommands {

  use StringTranslationTrait;

  /**
   * Drupal GatherContent Client.
   *
   * @var \Drupal\gathercontent\DrupalGatherContentClient
   */
  protected $client;

  /**
   * The configuration object factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a new GatherContentCommand object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   Entity type manager.
   */
  public function __construct(DrupalGatherContentClient $client, ConfigFactoryInterface $configFactory, EntityTypeManagerInterface $entityTypeManager) {
    $this->client = $client;
    $this->configFactory = $configFactory;
    $this->entityTypeManager = $entityTypeManager;
  }

  /**
   * Lists the available mapping definitions.
   *
   * @command gathercontent:list-mappings
   * @aliases gc-lm
   * @description Lists the available mapping definitions.
   * @bootstrap DRUSH_BOOTSTRAP_DRUPAL_FULL
   * @field-labels
   *   mapping_id: Mapping ID
   *   project_id: Project ID
   *   project_label: Project label
   *   template_id: Template ID
   *   template_label: Template label
   *   content_type: Content type
   * @return array
   *   The list of mappings.
   */
  public function listMappings() {
    $gc_mappings = $this->entityTypeManager->getStorage('gathercontent_mapping')->loadMultiple();
    $mappings = [];
    foreach ($gc_mappings as $gc_mapping) {
      $mappings[$gc_mapping->id()] = [
        'mapping_id' => $gc_mapping->id(),
        'project_id' => $gc_mapping->getGathercontentProjectId(),
        'project_label' => $gc_mapping->getGathercontentProject(),
        'template_id' => $gc_mapping->getGathercontentTemplateId(),
        'template_label' => $gc_mapping->getGathercontentTemplate(),
        'content_type' => $gc_mapping->getContentType(),
      ];
    }
    return $mappings;
  }

  /**
   * Lists the node status definitions.
   *
   * @command gathercontent-list-status
   * @aliases gc-ls
   * @description Lists the node status definitions.
   * @bootstrap DRUSH_BOOTSTRAP_DRUPAL_FULL
   * @argument project_id GatherContent project ID. Use: gathercontent-list-mappings
   * @option format The format to output the results in. Defaults to "table".
   * @option fields A comma-separated list of fields to include in the output. Defaults to "status_id,status_label".
   * @field-labels
   *   status_id: Status ID
   *   status_label: Status label
   *
   * @usage gathercontent-list-status
   *   Lists all node status definitions.
   * @usage gathercontent-list-status --format=json
   *   Lists all node status definitions in JSON format.
   * @usage gathercontent-list-status 123 --fields=status_id,status_label
   *   Lists node status definitions for project ID 123 with only the "status_id" and "status_label" fields included in the output.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup|array
   *   The list of node status definitions.
   */
  public function listStatus($project_id = NULL, $options = []) {

    if ($project_id === NULL) {
      $account_id = $this->client::getAccountId();
      if (!$account_id) {
        throw new UserAbortException(dt('No accounts configured.'));
      }
      $projects = $this->client->projectsGet($account_id);

      $choices = [];
      foreach ($projects['data'] as $id => $project) {
        $choices[] = $project->name . ', ' . $project->id;
      }
      $question = new ChoiceQuestion(dt('Select a project ID: '), $choices);
      $project = explode(', ', $this->io()->askQuestion($question));
      $project_id = end($project);
    }

    if (!$project_id) {
      throw new UserAbortException(dt('Unknown project ID.'));
    }

    $statuses = $this->client->projectStatusesGet($project_id);
    $mappings = [];
    foreach ($statuses['data'] as $id => $status) {
      $mappings[$status->id] = [
        'status_id' => $status->id,
        'status_label' => $status->name,
      ];
    }

    return $mappings;
  }

  /**
   * Import content from GatherContent site.
   *
   * @command gathercontent:import
   * @aliases gc-i
   * @bootstrap DRUSH_BOOTSTRAP_DRUPAL_FULL
   * @description Import content from GatherContent site.
   * @option publish Use --publish or --no-publish.
   * @option create-new-revision Use --create-new-revision or --no-create-new-revision.
   * @argument mapping_id The drupal side content mapping ID. Use: gathercontent-list-mappings
   * @argument status_id Change the document status on GC side. Use: gathercontent-list-status
   * @argument parent_menu_item Parent menu item. E.g.: Create under 'My account' menu, use: 'account:user.page'
   * @field-labels
   *   id: ID
   *   item_name: Item name
   *   node_status: Node status
   *   import_status: Import status
   * @default-fields id,item_name,node_status,import_status
   * @return \Drupal\Core\Serialization\YamlReference|void
   */
  function import($mapping_id = NULL, $status_id = FALSE, $parent_menu_item = FALSE, $options = ['publish' => TRUE, 'create-new-revision' => TRUE]) {
    if ($mapping_id === NULL) {
      /** @var \Drupal\gathercontent\Entity\MappingInterface[] $gc_mappings */
      $gc_mappings = $this->entityTypeManager->getStorage('gathercontent_mapping')->loadMultiple();

      $choices = [];
      foreach ($gc_mappings as $gc_mapping) {
        $choices[] = $gc_mapping->getGathercontentProject() . ' | ' . $gc_mapping->getGathercontentTemplate() . ', ' . $gc_mapping->id();
      }
      $question = new ChoiceQuestion(dt('Select a mapping ID: '), $choices);
      $mapping = explode(', ', $this->io()->askQuestion($question));
      $mapping_id = end($mapping);
    }

    if (!$mapping_id) {
      throw new UserAbortException(dt('Unknown mapping ID.'));
    }

    $mapping = Mapping::load($mapping_id);
    $project_id = $mapping->getGathercontentProjectId();
    $template_id = $mapping->getGathercontentTemplateId();

    $items = $this->client->itemsGet($project_id)['data'];

    // Create and start Batch processes.
    $isItemFromSelectedTemplate = function ($item) use ($template_id) {
      return $item->templateId === $template_id;
    };
    $itemToId = function ($item) {
      return $item->id;
    };

    $selected_items = array_filter($items, $isItemFromSelectedTemplate);
    $gc_ids = array_map($itemToId, $selected_items);

    $operations = [];

    foreach ($gc_ids as $gc_id) {
      $import_options[$gc_id] = new ImportOptions(
        (bool) $options['publish'],
        (bool) $options['create-new-revision'],
        $status_id,
        $parent_menu_item
      );
    }

    $operations[] = [
      'gathercontent_import_process',
      [
        $gc_ids,
        $import_options,
        $mapping,
      ],
    ];

    $batch = [
      'title' => t('Importing'),
      'init_message' => t('Starting import'),
      'error_message' => t('An error occurred during processing'),
      'progress_message' => t('Processed @current out of @total.'),
      'progressive' => TRUE,
      'operations' => $operations,
      'finished' => 'gathercontent_drush_import_process_finished',
    ];

    batch_set($batch);
    drush_backend_batch_process();
  }


}
