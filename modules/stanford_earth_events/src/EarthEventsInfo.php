<?php

namespace Drupal\stanford_earth_events;

use Drupal\Core\Database;
use Drupal\migrate\MigrateMessage;
use Drupal\stanford_earth_events\EarthEventsLock;

//class StanfordEventsException extends \Drupal\Core\Entity\Exception {}

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
   * EARTH_EVENTS_INFO_TABLE = The table added by Drupal hook_schema.
   *
   * @var string
   */
  const EARTH_EVENTS_INFO_TABLE = 'migrate_info_earth_events_importer';

  private $guid;
  private $entityId;
  private $unlisted;
  private $orphaned;
  private $status;
  private $starttime;

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
    $this->starttime = 0;
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
        if (!empty($record->orphaned)) {
          $this->orphaned = boolval($record->orphaned);
        }
        if (!empty($record->starttime)) {
          $this->starttime = intval($record->starttime);
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
  public function getOkayToUpdateEventStatus(array $source = []) {
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
      // For now.
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
   * @param bool $orphaned
   *   Whether the event info record has been orphaned.
   */
  public function setInfoRecord(array $source = [],
                                $entity_id = 0,
                                $orphaned = FALSE) {
    // Function uses $status which was originally set in the constructor.
    // If the $status is 'invalid', post a message and return.
    // If the $status is 'new', create a new record.
    // If the $status is 'found' and nothing has changed, just return.
    // If the $status is 'found' and values have changed, delete and recreate.
    $msg = new MigrateMessage();
    if (empty($source['guid']) ||
      $this->status == self::EARTH_EVENTS_INFO_INVALID) {
      $msg->display(t('Unable to update EarthEventsInfo table. Missing event guid.'), 'error');
      return;
    }
    if ($source['guid'] !== $this->guid) {
      $msg->display(t('Unable to update EarthEventsInfo table. Mismatched ids: @guid1, @guid2',
        ['@guid1' => $source['guid'], '@guid2' => $this->guid]));
      return;
    }

    // Get the status field from the source array.
    $unlisted = FALSE;
    if (isset($source['status_code']) &&
      intval($source['status_code']) == 0) {
      $unlisted = TRUE;
    }

    $starttime = 0;
    if (isset($source['field_s_event_date'])) {
      $starttime = strtotime($source['field_s_event_date']);
    }

    // If existing in table, see if we need to update.
    if ($this->status == self::EARTH_EVENTS_INFO_FOUND) {
      if ($this->orphaned !== $orphaned ||
        $this->unlisted !== $unlisted ||
        $this->starttime !== $starttime ||
        $this->entityId != $entity_id) {
        // The information is different, so delete record and set status = NEW.
        \Drupal::database()->delete(self::EARTH_EVENTS_INFO_TABLE)
          ->condition('guid', $this->guid)
          ->execute();
        $this->status = self::EARTH_EVENTS_INFO_NEW;
      }
    }

    // Now we will insert only if record is truly new or has changed.
    if ($this->status == self::EARTH_EVENTS_INFO_NEW) {
      $this->unlisted = $unlisted;
      $this->orphaned = $orphaned;
      $this->entityId = $entity_id;
      $this->starttime = $starttime;
      try {
        \Drupal::database()->insert(self::EARTH_EVENTS_INFO_TABLE)
          ->fields([
            'guid' => $this->guid,
            'entity_id' => $this->entityId,
            'unlisted' => intval($this->unlisted),
            'orphaned' => intval($this->orphaned),
            'starttime' => strval($this->starttime),
          ])
          ->execute();
      }
      catch (Exception $e) {
        // Log the exception to watchdog.
        $m = new MigrateMessage();
        $m->display(t('Unable to insert new EarthEventsInfo record for @guid', ['guid' => $this->guid]));
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
   * Return true if this is an unlisted event.
   */
  public function isUnlisted() {
    return $this->unlisted;
  }

  /**
   * Return true if this is an orphaned event.
   */
  public function isOrphaned() {
    return $this->orphaned;
  }

  /**
   * Return true if this event is new or found, false if invalid.
   */
  public function isValid() {
    return (!empty($this->status));
  }

  /**
   * Return the entity id of the event or zero if there is none.
   */
  public function entityId() {
    return $this->entityId;
  }

  /**
   * Mark all future events as orphans, to be reset as updated from feeds.
   */
  public static function earthEventsMakeOrphans() {
    $db = \Drupal::database();
    $lock = new EarthEventsLock($db);
    $test = $lock->getLockId();
    if ($lock->acquire('EarthEventsLock', 900)) {
      $lockid = $lock->getLockId();
    }
    /** @var \Drupal\Core\Tempstore\PrivateTempStore $session */
    $session = \Drupal::service('tempstore.private')->get('EarthEventsInfo');
    $xyz = $session->get('mylockid');
    if ($xyz !== NULL) {
      $xy2 = 'found! '.$xyz;
    } else {
      $session->set('mylockid', $lockid);
    }
    $xyz = getmypid();
    //print 'make orphans -- user: ' . \Drupal::currentUser()->getAccountName() . ' pid: ' . getmypid() . chr(13). chr(10);\Drupsal
    \Drupal::database()
      ->update(EarthEventsInfo::EARTH_EVENTS_INFO_TABLE)
      ->fields([
        'orphaned' => 1,
      ])
      ->condition('starttime', REQUEST_TIME, '>')
      ->execute();
    //throw new \Exception("Test failure while making orphans");
  }

  /**
   * Deletes records from the Event Info table marked as orphans.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public static function earthEventsDeleteOrphans() {
    $session = \Drupal::service('tempstore.private')->get('EarthEventsInfo');
    $xyz = $session->get('mylockid');
    if ($xyz !== NULL) {
      /** @var \Drupal\Core\Lock\DatabaseLockBackend $lock */
      $lock = \Drupal::service('lock');
      $lockid = $lock->getLockId();
      if ($lockid === $xyz) {
        $xy2 = "we got it";
      }
    }

    $abc = getmypid();
    $xyz = $abc;
    //print 'delete orphans -- user: ' . \Drupal::currentUser()->getAccountName() . ' pid: ' . getmypid() . chr(13). chr(10);
    $orphaned_entities = [];
    $result = \Drupal::database()
      ->query("SELECT entity_id FROM {" .
        EarthEventsInfo::EARTH_EVENTS_INFO_TABLE . "} WHERE orphaned = 1");
    foreach ($result as $record) {
      $orphaned_entities[] = intval($record->entity_id);
    }
    if (!empty($orphaned_entities)) {
      $storage_handler = \Drupal::entityTypeManager()->getStorage('node');
      $entities = $storage_handler->loadMultiple($orphaned_entities);
      $storage_handler->delete($entities);
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
            'length' => 16,
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
          'starttime' => [
            'type' => 'varchar',
            'length' => 255,
            'not null' => FALSE,
            'description' => "Unix timestamp of the event start time",
          ],
        ],
        'primary key' => ['guid'],
      ],
    ];
  }

}
