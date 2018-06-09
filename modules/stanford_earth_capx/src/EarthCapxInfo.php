<?php

namespace Drupal\stanford_earth_capx;

use Drupal\Core\Database;
use Drupal\migrate\MigrateMessage;

/**
 * Encapsulates an information table for CAP-X Profile imports.
 */
class EarthCapxInfo {

  const EARTH_CAPX_INFO_INVALID = 0;
  const EARTH_CAPX_INFO_NEW = 1;
  const EARTH_CAPX_INFO_FOUND = 2;

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
  public function __construct($su_id = "") {
    $su_id = (string) $su_id;
    $this->sunetid = "";
    $this->status = self::EARTH_CAPX_INFO_INVALID;
    $this->etag = "";
    $this->profilePhotoTimestamp = "";
    $this->entity_id = 0;
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
          $this->entity_id = intval($record->entity_id);
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
   * @param int $entityId
   *   Entity ID of the profile.
   * @param int $photo_id
   *   Photo id number from profile photo URL.
   */
  public function setInfoRecord(array $source = [], $entityId = 0, $photo_id = 0) {
    // See if we have valid sunetids.
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
        $this->entityId !== $entityId) {
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
      $this->entityId = $entityId;
      \Drupal::database()->insert(self::EARTH_CAPX_INFO_TABLE)
        ->fields([
          'sunetid' => $this->sunetid,
          'etag' => $this->etag,
          'photo_timestamp' => $this->profilePhotoTimestamp,
          'entityId' => $this->entityId,
          'profile_photo_id' => $this->profilePhotoFid,
        ])
        ->execute();
    }
  }

  /**
   * Delete a record from the table by entity_id.
   *
   * @param string $entityId
   *   Entity ID of profile to be deleted.
   */
  public static function delete($entityId = 0) {
    if ($entityId > 0) {
      \Drupal::database()->delete(self::EARTH_CAPX_INFO_TABLE)
        ->condition('entity_id', $entityId)
        ->execute();
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
