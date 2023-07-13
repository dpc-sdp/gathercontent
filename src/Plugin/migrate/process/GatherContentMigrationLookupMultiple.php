<?php

namespace Drupal\gathercontent\Plugin\migrate\process;

use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\migrate\MigrateException;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\Plugin\migrate\process\MigrationLookup;
use Drupal\migrate\Row;

/**
 * A process plugin that supports to aggregate multiple migration destinations.
 *
 * Supports multiple migrations and multiple destinations per migration,
 * assuming :N suffixes of the source ID, see
 *
 * @MigrateProcessPlugin(
 *   id = "gathercontent_migrate_lookup_multiple"
 * )
 */
class GatherContentMigrationLookupMultiple extends MigrationLookup {

  /**
   * {@inheritdoc}
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {

    $lookup_migration_ids = (array) $this->configuration['migration'];
    $destination_ids = [];

    foreach ($lookup_migration_ids as $lookup_migration_id) {

      $lookup_value = (array) $value;
      $this->skipInvalid($lookup_value);

      // Re-throw any PluginException as a MigrateException so the executable
      // can shut down the migration.
      try {
        $destination_id_array = $this->migrateLookup->lookup($lookup_migration_id, $lookup_value);
      }
      catch (PluginNotFoundException $e) {
        $destination_id_array = [];
      }
      catch (MigrateException $e) {
        throw $e;
      }
      catch (\Exception $e) {
        throw new MigrateException(sprintf('A %s was thrown while processing this migration lookup', gettype($e)), $e->getCode(), $e);
      }

      if ($destination_id_array) {
        $destination_ids[] = array_values(reset($destination_id_array));
      }
      else {

        // If nothing was found, assume it is a split repeatable component,
        // retry starting with delta 0 and fetch results until the given delta
        // is not found.
        $delta = 0;
        do {
          $lookup_value = [$value . ':' . $delta];

          // Re-throw any PluginException as a MigrateException so the executable
          // can shut down the migration.
          try {
            $destination_id_array = $this->migrateLookup->lookup($lookup_migration_id, $lookup_value);
          }
          catch (PluginNotFoundException $e) {
            $destination_id_array = [];
          }
          catch (MigrateException $e) {
            throw $e;
          }
          catch (\Exception $e) {
            throw new MigrateException(sprintf('A %s was thrown while processing this migration lookup', gettype($e)), $e->getCode(), $e);
          }

          if ($destination_id_array) {
            $destination_ids[] = array_values(reset($destination_id_array));
          }

          $delta++;

        } while ($destination_id_array);
      }
    }

    return $destination_ids;
  }

}
