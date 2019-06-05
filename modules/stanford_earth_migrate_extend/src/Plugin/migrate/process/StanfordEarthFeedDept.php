<?php

namespace Drupal\stanford_earth_migrate_extend\Plugin\migrate\process;

use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\Row;
use Drupal\node\Entity\Node;

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

    $departments = [];
    if (!empty($department)) {
      $departments[] = $department;
    }

    // If an event is imported from more than one feed (via bookmarking)
    // we want to make sure we maintain all Department tags put in place.
    $nid = $row->getSourceProperty('nid');
    if (!empty($nid)) {
      $existing = Node::load($nid);
      if (!empty($existing)) {
        $depts = $existing->get('field_s_event_department')->getValue();
        foreach ($depts as $dept) {
          if (!empty($dept['target_id'])) {
            $deptTerm = \Drupal::getContainer()->get('entity.manager')
              ->getStorage('taxonomy_term')->load($dept['target_id']);
            if (!empty($deptTerm)) {
              $deptTermName = $deptTerm->getName();
              if (!empty($deptTermName) &&
                !in_array($deptTermName, $departments)) {
                $departments[] = $deptTermName;
              }
            }
          }
        }
      }
    }
    return $departments;
  }

}
