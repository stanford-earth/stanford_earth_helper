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
    $department_list = [];
    $departments_in = \Drupal::config('migrate_plus.migration_group.earth_events')->get('departments');
    foreach ($departments_in as $dept_in) {
      if (strpos($dept_in,'|') !== false) {
        $dept_split = explode('|',$dept_in,2);
        $department_list[$dept_split[0]] = $dept_split[1];
      }
    }
    $properties = [
      'name' => $row->getSourceProperty('current_feed_url'),
      'vid' => 'stanford_earth_event_feeds',
    ];
    $terms = \Drupal::entityTypeManager()
      ->getStorage('taxonomy_term')
      ->loadByProperties($properties);
    if (!empty($terms)) {
      foreach ($terms as $term) {
        $description = trim(strip_tags($term->description->value));
        $bookmarked = strpos($description, " - bookmarked");
        if ($bookmarked !== false) {
          $description = trim(substr($description, 0, $bookmarked));
        }
        if (!empty($department_list[$description])) {
          $department = $department_list[$description];
          break;
        }
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
            $deptTerm = \Drupal::getContainer()->get('entity_type.manager')
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
