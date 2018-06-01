<?php

namespace Drupal\stanford_earth_capx\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Drupal\migrate\MigrateException;
use Drupal\migrate\Event\MigrateEvents;
use Drupal\migrate\Event\MigratePreRowSaveEvent;
use Drupal\migrate\Event\MigratePostRowSaveEvent;
use Drupal\migrate\Event\MigrateRowDeleteEvent;
use Drupal\migrate\Row;
use Drupal\stanford_earth_capx\EarthCapxInfo;

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
      MigrateEvents::PRE_ROW_SAVE => 'migratePreRowSave',
      MigrateEvents::POST_ROW_SAVE => 'migratePostRowSave',
      MigrateEvents::POST_ROW_DELETE => 'migratePostRowDelete',
    ];
  }

  /**
   * React to a migrate PRE_ROW_SAVE event.
   * and decide if we really need to re-import a profile
   *
   * @param \Drupal\migrate\Event\MigratePreRowSaveEvent $event
   *
   */
  public function migratePreRowSave(MigratePreRowSaveEvent $event) {

    // get the row in question
    $row = $event->getRow();
    // see if we already have migration information for this profile
    $info = new EarthCapxInfo($row->getSourceProperty('sunetid'));
    // only proceed if the profile has changed (by checking etag & photo timestamp
    $okay =  $info->getOkayToUpdateProfile($row->getSource());

    // throw an exception if not okay to skip this record
    if (!$okay) {
      throw new MigrateException(NULL, 0, NULL, 3, 2);
    }
  }

  /**
   * React to a migrate POST_ROW_SAVE event.
   * Save information that we will need to determine whether to reimport.
   *
   * @param \Drupal\migrate\Event\MigratePostRowSaveEvent $event
   *
   */
  public function migratePostRowSave(MigratePostRowSaveEvent $event) {
    // save CAP API etag and other information so we don't later re-import
    // a profile that has not changed.
    $source = $event->getRow()->getSource();
    $destination = 0;
    $destination_ids = $event->getDestinationIdValues();
    if (!empty($destination_ids[0])) {
      $destination = intval($destination_ids[0]);
    }
    $info = new EarthCapxInfo((!empty($source['sunetid']) ? $source['sunetid'] : ''));
    $info->setInfoRecord($source,$destination);
  }

  /**
   * React to a migrate POST_ROW_DELETE event.
   * If a rollback removes a profile, we want to delete it from our info table.
   *
   * @param \Drupal\migrate\Event\MigratePostRowDeleteEvent $event
   *
   */
  public function migratePostRowDelete(MigrateRowDeleteEvent $event) {
    $destination_ids = $event->getDestinationIdValues();
    $destination = 0;
    if (!empty($destination_ids['uid'])) {
      $destination = intval($destination_ids['uid']);
    }
    EarthCapxInfo::delete($destination);
  }
  
}
