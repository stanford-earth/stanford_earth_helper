<?php

namespace Drupal\stanford_earth_capx\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Drupal\migrate\MigrateException;
use Drupal\migrate\Event\MigrateEvents;
use Drupal\migrate\Event\MigratePreRowSaveEvent;
use Drupal\migrate\Event\MigratePostRowSaveEvent;
use Drupal\migrate\Event\MigrateRowDeleteEvent;
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
   *
   * Decide if we really need to re-import a profile.
   *
   * @param \Drupal\migrate\Event\MigratePreRowSaveEvent $event
   *   Information about the migration source row being processed.
   */
  public function migratePreRowSave(MigratePreRowSaveEvent $event) {

    // Get the row in question.
    $row = $event->getRow();
    // See if we already have migration information for this profile.
    $info = new EarthCapxInfo($row->getSourceProperty('sunetid'));
    $photo_id = 0;
    $photo_field = $row->getDestinationProperty('field_s_person_image');
    if (!empty($photo_field['target_id'])) {
      $photo_id = $photo_field['target_id'];
    }
    // Check source data in the row against etag and photo info stored in table.
    $okay = $info->getOkayToUpdateProfile($row->getSource(), $photo_id);

    // Throw an exception if not okay to skip this record.
    if (!$okay) {
      throw new MigrateException(NULL, 0, NULL, 3, 2);
    }
  }

  /**
   * React to a migrate POST_ROW_SAVE event.
   *
   * Save information that we will need to determine whether to reimport.
   *
   * @param \Drupal\migrate\Event\MigratePostRowSaveEvent $event
   *   Contains information about the migration source row being saved.
   */
  public function migratePostRowSave(MigratePostRowSaveEvent $event) {
    // Save CAP API etag and other information so we don't later re-import
    // a profile that has not changed.
    $source = $event->getRow()->getSource();
    $destination = 0;
    $destination_ids = $event->getDestinationIdValues();
    if (!empty($destination_ids[0])) {
      $destination = intval($destination_ids[0]);
    }

    // Get the fid of the profile photo.
    $photoId = 0;
    $dest_values = $event->getRow()->getDestinationProperty('field_s_person_image');
    if (!empty($dest_values['target_id'])) {
      $photoId = intval($dest_values['target_id']);
    }
    $info = new EarthCapxInfo((!empty($source['sunetid']) ? $source['sunetid'] : ''));
    $info->setInfoRecord($source, $destination, $photoId);
  }

  /**
   * React to a migrate POST_ROW_DELETE event.
   *
   * If a rollback removes a profile, we want to delete it from our info table.
   *
   * @param \Drupal\migrate\Event\MigrateRowDeleteEvent $event
   *   $event Contains information on which profile by user id is deleted.
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
