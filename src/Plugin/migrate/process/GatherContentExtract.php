<?php

namespace Drupal\gathercontent\Plugin\migrate\process;

use Drupal\migrate\MigrateException;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\MigrateSkipProcessException;
use Drupal\migrate\Plugin\migrate\process\Extract;
use Drupal\migrate\Row;

/**
 * Extracts a value from an array.
 *
 * @see \Drupal\migrate\Plugin\migrate\process\Extract
 *
 * @MigrateProcessPlugin(
 *   id = "gather_content_extract",
 *   handle_multiples = TRUE
 * )
 */
class GatherContentExtract extends Extract {

  /**
   * {@inheritdoc}
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    try {
      return parent::transform($value, $migrate_executable, $row, $destination_property);
    } catch (MigrateException $e) {
      // Throw a skip process exception instead, so the process will be skipped and the migration will not get terminated.
      throw new MigrateSkipProcessException($e->getMessage());
    }
  }

}
