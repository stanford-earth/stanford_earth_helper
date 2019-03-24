<?php

namespace Drupal\stanford_earth_events;

use Drupal\Core\Database;
use Drupal\migrate\MigrateMessage;

/**
 * Encapsulates an information table for Earth Events imports.
 */
class EarthEventsInfo {

  /**
   * Status constants for the profile information.
   *
   * EARTH_EVENTS_INFO_INVALID = An event with no GUID or entityID is requested.
   * EARTH_EVENTS_INFO_NEW = A new event record will be created.
   * EARTH_EVENTS_INFO_FOUND = An existing event record will be updated.
   *
   * @var int
   */
  const EARTH_EVENTS_INFO_INVALID = 0;
  const EARTH_EVENTS_INFO_NEW = 1;
  const EARTH_EVENTS_INFO_FOUND = 2;

  /**
   * Table name constant for info table.
   *
   * EARTH_CAPX_INFO_TABLE = The table added by Drupal hook_schema.
   *
   * @var string
   */
  const EARTH_EVENTS_INFO_TABLE = 'migrate_info_earth_events_importer';

  private $guid;
  private $entityId;
  private $unlisted;
  private $orphaned;
  private $status;

  /**
   * Construct with $guid param; if already exists, populate other properties.
   *
   * @param string $guid
   *   Event GUID.
   */
  public function __construct(string $guid = NULL) {
    // $status will get set here depending on whether event is valid and
    // whether it is new or already exists in the Drupal system.
    $this->guid = "";
    $this->status = self::EARTH_EVENTS_INFO_INVALID;
    $this->entityId = 0;
    $this->unlisted = FALSE;
    $this->orphaned = FALSE;
    if (!empty($guid)) {
      $this->status = self::EARTH_EVENTS_INFO_NEW;
      $this->guid = $guid;
      $db = \Drupal::database();
      $result = $db->query("SELECT * FROM {" . self::EARTH_EVENTS_INFO_TABLE .
        "} WHERE guid = :guid", [':guid' => $this->guid]);
      foreach ($result as $record) {
        $this->status = self::EARTH_EVENTS_INFO_FOUND;
        if (!empty($record->entity_id)) {
          $this->entityId = intval($record->entity_id);
        }
        if (!empty($record->unlisted)) {
          $this->unlisted = boolval($record->unlisted);
        }
      }
    }
  }

  /**
   * Check if we should update the event.
   *
   * Return true if event should be updated because it is new
   * or because something has changed.
   *
   * @param array $source
   *   The source array from the migration row.
   */
  public function getOkayToUpdateEvent(array $source = []) {
    // Checks $status which was set in the constructor.
    $oktoupdate = FALSE;
    $msg = new MigrateMessage();
    if (empty($source['guid']) ||
      $this->status == self::EARTH_EVENTS_INFO_INVALID ||
      $source['guid'] !== $this->guid) {
      $msg->display(t('Unable to validate new event information.'), 'error');
    }
    elseif ($this->status == self::EARTH_EVENTS_INFO_NEW) {
      $oktoupdate = TRUE;
    }
    elseif ($this->status == self::EARTH_EVENTS_INFO_FOUND) {
      $oktoupdate = TRUE;
    }
    return $oktoupdate;
  }

