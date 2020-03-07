<?php

namespace Drupal\stanford_earth_events;

use Drupal\migrate_plus\Entity\Migration;
use Drupal\migrate\MigrateMessage;
use Drupal\stanford_earth_migrate_extend\EarthMigrationLock;
use Drupal\node\Entity\Node;
use Drupal\media\Entity\Media;

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

  /**
   * Class guid properties.
   *
   * @var string
   */
  private $guid;

  /**
   * Class entityId property.
   *
   * @var int
   */
  private $entityId;

  /**
   * Class unlisted property.
   *
   * @var bool
   */
  private $unlisted;

  /**
   * Class orphaned property.
   *
   * @var bool
   */
  private $orphaned;

  /**
   * Class status property.
   *
   * @var int
   */
  private $status;

  /**
   * Class starttime property.
   *
   * @var int
   */
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
      $msg->display('Unable to validate new event information.', 'error');
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
      $msg->display('Unable to update EarthEventsInfo table. Missing event guid.', 'error');
      return;
    }
    if ($source['guid'] !== $this->guid) {
      $msg->display('Unable to update EarthEventsInfo table. Mismatched ids: @guid1, @guid2',
        ['@guid1' => $source['guid'], '@guid2' => $this->guid]);
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
        $m->display('Unable to insert new EarthEventsInfo record for @guid', ['guid' => $this->guid]);
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
    // We need to access the database.
    $db = \Drupal::database();
    // Only make orphans if we can first acquire a lock.
    $lock = new EarthMigrationLock($db);
    if ($lock->acquireLock(900)) {
      // We have a lock, so store the lock id in our session.
      /** @var \Drupal\Core\Tempstore\PrivateTempStore $session */
      $session = \Drupal::service('tempstore.private')->get($lock::EARTH_MIGRATION_LOCK_NAME);
      $session->set($lock::EARTH_MIGRATION_LOCK_NAME, $lock->getLockId());

      // Now let's make sure any non-IDLE migrations are reset.
      $eMigrations = \Drupal::service("config.factory")
        ->listAll('migrate_plus.migration.earth_events_importer');
      foreach ($eMigrations as $eMigration) {
        // Ignore this preprocess migration.
        if (strpos($eMigration, "preprocess") === FALSE) {
          $migration = Migration::load(substr($eMigration, strpos($eMigration,
            'earth_events')));
          /** @var \Drupal\migrate\Plugin\MigrationInterface $migration_plugin */
          $migration_plugin = \Drupal::service('plugin.manager.migration')
            ->createInstance($migration->id(), $migration->toArray());
          if ($migration_plugin->getStatus() !== $migration_plugin::STATUS_IDLE) {
            $migration_plugin->setStatus($migration_plugin::STATUS_IDLE);
          }
        }
      }

      // Then set the records for future events in the Info table to orphaned.
      // They will be reset one-by-one as the events are updated.
      // At the end, any still flagged as orphaned will be removed.
      $db->update(EarthEventsInfo::EARTH_EVENTS_INFO_TABLE)
        ->fields([
          'orphaned' => 1,
        ])
        ->condition('starttime', REQUEST_TIME, '>')
        ->execute();
    }
    // We will release the lock after deleting orphans.
  }

  /**
   * Find events repeated daily and limits them to once per week.
   */
  public static function earthEventsOrphanRepeaters() {
    // We need to access the database.
    $db = \Drupal::database();

    $update = [];
    $result = $db->query("SELECT guid, entity_id, " .
      "DATE_FORMAT(FROM_UNIXTIME(starttime), '%W %M %e, %Y') AS eventdate, " .
      "DAYOFWEEK(FROM_UNIXTIME(starttime)) AS eventday " .
      "FROM migrate_info_earth_events_importer WHERE " .
      "starttime > UNIX_TIMESTAMP() OR DATE(FROM_UNIXTIME(starttime)) = " .
      "CURDATE() ORDER BY starttime");
    foreach ($result as $record) {
      $guid = substr($record->guid, 0, strpos($record->guid, '-'));
      $eventday = intval($record->eventday);
      if ($eventday == 1) {
        $eventday = 8;
      }
      if (!array_key_exists($guid, $update)) {
        $update[$guid] = [
          'entity_id' => $record->entity_id,
          'start' => $record->eventdate,
          'end' => $record->eventdate,
          'end_day' => $eventday,
          'multi' => FALSE,
        ];
      }
      else {
        $diff = intval(date_diff(date_create($update[$guid]['end']),
          date_create($record->eventdate))->format("%a")) +
          $update[$guid]['end_day'];
        if ($diff < 9) {
          $db->update(EarthEventsInfo::EARTH_EVENTS_INFO_TABLE)
            ->fields([
              'orphaned' => 1,
            ])
            ->condition('entity_id', $record->entity_id, '=')
            ->execute();
          $update[$guid]['multi'] = TRUE;
        }
        $update[$guid]['end'] = $record->eventdate;
        $update[$guid]['end_day'] = $eventday;
      }
    }
    foreach ($update as $guid => $event) {
      if ($event['multi']) {
        $node_entities = [];
        $result = $db->query("SELECT entity_id " .
          "FROM migrate_info_earth_events_importer WHERE " .
          "orphaned = 0 AND guid LIKE '" . $guid . "%'");
        foreach ($result as $record) {
          $node_entities[] = intval($record->entity_id);
        }
        $storage_handler = \Drupal::entityTypeManager()->getStorage('node');
        $entities = $storage_handler->loadMultiple($node_entities);
        foreach ($entities as $node) {
          $when_field = reset($node->get('field_s_event_when')->getValue());
          $when = '';
          if ($when_field && isset($when_field['value'])) {
            $when = $when_field['value'];
          }
          $when .= ' Repeats through ' . $event['end'];
          $node->set('field_s_event_when', $when);
          $node->save();
        }
      }
    }
  }

  /**
   * Deletes records from the Event Info table marked as orphans.
   */
  public static function earthEventsDeleteOrphans() {
    // We need to access the database.
    $db = \Drupal::database();
    // Instantiate the lock object.
    $lock = new EarthMigrationLock($db);
    // See if we have a lock by looking for a lockid stored in our session.
    /** @var \Drupal\Core\TempStore\PrivateTempStore $session */
    $session = \Drupal::service('tempstore.private')->get($lock::EARTH_MIGRATION_LOCK_NAME);
    $mylockid = $session->get($lock::EARTH_MIGRATION_LOCK_NAME);
    if (!empty($mylockid)) {
      // See if there is a lock in the semaphore table that matches our id.
      $actual = $lock->getExistingLockId();
      // If they match, check that the lock hasn't timed out.
      if (!empty($actual) && $actual === $mylockid && $lock->valid()) {
        // Limit repeated events to once per week.
        self::earthEventsOrphanRepeaters();
        // Delete orphaned event nodes.
        $orphaned_entities = [];
        $result = $db->query("SELECT entity_id FROM {" .
            EarthEventsInfo::EARTH_EVENTS_INFO_TABLE . "} WHERE orphaned = 1");
        foreach ($result as $record) {
          $orphaned_entities[] = intval($record->entity_id);
        }
        if (!empty($orphaned_entities)) {
          $storage_handler = \Drupal::entityTypeManager()->getStorage('node');
          $entities = $storage_handler->loadMultiple($orphaned_entities);
          $storage_handler->delete($entities);
        }

        // Release the lock.
        $lock->releaseEarthMigrationLock($mylockid);
      }

    }
  }

  /**
   * Return the default media entity for event nodes.
   *
   * @return int|string|null
   *   Entity id or null.
   */
  public static function getDefaultEventMediaEntity(Node $node = NULL) {
    if (empty($node)) {
      $fields = \Drupal::service('entity_field.manager')
        ->getFieldDefinitions('node', 'stanford_event');
      $media_field_def = $fields['field_s_event_media'];
      $found_mid = $media_field_def->getDefaultValueLiteral();
    }
    else {
      $media_field_def = $node->getFieldDefinition('field_s_event_media');
      $found_mid = $media_field_def->getDefaultValue($node);
    }
    $default_mid = NULL;
    if (!empty($found_mid) and is_array($found_mid)) {
      $media_entity = NULL;
      if (!empty($found_mid[0]['target_id'])) {
        $media_entity = Media::load($found_mid[0]['target_id']);
      }
      elseif (!empty($found_mid[0]['target_uuid'])) {
        $media_entity = \Drupal::service('entity.repository')
          ->loadEntityByUuid('media', $found_mid[0]['target_uuid']);
      }
      if (!empty($media_entity)) {
        $default_mid = $media_entity->id();
      }
    }
    return $default_mid;
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

  /**
   * Deletes images from the files/stanford-event folder if unused.
   *
   * @param array $nids
   *   List of nids to load and update.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public static function deleteUnusedImages(array $nids = []) {

    set_time_limit(0);
    foreach ($nids as $nid) {
      $node = Node::load($nid);
      $depts = NULL;
      if ($node->getType() === 'stanford_news') {
        $depts = $node->get('field_s_news_department')->getValue();
      }
      else {
        if ($node->getType() === 'stanford_spotlight') {
          $depts = $node->get('field_s_spotlight_department_tag')->getValue();
        }
      }
      $found = FALSE;
      if (empty($depts)) {
        $depts = [];
      }
      foreach ($depts as $tid) {
        if (!empty($tid) && is_array($tid)) {
          if (!empty($tid['target_id'] && $tid['target_id'] == '1311')) {
            $found = TRUE;
          }
        }
      }
      if (!$found) {
        $depts[] = ['target_id' => '1311'];
        if ($node->getType() === 'stanford_news') {
          $node->field_s_news_department = $depts;
        }
        else {
          if ($node->getType() === 'stanford_spotlight') {
            $node->field_s_spotlight_department_tag = $depts;
          }
        }
        $node->save();
      }
    }
  }

}
