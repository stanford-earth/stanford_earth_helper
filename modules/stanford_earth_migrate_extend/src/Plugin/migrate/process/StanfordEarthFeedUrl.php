<?php

namespace Drupal\stanford_earth_migrate_extend\Plugin\migrate\process;

use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\Row;
use Drupal\node\Entity\Node;

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

    $feed_url = $row->getSourceProperty('current_feed_url');
    $feed_urls = [];
    if (!empty($feed_url)) {
      $feed_urls[] = $feed_url;
    }

    // If an event is imported from more than one feed (via bookmarking)
    // we want to make sure we maintain all feed urls.
    $nid = $row->getSourceProperty('nid');
    if (!empty($nid)) {
      $existing = Node::load($nid);
      if (!empty($existing)) {
        $urls = $existing->get('field_s_event_feed_url')->getValue();
        foreach ($urls as $url) {
          if (!empty($url['target_id'])) {
            $urlTerm = \Drupal::getContainer()->get('entity_type.manager')
              ->getStorage('taxonomy_term')->load($url['target_id']);
            if (!empty($urlTerm)) {
              $urlTermName = $urlTerm->getName();
              if (!empty($urlTermName) &&
                !in_array($urlTermName, $feed_urls)) {
                $feed_urls[] = $urlTermName;
              }
            }
          }
        }
      }
    }
    return $feed_urls;
  }

}