  /**
   * Update the table with information about the event.
   *
   * Update the table with information from the source array and destination id
   * only do the operation if information has changed.
   *
   * @param array $source
   *   Source data from migration row.
   * @param int $entity_id
   *   Entity ID of the event.
   */
  public function setInfoRecord(array $source = [],
                                $entity_id = 0,
                                $photo_id = 0,
                                $wg = NULL) {
    // Function uses $status which was originally set in the constructor.
    // If the $status is 'invalid', post a message and return.
    // If the $status is 'new', create a new record.
    // If the $status is 'found' and nothing has changed, just return.
    // If the $status is 'found' and values have changed, delete and recreate.
    $msg = new MigrateMessage();
    if (empty($source['sunetid']) ||
      $this->status == self::EARTH_CAPX_INFO_INVALID) {
      $msg->display(t('Unable to update EarthCapxInfo table. Missing source id.'), 'error');
      return;
    }
    if ($source['sunetid'] !== $this->sunetid) {
      $msg->display(t('Unable to update EarthCapxInfo table. Mismatched ids: @sunet1, @sunet2',
        ['@sunet1' => $source['sunetid'], '@sunet2' => $this->sunetid]));
      return;
    }

    // Get the fields from the source array.
    $source_etag = '';
    if (!empty($source['etag'])) {
      $source_etag = $source['etag'];
    }

    $source_ts = '';
    if (!empty($source['profile_photo'])) {
      $source_ts = $this->getPhotoTimestamp($source['profile_photo']);
    }

    $photo_id = intval($photo_id);

    // Add the workgroup if not null and not already in array.
    $wg_changed = FALSE;
    $wgs = $this->workgroups;
    if (!empty($wg) && !in_array($wg, $wgs)) {
      $wgs[] = $wg;
      $wg_changed = TRUE;
    }

    // If existing in table, see if we need to update.
    if ($this->status == self::EARTH_CAPX_INFO_FOUND) {
      if ($this->etag !== $source_etag ||
        $this->profilePhotoTimestamp !== $source_ts ||
        $this->profilePhotoFid !== $photo_id ||
        $this->entityId !== $entity_id ||
        $wg_changed) {
        // The information is different, so delete record and set status = NEW.
        \Drupal::database()->delete(self::EARTH_CAPX_INFO_TABLE)
          ->condition('sunetid', $this->sunetid)
          ->execute();
        $this->status = self::EARTH_CAPX_INFO_NEW;
      }
    }

    // Now we will insert only if record is truly new or has changed.
    if ($this->status == self::EARTH_CAPX_INFO_NEW) {
      $this->etag = $source_etag;
      $this->profilePhotoTimestamp = $source_ts;
      $this->profilePhotoFid = $photo_id;
      $this->entityId = $entity_id;
      $this->workgroups = $wgs;
      try {
        \Drupal::database()->insert(self::EARTH_CAPX_INFO_TABLE)
          ->fields([
            'sunetid' => $this->sunetid,
            'etag' => $this->etag,
            'photo_timestamp' => $this->profilePhotoTimestamp,
            'entity_id' => $this->entityId,
            'profile_photo_id' => $this->profilePhotoFid,
            'workgroup_list' => serialize($this->workgroups),
          ])
          ->execute();
      }
      catch (Exception $e) {
        // Log the exception to watchdog.
        $m = new MigrateMessage();
        $m->display(t('Unable to insert new EarthCapxInfo record for @sunet', ['sunet' => $this->sunetid]));
        \Drupal::logger('type')->error($e->getMessage());
      }
    }
  }

  /**
   * Delete a record from the table by entity_id.
   *
   * @param string $entity_id
   *   Entity ID of event to be deleted.
   */
  public static function delete($entity_id = 0) {
    if ($entity_id > 0) {
      $db = \Drupal::database();
      if ($db->schema()->tableExists(self::EARTH_EVENTS_INFO_TABLE)) {
        $db->delete(self::EARTH_EVENTS_INFO_TABLE)
          ->condition('entity_id', $entity_id)
          ->execute();
      }
    }
  }

  /**
   * Return true if this is a new event import.
   */
  public function isNew() {
    if ($this->status == self::EARTH_EVENTS_INFO_NEW) {
      return TRUE;
    }
    else {
      return FALSE;
    }
  }

  /**
   * The schema for this table to be retrieved by the module hook_schema call.
   */
  public static function getSchema() {
    return [
      self::EARTH_EVENTS_INFO_TABLE => [
        'description' => "Stanford Events Import Information",
        'fields' => [
          'guid' => [
            'type' => 'varchar',
            'length' => 255,
            'not null' => TRUE,
            'description' => "GUID for imported event",
          ],
          'entity_id' => [
            'type' => 'int',
            'not null' => FALSE,
            'description' => "Entity id to which the profile was imported",
          ],
          'unlisted' => [
            'type' => 'int',
            'not null' => FALSE,
            'description' => "If the event is unlisted, canceling keeps it so",
          ],
          'orphaned' => [
            'type' => 'int',
            'not null' => FALSE,
            'description' => "Field used during imports to unpublish orphaned events",
          ],
        ],
        'primary key' => ['guid'],
      ],
    ];
  }

}
