<?php

namespace Drupal\stanford_earth_capx;

use Drupal\migrate\MigrateMessage;
use Drupal\user\Entity\User;
use Drupal\media\Entity\Media;
use Drupal\file\Entity\File;
use Drupal\migrate_plus\Entity\Migration;
use Drupal\Core\Batch\BatchBuilder;
use Drupal\stanford_earth_migrate_extend\EarthMigrationLock;

/**
 * Encapsulates an information table for CAP-X Profile imports.
 */
class EarthCapxInfo {

  /**
   * Status constants for the profile information.
   *
   * EARTH_CAPX_INFO_INVALID = A profile was presented with no SUNet ID/uid.
   * EARTH_CAPX_INFO_NEW = A new profile/account will be created.
   * EARTH_CAPX_INFO_FOUND = An existing profile/account will be updated.
   *
   * @var int
   */
  const EARTH_CAPX_INFO_INVALID = 0;
  const EARTH_CAPX_INFO_NEW = 1;
  const EARTH_CAPX_INFO_FOUND = 2;

  /**
   * Table name constant for info table.
   *
   * EARTH_CAPX_INFO_TABLE = The table added by Drupal hook_schema.
   *
   * @var string
   */
  const EARTH_CAPX_INFO_TABLE = 'migrate_info_earth_capx_importer';

  /**
   * Query string for checking if a user's workgroup assignments changed.
   *
   * EARTH_CAPX_WG_QUERY = The query string for a particular sunetid.
   *
   * @var string
   */
  const EARTH_CAPX_WG_QUERY = "SELECT IF(COUNT(*)=0,'same','different') " .
    "FROM ( SELECT sunetid, wg_tag FROM migrate_info_earth_capx_wgs " .
    "WHERE sunetid = :sunetid AND ( sunetid, wg_tag ) NOT IN " .
    "( SELECT sunetid, wg_tag FROM migrate_info_earth_capx_wgs_temp " .
    "WHERE sunetid = :sunetid) UNION " .
    "SELECT sunetid, wg_tag FROM migrate_info_earth_capx_wgs_temp " .
    "WHERE sunetid = :sunetid AND ( sunetid, wg_tag ) NOT IN " .
    "( SELECT sunetid, wg_tag FROM migrate_info_earth_capx_wgs " .
    "WHERE sunetid = :sunetid )) minusintersec";

  /**
   * Sunetid of user.
   *
   * @var string
   */
  private $sunetid;

  /**
   * Etag from CAP API.
   *
   * @var string
   */
  private $etag;

  /**
   * Photo timestamp from url of profile photo.
   *
   * @var string
   */
  private $profilePhotoTimestamp;

  /**
   * Entity id (user account) of profile.
   *
   * @var int
   */
  private $entityId;

  /**
   * File id of profile photo for profile.
   *
   * @var int
   */
  private $profilePhotoFid;

  /**
   * Status of profile info record.
   *
   * @var int
   */
  private $status;

  /**
   * Construct with sunetid param; if already exists, populate other properties.
   *
   * @param string $su_id
   *   SUNet ID.
   */
  public function __construct(string $su_id = NULL) {
    // $status will get set here depending on whether profile is valid and
    // whether it is new or already exists in the Drupal system.
    $this->sunetid = "";
    $this->status = self::EARTH_CAPX_INFO_INVALID;
    $this->etag = "";
    $this->profilePhotoTimestamp = "";
    $this->entityId = 0;
    $this->profilePhotoFid = 0;
    if (!empty($su_id)) {
      $this->status = self::EARTH_CAPX_INFO_NEW;
      $this->sunetid = $su_id;
      $db = \Drupal::database();
      $result = $db->query("SELECT * FROM {" . self::EARTH_CAPX_INFO_TABLE .
        "} WHERE sunetid = :sunetid", [':sunetid' => $this->sunetid]);
      foreach ($result as $record) {
        $this->status = self::EARTH_CAPX_INFO_FOUND;
        if (!empty($record->etag)) {
          $this->etag = $record->etag;
        }
        if (!empty($record->photo_timestamp)) {
          $this->profilePhotoTimestamp = $record->photo_timestamp;
        }
        if (!empty($record->entity_id)) {
          $this->entityId = intval($record->entity_id);
        }
        if (!empty($record->profile_photo_id)) {
          $this->profilePhotoFid = intval($record->profile_photo_id);
        }
      }
    }
  }

