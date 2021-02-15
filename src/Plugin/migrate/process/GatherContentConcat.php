<?php

namespace Drupal\gathercontent\Plugin\migrate\process;

use Drupal\migrate\MigrateException;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\Plugin\migrate\process\Concat;
use Drupal\migrate\Row;

/**
 * Perform custom value transformation.
 *
 * @\Drupal\migrate\Annotation\MigrateProcessPlugin(
 *   id = "gather_content_concat",
 *   handle_multiples = TRUE
 * )
 */
class GatherContentConcat extends Concat {

  /**
   * {@inheritdoc}
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    if (is_array($value)) {
      $delimiter = isset($this->configuration['delimiter']) ? $this->configuration['delimiter'] : '';

      if (!is_array(reset($value))) {
        return implode($delimiter, $value);
      }

      $deltaValues = [];
      foreach ($value as $itemValue) {
        if (is_array($itemValue)) {
          foreach ($itemValue as $subItemKey => $subItemValue) {
            $deltaValues[$subItemKey][] = $subItemValue['value'];
          }
        }
      }

      $result = [];
      foreach ($deltaValues as $deltaKey => $deltaValue) {
        $result[$deltaKey]['value'] = implode($delimiter, $deltaValue);
      }

      return $result;
    }
    else {
      throw new MigrateException(sprintf('%s is not an array', var_export($value, TRUE)));
    }
  }

}
