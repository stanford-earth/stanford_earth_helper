<?php

namespace Drupal\stanford_earth_events\Plugin\migrate\process;

use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\Row;

/**
 * Returns the base file name of the image url.
 *
 * @see \Drupal\migrate\Plugin\MigrateProcessInterface
 *
 * @MigrateProcessPlugin(
 *   id = "stanford_earth_events_image_basename"
 * )
 */
class StanfordEarthEventsImageBasename extends ProcessPluginBase {

  /**
   * {@inheritdoc}
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {

    $basename = '';
    if (!empty($value)) {
      $basename = \Drupal::service('file_system')->basename($value);
    }
    return $basename;
  }

}
