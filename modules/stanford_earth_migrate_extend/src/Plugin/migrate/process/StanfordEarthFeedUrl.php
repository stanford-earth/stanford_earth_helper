<?php

namespace Drupal\stanford_earth_migrate_extend\Plugin\migrate\process;

use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\Row;

/**
 * Imports the Stanford Events feed URL from which the event comes.
 *
 * @MigrateProcessPlugin(
 *   id = "stanford_earth_feed_url"
 * )
 */
class StanfordEarthFeedUrl extends ProcessPluginBase {
  
  /**
   * {@inheritdoc}
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    return $row->getSourceProperty('current_feed_url');
  }
  
}
