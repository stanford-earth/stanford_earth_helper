<?php

namespace Drupal\stanford_earth_capx;

use Drupal\Core\Database;
use Drupal\migrate\MigrateMessage;

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

  private $sunetid;
  private $etag;
  private $profilePhotoTimestamp;
  private $entityId;
  private $profilePhotoFid;
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
    if (empty($source['profile_photo']) || empty($this->profilePhotoFid)
      || empty($this->profilePhotoTimestamp)) {
      return FALSE;
    }
    $photo_ts = $this->getPhotoTimestamp($source['profile_photo']);
    if ($photo_ts == $this->profilePhotoTimestamp) {
      return $this->profilePhotoFid;
    }
    else {
      return FALSE;
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
  public function getOkayToUpdateProfile(array $source = [], int $photoId = 0) {
    // Checks $status which was set in the constructor.
    $oktoupdate = FALSE;
    $msg = new MigrateMessage();
    if (empty($source['sunetid']) ||
      $this->status == self::EARTH_CAPX_INFO_INVALID ||
      $source['sunetid'] !== $this->sunetid) {
      $msg->display(t('Unable to validate new profile information.'), 'error');
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
      //if (!$oktoupdate && !empty($this->entity_id)) {
      //    $user_acct = \Drupal\user\Entity\User::load($this->entity_id);
      //    if (!empty($user_acct) && empty($user_acct->getEmail())) {
      //        $oktoupdate = TRUE;
      //    }
      //}
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
  public function setInfoRecord(array $source = [], $entity_id = 0, $photo_id = 0) {
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
        $m->display(t('Unable to insert new EarthCapxInfo record for @sunet', ['sunet' => $this->sunetid]));
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
   * The schema for this table to be retrieved by the module hook_schema call.
   */
  public static function getSchema() {
    return [
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