  /**
   * Get the photo timestamp from the cap url.
   */
  private function getPhotoTimestamp($photoUrl = "") {
    $photo_ts = '';
    if (!empty($photoUrl) && is_string($photoUrl)) {
      $ts1 = strpos($photoUrl, "ts=");
      if ($ts1 !== FALSE) {
        $ts2 = substr($photoUrl, $ts1);
        if (strpos($ts2, "&") !== FALSE) {
          $photo_ts = substr($ts2, 3, strpos($ts2, "&") - 3);
        }
        else {
          $photo_ts = substr($ts2, 3);
        }
      }
    }
    return $photo_ts;
  }

  /**
   * Get fid for photo_id.
   *
   * See if we have an existing and current profile photo_id
   * if we do, return its fid. if we don't, return false.
   *
   * @param array $source
   *   The source row array from the migration source.
   */
  public function currentProfilePhotoId(array $source = []) {

    if (empty($this->profilePhotoFid)) {
      return FALSE;
    }
    else {
      if (empty($source['profile_photo']) ||
          empty($this->profilePhotoTimestamp) ||
          empty($this->getPhotoTimestamp($source['profile_photo'])) ||
          $this->profilePhotoTimestamp !==
          $this->getPhotoTimestamp($source['profile_photo'])) {
        $file = File::load($this->profilePhotoFid);
        $file->delete();
        return FALSE;
      }
      else {
        return $this->profilePhotoFid;
      }
    }
  }

  /**
   * Check if we should update the profile.
   *
   * Return true if profile should be updated because it is new
   * or because the etag has changed.
   *
   * @param array $source
   *   The source array from the migration row.
   * @param int $photoId
   *   The photoId from the image url in the profile data.
   */
  public function getOkayToUpdateProfile(array $source = [],
                                         int $photoId = NULL) {
    // Checks $status which was set in the constructor.
    $oktoupdate = FALSE;
    $msg = new MigrateMessage();
    if (empty($source['sunetid']) ||
      $this->status == self::EARTH_CAPX_INFO_INVALID ||
      $source['sunetid'] !== $this->sunetid) {
      $msg->display('Unable to validate new profile information.', 'error');
    }
    elseif ($this->status == self::EARTH_CAPX_INFO_NEW) {
      $oktoupdate = TRUE;
    }
    elseif ($this->status == self::EARTH_CAPX_INFO_FOUND) {
      $source_etag = '';
      if (!empty($source['etag'])) {
        $source_etag = $source['etag'];
      }
      if ($this->etag !== $source_etag || $this->profilePhotoFid !== $photoId) {
        $oktoupdate = TRUE;
      }
      if (!$oktoupdate && empty($this->entityId)) {
        $oktoupdate = TRUE;
      }
      if (!$oktoupdate && !empty($source['updateemail'])) {
        $oktoupdate = TRUE;
      }
    }
    return $oktoupdate;
  }

