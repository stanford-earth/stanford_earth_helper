<?php

namespace Drupal\stanford_earth_events\Plugin\migrate\process;

use Drupal\migrate\MigrateSkipProcessException;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\Row;
use Drupal\migrate\MigrateSkipRowException;

/**
 * Skips processing the current row when the Localist Origin ID is not empty.
 *
 * The skip_on_origin_id process plugin checks to see if the Localist event has
 * its origin_id field set and if it starts with EARTH_ in which case this is
 * an event that originated on the Stanford Earth site and should not be
 * imported otherwise it will create a duplicate node.
 *
 * @see \Drupal\migrate\Plugin\MigrateProcessInterface
 *
 * @MigrateProcessPlugin(
 *   id = "skip_on_origin_id"
 * )
 */
class SkipOnOriginId extends ProcessPluginBase {

  /**
   * Skips the current row when value is set.
   *
   * @param mixed $value
   *   The input value.
   * @param \Drupal\migrate\MigrateExecutableInterface $migrate_executable
   *   The migration in which this process is being executed.
   * @param \Drupal\migrate\Row $row
   *   The row from the source to process.
   * @param string $destination_property
   *   The destination property currently worked on. This is only used together
   *   with the $row above.
   *
   * @return mixed
   *   The empty input value, $value.
   *
   * @throws \Drupal\migrate\MigrateSkipRowException
   *   Thrown if the source property is set and the row should be skipped,
   *   records with STATUS_IGNORED status in the map.
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    $originId = $row->getSourceProperty('origin_id');
    if (!empty($originId) && is_string($originId) &&
      substr($originId, 0, 6) === 'EARTH-' ) {
      throw new MigrateSkipRowException('');
    }
    return $originId;
  }

}
