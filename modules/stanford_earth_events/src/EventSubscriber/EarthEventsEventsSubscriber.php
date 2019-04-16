<?php

namespace Drupal\stanford_earth_events\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Drupal\migrate\Event\MigrateEvents;
use Drupal\migrate\Event\MigratePostRowSaveEvent;
use Drupal\migrate\Event\MigrateRowDeleteEvent;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Entity\EntityTypeManager;
use Drupal\stanford_earth_events\EarthEventsInfo;

/**
 * Class EntityTypeSubscriber.
 *
 * @package Drupal\stanford_earth_events\EventSubscriber
 */
class EarthEventsEventsSubscriber implements EventSubscriberInterface {

  use StringTranslationTrait;

  /**
   * Entity Type Manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManager
   */
  protected $entityTypeManager;

  /**
   * User object.
   *
   * @var \Drupal\user\UserStorageInterface
   */
  protected $user;

  /**
   * EarthEventsEventsSubscriber constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManager $entityTypeManager
   *   The EntityTypeManager service.
   */
  public function __construct(EntityTypeManager $entityTypeManager) {
    $this->entityTypeManager = $entityTypeManager;
    $this->user = $entityTypeManager->getStorage('user');
  }

  /**
   * {@inheritdoc}
   *
   * @return array
   *   The event names to listen for, and the methods that should be executed.
   */
  public static function getSubscribedEvents() {
    return [
      MigrateEvents::POST_ROW_SAVE => 'migratePostRowSave',
      MigrateEvents::POST_ROW_DELETE => 'migratePostRowDelete',
        MigrateEvents::POST_IMPORT => 'migratePostImport',
        MigrateEvents::IDMAP_MESSAGE => 'migrateIdmapMessage',
        MigrateEvents::MAP_DELETE => 'migrateMapDelete',
        MigrateEvents::MAP_SAVE => 'migrateMapSave',
        MigrateEvents::POST_ROLLBACK => 'migratePostRollback',
        MigrateEvents::PRE_IMPORT => 'migratePreImport',
        MigrateEvents::PRE_ROW_DELETE => 'migratePreDelete',
        MigrateEvents::PRE_ROLLBACK => 'migratePreRollback',
      MigrateEvents::PRE_ROW_SAVE => 'migratePreRowSave',
    ];
  }

  public function migrateIdmapMessage(\Drupal\migrate\Event\MigrateIdMapMessageEvent $event) {
    $xyz = 1;
  }

  public function migratePostImport(\Drupal\migrate\Event\MigrateImportEvent $event) {
    $xyz = 1;
  }

  public function migrateMapDelete(\Drupal\migrate\Event\MigrateMapDeleteEvent $event) {
    $xyz = 1;
  }

  public function migrateMapSave(\Drupal\migrate\Event\MigrateMapSaveEvent $event) {
    $xyz = 1;
  }

  public function migratePostRollback(\Drupal\migrate\Event\MigrateRollbackEvent $event) {
    $xyz = 1;
  }

  public function migratePreImport(\Drupal\migrate\Event\MigrateImportEvent $event) {
    $xyz = 1;
  }

  public function migratePreDelete(\Drupal\migrate\Event\MigrateRowDeleteEvent $event) {
    $xyz = 1;
  }

  public function migratePreRollback(\Drupal\migrate\Event\MigrateRollbackEvent $event) {
    $xyz = 1;
  }

  public function migratePreRowSave(\Drupal\migrate\Event\MigratePreRowSaveEvent $event) {
    $xyz = 1;
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

    // This event gets thrown for all migrations, so check that first.
    if (strpos($event->getMigration()->id(), 'earth_events_importer') !== 0) {
      return;
    }

    // Save Event guid and entity id and whether the event is unlisted.
    $row = $event->getRow();
    $guid = $row->getSourceProperty('guid');
    if (empty($guid)) {
      return;
    }

    $destination = 0;
    $destination_ids = $event->getDestinationIdValues();
    if (!empty($destination_ids[0])) {
      $destination = intval($destination_ids[0]);
    }

    $info = new EarthEventsInfo($row->getSourceProperty('guid'));
    if ($info->getOkayToUpdateEventStatus($row->getSource())) {
      $info->setInfoRecord($row->getSource(), $destination, empty($destination));
    }
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

    // This event gets thrown for all migrations, so check that first.
    if (strpos($event->getMigration()->id(), 'earth_events_importer') !== 0) {
      return;
    }

    $destination_ids = $event->getDestinationIdValues();
    $destination = 0;
    if (!empty($destination_ids['uid'])) {
      $destination = intval($destination_ids['uid']);
    }
    EarthEventsInfo::delete($destination);

  }

}