  /**
   * Update the table with information about the profile.
   *
   * Update the table with information from the source array and destination id
   * only do the operation if information has changed.
   *
   * @param array $source
   *   Source data from migration row.
   * @param int $entity_id
   *   Entity ID of the profile.
   * @param int $photo_id
   *   Photo id number from profile photo URL.
   */
  public function setInfoRecord(array $source = [],
                                $entity_id = 0,
                                $photo_id = 0) {
    // Function uses $status which was originally set in the constructor.
    // If the $status is 'invalid', post a message and return.
    // If the $status is 'new', create a new record.
    // If the $status is 'found' and nothing has changed, just return.
    // If the $status is 'found' and values have changed, delete and recreate.
    $msg = new MigrateMessage();
    if (empty($source['sunetid']) ||
      $this->status == self::EARTH_CAPX_INFO_INVALID) {
      $msg->display('Unable to update EarthCapxInfo table. Missing source id.', 'error');
      return;
    }
    if ($source['sunetid'] !== $this->sunetid) {
      $msg->display('Unable to update EarthCapxInfo table. Mismatched ids: @sunet1, @sunet2',
        ['@sunet1' => $source['sunetid'], '@sunet2' => $this->sunetid]);
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

    // If existing in table, see if we need to update.
    if ($this->status == self::EARTH_CAPX_INFO_FOUND) {
      if ($this->etag !== $source_etag ||
        $this->profilePhotoTimestamp !== $source_ts ||
        $this->profilePhotoFid !== $photo_id ||
        $this->entityId !== $entity_id) {
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
      try {
        \Drupal::database()->insert(self::EARTH_CAPX_INFO_TABLE)
          ->fields([
            'sunetid' => $this->sunetid,
            'etag' => $this->etag,
            'photo_timestamp' => $this->profilePhotoTimestamp,
            'entity_id' => $this->entityId,
            'profile_photo_id' => $this->profilePhotoFid,
          ])
          ->execute();
      }
      catch (Exception $e) {
        // Log the exception to watchdog.
        $m = new MigrateMessage();
        $m->display('Unable to insert new EarthCapxInfo record for @sunet', ['sunet' => $this->sunetid]);
        \Drupal::logger('type')->error($e->getMessage());
      }
    }
  }

  /**
   * Delete a record from the table by entity_id.
   *
   * @param string $entity_id
   *   Entity ID of profile to be deleted.
   */
  public static function delete($entity_id = 0) {
    if ($entity_id > 0) {
      \Drupal::database()->delete(self::EARTH_CAPX_INFO_TABLE)
        ->condition('entity_id', $entity_id)
        ->execute();
    }
  }

  /**
   * Get the sunetid given an entity_id.
   *
   * @param string $entity_id
   *   Entity ID of profile for which to get SUNet ID.
   */
  public static function getSunetid($entity_id = 0) {
    $sunetid = '';
    if ($entity_id > 0) {
      $db = \Drupal::database();
      $result = $db->query("SELECT sunetid FROM {" . self::EARTH_CAPX_INFO_TABLE .
          "} WHERE entity_id = :entity_id", [':entity_id' => $entity_id]);
      foreach ($result as $record) {
        if (!empty($record->sunetid)) {
          $sunetid = $record->sunetid;
        }
        break;
      }
    }
    return $sunetid;
  }

  /**
   * Return true if this is a new profile import.
   */
  public function isNew() {
    if ($this->status == self::EARTH_CAPX_INFO_NEW) {
      return TRUE;
    }
    else {
      return FALSE;
    }
  }

  /**
   * Return entity_id (uid) of current record.
   */
  public function getEntityId() {
    return $this->entityId;
  }

  /**
   * Get the photo timestamp from the cap url.
   */
  public static function getProfileImageTimestamp($photoUrl = NULL) {
    $photo_ts = '';
    if (!empty($photoUrl) && is_string($photoUrl)) {
      $ts1 = strpos($photoUrl, "ts=");
      if ($ts1 !== FALSE) {
        $ts2 = substr($photoUrl, $ts1);
        if (strpos($ts2, "&") !== FALSE) {
          $photo_ts = substr($ts2, 3, strpos($ts2, "&") - 3);
        }
        else {
          $photo_ts = substr($ts2, 3);
        }
        $photo_ts = '_' . $photo_ts;
      }
    }
    return $photo_ts;
  }

  /**
   * Return the default media entity for profiles/user accounts.
   *
   * @return int|string|null
   *   Entity id or null.
   */
  public static function getDefaultProfileMediaEntity() {
    $account = User::load(0);
    $media_field_def = $account->getFieldDefinition('field_s_person_media');
    $found_mid = $media_field_def->getDefaultValue($account);
    $default_mid = NULL;
    if (!empty($found_mid) and is_array($found_mid)) {
      if (!empty($found_mid[0]['target_id'])) {
        $media_entity = Media::load($found_mid[0]['target_id']);
        if (!empty($media_entity)) {
          $default_mid = $media_entity->id();
        }
      }
    }
    return $default_mid;
  }

  /**
   * Prepare workgroups temp table for profile imports.
   */
  public static function earthCapxPreImport() {
    // We need to access the database.
    $db = \Drupal::database();
    // Only update search tags if we can first acquire a lock.
    $lock = new EarthMigrationLock($db, 'EarthCapxLock');
    if ($lock->acquireLock(900)) {
      // We have a lock, so store the lock id in our session.
      /** @var \Drupal\Core\Tempstore\PrivateTempStore $session */
      $session = \Drupal::service('tempstore.private')->get('EarthCapxLock');
      $session->set('EarthCapxLock', $lock->getLockId());

      // Now let's make sure any non-IDLE migrations are reset.
      $eMigrations = \Drupal::service("config.factory")
        ->listAll('migrate_plus.migration.earth_capx_importer');
      foreach ($eMigrations as $eMigration) {
        // Ignore this preprocess migration.
        if (strpos($eMigration, "preprocess") === FALSE) {
          $migration = Migration::load(substr($eMigration, strpos($eMigration,
            'earth_capx')));
          /** @var \Drupal\migrate\Plugin\MigrationInterface $migration_plugin */
          $migration_plugin = \Drupal::service('plugin.manager.migration')
            ->createInstance($migration->id(), $migration->toArray());
          if ($migration_plugin->getStatus() !== $migration_plugin::STATUS_IDLE) {
            $migration_plugin->setStatus($migration_plugin::STATUS_IDLE);
          }
        }
      }

      // Then clear the workgroups temp file for search tag updates at the end.
      $db->delete('migrate_info_earth_capx_wgs_temp')->execute();
    }
    // We will release the lock after processing accounts.
  }

  /**
   * Tag users with profile search terms.
   */
  public static function processAccounts($initial, $all_reg_tid = 0, $all_affil_tid = 0) {
    $db = \Drupal::database();
    /** @var \Drupal\Core\Entity\EntityTypeManager $em */
    $em = \Drupal::service("entity_type.manager");
    $sunets = $db->query("SELECT DISTINCT sunetid FROM " .
      "migrate_info_earth_capx_wgs WHERE sunetid LIKE :initial " .
      "UNION SELECT DISTINCT sunetid FROM migrate_info_earth_capx_wgs_temp " .
      "WHERE sunetid LIKE :initial", [':initial' => $initial . '%']);
    foreach ($sunets as $sunet) {
      $sunetid = $sunet->sunetid;
      $result = $db->query(self::EARTH_CAPX_WG_QUERY,
        [':sunetid' => $sunetid]);
      foreach ($result as $record) {
        if (reset($record) == 'different') {
          // get the wg_tags for the user
          $term_array = [];
          $wgs = $db->query("SELECT wg_tag FROM " .
            "migrate_info_earth_capx_wgs_temp WHERE sunetid = :sunetid",
            [":sunetid" => $sunetid]);
          foreach ($wgs as $wg) {
            $entity = $em->getStorage('taxonomy_term')->load($wg->wg_tag);
            $search_terms = $entity->field_people_search_terms;
            foreach ($search_terms as $search_term) {
              if ($search_term->entity) {
                $id = $search_term->entity->id();
                $term_array[intval($id)] = $id;
              }
            }
          }
            if (isset($term_array[$all_reg_tid]) &&
              isset($term_array[$all_affil_tid])) {
              unset($term_array[$all_affil_tid]);
            }
            $accounts = $em->getStorage('user')
              ->loadByProperties(['name' => $sunetid]);
            if (!empty($accounts)) {
              $account = reset($accounts);
              $termids = [];
              foreach ($term_array as $tid) {
                $termids[] = ['target_id' => $tid];
              }
              $account->field_profile_search_terms = $termids;
              $account->save();
            }
        }
        break;
      }
    }
  }

  public static function earthCapxPostImportCleanup() {
    // We need to access the database.
    $db = \Drupal::database();
    // Instantiate the lock object.
    $lock = new EarthMigrationLock($db, 'EarthCapxLock');
    // See if we have a lock by looking for a lockid stored in our session.
    /** @var \Drupal\Core\TempStore\PrivateTempStore $session */
    $session = \Drupal::service('tempstore.private')->get('EarthCapxLock');
    $mylockid = $session->get('EarthCapxLock');
    if (!empty($mylockid)) {
      // See if there is a lock in the semaphore table that matches our id.
      $actual = $lock->getExistingLockId();
      // If they match, check that the lock hasn't timed out.
      if (!empty($actual) && $actual === $mylockid && $lock->valid()) {
        // Clear the wgs table.
        $db->delete('migrate_info_earth_capx_wgs')->execute();
        // Copy the temp file to the regular file
        try {
          $query = $db->select('migrate_info_earth_capx_wgs_temp', 'm');
          $query->addField('m', 'sunetid');
          $query->addField('m', 'wg_tag');
          $db->insert('migrate_info_earth_capx_wgs')->from($query)->execute();
        }
        catch (Exception $e) {
          // Log the exception to watchdog.
          \Drupal::logger('type')->error($e->getMessage());
        }
        // Release the lock.
        $lock->releaseEarthMigrationLock($mylockid);
      }
    }
  }

  /**
   * Profile import post-processing
   */
  public static function earthCapxPostImport() {
    // We need to access the database.
    $db = \Drupal::database();
    // Instantiate the lock object.
    $lock = new EarthMigrationLock($db, 'EarthCapxLock');
    // See if we have a lock by looking for a lockid stored in our session.
    /** @var \Drupal\Core\TempStore\PrivateTempStore $session */
    $session = \Drupal::service('tempstore.private')->get('EarthCapxLock');
    $mylockid = $session->get('EarthCapxLock');
    if (!empty($mylockid)) {
      // See if there is a lock in the semaphore table that matches our id.
      $actual = $lock->getExistingLockId();
      // If they match, check that the lock hasn't timed out.
      if (!empty($actual) && $actual === $mylockid && $lock->valid()) {
        // Kick off a batch to process accounts by initial letter.
        // First we need term ids for All Regular and All Affiliated Faculty.
        $all_reg_tid = 0;
        $all_affil_tid = 0;
        /** @var \Drupal\Core\Entity\EntityTypeManager $em */
        $em = \Drupal::service("entity_type.manager");
        $props = [
          'vid' => 'people_search_terms',
          'name' => 'All Regular Faculty',
        ];
        $ps_term = $em->getStorage('taxonomy_term')->loadByProperties($props);
        if (!empty($ps_term)) {
          $term_entity = reset($ps_term);
          $all_reg_tid = intval($term_entity->id());
          $props['name'] = 'All Associated Faculty';
          $ps_term = $em->getStorage('taxonomy_term')->loadByProperties($props);
          if (!empty($ps_term)) {
            $term_entity = reset($ps_term);
            $all_affil_tid = intval($term_entity->id());
          }
        }
        //$batch_builder = new BatchBuilder();
        //$batch_builder->setTitle('Update profile search tags on accounts.');
        //$batch_builder->setFinishCallback(
        //  [
        //    new EarthCapxInfo(),
        //    'earthCapxPostImportCleanup',
        //  ]
        //);
        $abc = 'abcdefghijklmnopqrstuvwxyz';
        for ($i=0; $i<strlen($abc); $i++) {
          $istr = substr($abc, $i, 1);
          self::processAccounts($istr, $all_reg_tid, $all_affil_tid);
          /*
          $batch_builder->addOperation(
            [
              new EarthCapxInfo(),
              'processAccounts',
            ],
            [
              $istr,
              $all_reg_tid,
              $all_affil_tid,
            ]
          );
          */
        }
        self::earthCapxPostImportCleanup();
        //$batch_builder->setProgressive(TRUE);
        //batch_set($batch_builder->toArray());
        //return batch_process('/');
      }
    }
  }

  /**
   * The schema for this table to be retrieved by the module hook_schema call.
   */
  public static function getSchema() {
    return [
      'migrate_info_earth_capx_wgs' => [
        'description' => "List of workgroups found for each SUNetID",
        'fields' => [
          'sunetid' => [
            'type' => 'varchar',
            'length' => 8,
            'not null' => TRUE,
            'description' => "SUNetID for this account and profile",
          ],
          'wg_tag' => [
            'type' => 'int',
            'length' => 11,
            'not null' => TRUE,
            'description' => "Workgroup tag from import",
          ],
        ],
        'primary key' => [
          'sunetid',
          'wg_tag',
        ],
      ],
      'migrate_info_earth_capx_wgs_temp' => [
        'description' => "List of workgroups found for each SUNetID",
        'fields' => [
          'sunetid' => [
            'type' => 'varchar',
            'length' => 8,
            'not null' => TRUE,
            'description' => "SUNetID for this account and profile",
          ],
          'wg_tag' => [
            'type' => 'int',
            'length' => 11,
            'not null' => TRUE,
            'description' => "Workgroup tag from import",
          ],
        ],
        'primary key' => [
          'sunetid',
          'wg_tag',
        ],
      ],
      'migrate_info_earth_capx_importer' => [
        'description' => "Stanford Cap-X Profile Import Information",
        'fields' => [
          'sunetid' => [
            'type' => 'varchar',
            'length' => 8,
            'not null' => TRUE,
            'description' => "SUNetID for this account and profile",
          ],
          'etag' => [
            'type' => 'varchar',
            'length' => 255,
            'not null' => FALSE,
            'description' => "Hex etag of profile from CAP API",
          ],
          'photo_timestamp' => [
            'type' => 'varchar',
            'length' => 255,
            'not null' => FALSE,
            'description' => "Timestamp of profile photo update",
          ],
          'entity_id' => [
            'type' => 'int',
            'not null' => FALSE,
            'description' => "Entity id to which the profile was imported",
          ],
          'profile_photo_id' => [
            'type' => 'int',
            'not null' => FALSE,
            'description' => "File id of the profile photo already imported",
          ],
        ],
        'primary key' => ['sunetid'],
      ],
    ];
  }

}
