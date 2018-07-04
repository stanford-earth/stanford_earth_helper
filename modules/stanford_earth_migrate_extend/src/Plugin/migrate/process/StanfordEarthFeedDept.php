<?php

namespace Drupal\stanford_earth_migrate_extend\Plugin\migrate\process;

use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\Row;

/**
 * Looks up the Department or Program that goes with the events feed URL.
 *
 * @MigrateProcessPlugin(
 *   id = "stanford_earth_feed_dept"
 * )
 */
class StanfordEarthFeedDept extends ProcessPluginBase {
  
  /**
   * {@inheritdoc}
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    $department = null;
    $properties = [
      'name' => $row->getSourceProperty('current_feed_url'),
      'vid' => 'stanford_earth_event_feeds',
    ];
    $terms = \Drupal::entityTypeManager()
      ->getStorage('taxonomy_term')
      ->loadByProperties($properties);
    if (!empty($terms)) {
      foreach ($terms as $tid => $term) {
        $description = $term->description->value;
        if (strpos($description, 'energy resources engineering')!== FALSE) {
          $department = "Energy Resources Engineering";
        } else if (strpos($description, "emmett") !== FALSE) {
          $department = "Emmett Interdisciplinary Program in Environment & Resources";
        } else if (strpos($description, 'earth system science') !== FALSE) {
          $department = "Earth System Science";
        } else if (strpos($description, 'geophysics') !== FALSE) {
          $department = "Geophysics";
        } else if (strpos($description, 'earth systems program') !== FALSE) {
          $department = "Earth Systems Program";
        } else if (strpos($description, 'geological science') !== FALSE) {
          $department = "Geological Sciences";
        } else if (strpos($description, 'sustainability') !== FALSE) {
          $department = "Sustainability Science and Practice";
        }
        break;
      }
    }
    return $department;
  }
  
}
