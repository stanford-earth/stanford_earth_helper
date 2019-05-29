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
    $department = NULL;
    $properties = [
      'name' => $row->getSourceProperty('current_feed_url'),
      'vid' => 'stanford_earth_event_feeds',
    ];
    $terms = \Drupal::entityTypeManager()
      ->getStorage('taxonomy_term')
      ->loadByProperties($properties);
    if (!empty($terms)) {
      foreach ($terms as $term) {
        $description = $term->description->value;
        if (strpos($description, 'energy resources engineering') !== FALSE) {
          $department = "Energy Resources Engineering";
        }
        elseif (strpos($description, "emmett") !== FALSE) {
          $department = "Emmett Interdisciplinary Program in Environment & Resources";
        }
        elseif (strpos($description, 'earth system science') !== FALSE) {
          $department = "Earth System Science";
        }
        elseif (strpos($description, 'geophysics') !== FALSE) {
          $department = "Geophysics";
        }
        elseif (strpos($description, 'earth systems program') !== FALSE) {
          $department = "Earth Systems Program";
        }
        elseif (strpos($description, 'geological science') !== FALSE) {
          $department = "Geological Sciences";
        }
        elseif (strpos($description, 'sustainability') !== FALSE) {
          $department = "Sustainability Science and Practice";
        }
        break;
      }
    }
    return [$department];
  }

}
