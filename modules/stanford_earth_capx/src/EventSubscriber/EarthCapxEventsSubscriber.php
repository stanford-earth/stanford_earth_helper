<?php

namespace Drupal\stanford_earth_capx\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Drupal\migrate\Event\MigrateEvents;
use Drupal\migrate\Event\MigratePreRowSaveEvent;
use Drupal\migrate\Event\MigratePostRowSaveEvent;
use Drupal\migrate\Row;
use Drupal\Core\Database;

/**
 * Class EntityTypeSubscriber.
 *
* @package Drupal\stanford_earth_capx\EventSubscriber
*/
class EarthCapxEventsSubscriber implements EventSubscriberInterface {

  /**
   * {@inheritdoc}
   *
   * @return array
   *   The event names to listen for, and the methods that should be executed.
   */
  public static function getSubscribedEvents() {
    return [
      MigrateEvents::POST_ROW_SAVE => 'migratePostRowSave',
    ];
  }

  /**
   * React to a migrate POST_ROW_SAVE event.
   *
   * @param \Drupal\migrate\Event\MigratePostRowSaveEvent $event
   *
   */
  public function migratePostRowSave(MigratePostRowSaveEvent $event) {

    $source = $event->getRow()->getSource();
    $sunetid = '';
    if (!empty($source['sunetid'])) $sunetid = $source['sunetid'];
    $etag = '';
    if (!empty($source['etag'])) $etag = $source['etag'];
    $photo_timestamp = 0;
    if (!empty($source['profilePhoto']) && is_string($source['profilePhoto']) &&
      strpos($source['profilePhoto'],"ts=") !== false) {
        $ts = substr($source['profilePhoto'],
          strpos($source['profilePhoto'],"ts=")+3);
        $photo_timestamp = intval($ts);
    }
    if (!empty($sunetid)) {
      $db = \Drupal::database();
      $result = $db->query("SELECT * FROM {migrate_info_earth_capx_importer} " .
        " WHERE sunetid = :sunetid", array(':sunetid'=>$sunetid));
      foreach ($result as $record) {
        $xyz = print_r($record,true);
      }
    }
  }
  
}
