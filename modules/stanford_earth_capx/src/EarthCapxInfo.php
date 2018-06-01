<?php
/**
 * Created by PhpStorm.
 * User: kennethsharp1
 * Date: 5/30/18
 * Time: 9:29 PM
 */

namespace Drupal\stanford_earth_capx;

use Drupal\Core\Database;
use Drupal\migrate\MigrateMessage;

/*
 * Encapsulates an information table for CAP-X Profile imports
 */
class EarthCapxInfo
{

  const EARTH_CAPX_INFO_INVALID = 0;
  const EARTH_CAPX_INFO_NEW = 1;
  const EARTH_CAPX_INFO_FOUND = 2;

  const EARTH_CAPX_INFO_TABLE = 'migrate_info_earth_capx_importer';

  private $sunetid;
  private $etag;
  private $profilePhotoTimestamp;
  private $entity_id;
  private $profilePhotoFid;
  private $status;

  /**
   * construct object with sunetid parameter;
   * if already in table, populate other properties.
   *
   * @param $su_id
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
        "} WHERE sunetid = :sunetid", [ ':sunetid' => $this->sunetid]);
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
   * get the photo timestamp from the cap url
   */
  private function getPhotoTimestamp($photoUrl = "") {
    $photo_ts = '';
    if (!empty($photoUrl) && is_string($photoUrl)) {
      $ts1 = strpos($photoUrl,"ts=");
      if ($ts1 !== false) {
        $ts2 = substr($photoUrl,$ts1);
        if (strpos($ts2,"&") !== false)  {
          $photo_ts = substr($ts2,3,strpos($ts2,"&")-3);
        } else {
          $photo_ts = substr($ts2,3);
        }
      }
    }
    return $photo_ts;
  }
  
  /**
   * see if we have an existing and current profile photo_id
   * if we do, return its fid. if we don't, return false
   * @param array $source
   */
  public function currentProfilePhotoId($source = []) {
    if (empty($source['profile_photo']) || empty($this->profilePhotoFid)
      || empty($this->profilePhotoTimestamp)) {
      return false;
    }
    $photo_ts = $this->getPhotoTimestamp($source['profile_photo']);
    if ($photo_ts == $this->profilePhotoTimestamp) {
      return $this->profilePhotoFid;
    } else {
      return false;
    }
  }
  
  /**
   * return true if profile should be updated because it is new
   * or because the etag has changed.
   * @param array $source
   */
  public function getOkayToUpdateProfile($source = [], $photoId = 0) {
    $oktoupdate = false;
    $msg = new MigrateMessage();
    if (empty($source['sunetid']) ||
      $this->status == self::EARTH_CAPX_INFO_INVALID ||
      $source['sunetid'] !== $this->sunetid) {
        $msg->display('Unable to validate new profile information.', 'error');
    } else if ($this->status == self::EARTH_CAPX_INFO_NEW) {
      $oktoupdate = true;
    } else if ($this->status == self::EARTH_CAPX_INFO_FOUND) {
      $source_etag = '';
      if (!empty($source['etag'])) {
        $source_etag = $source['etag'];
      }
      if ($this->etag !== $source_etag || $this->profilePhotoFid !== $photoId) {
        $oktoupdate = true;
      }
    }
    return $oktoupdate;
  }
  
  /**
   * update the table with information from the source array and destination id
   * only do the operation if information has changed
   * 
   * @param array $source
   */
  public function setInfoRecord($source = [], $entity_id = 0, $photo_id = 0) {
    // see if we have valid sunetids
    $msg = new MigrateMessage();
    if (empty($source['sunetid']) ||
      $this->status == self::EARTH_CAPX_INFO_INVALID) {
        $msg->display('Unable to update EarthCapxInfo table. Missing source id.','error');
        return;
    }
    if ($source['sunetid'] !== $this->sunetid) {
      $msg->display('Unable to update EarthCapxInfo table. Mismatched ids: ' .
        $source['sunetid'] . ', ' . $this->sunetid);
      return;
    }

    // get the fields from the source array
    $source_etag = '';
    if (!empty($source['etag'])) $source_etag = $source['etag'];
    
    $source_ts = '';
    if (!empty($source['profile_photo'])) {
      $source_ts = $this->getPhotoTimestamp($source['profile_photo']);
    }

    $photo_id = intval($photo_id);
    
    // if existing in table, see if we need to update
    if ($this->status == self::EARTH_CAPX_INFO_FOUND) {
      if ($this->etag !== $source_etag ||
        $this->profilePhotoTimestamp !== $source_ts ||
        $this->profilePhotoFid !== $photo_id ||
        $this->entityId != $entity_id) {
          // the information is different, so delete record and set status = NEW
          \Drupal::database()->delete(self::EARTH_CAPX_INFO_TABLE)
            ->condition('sunetid',$this->sunetid)
            ->execute();
        $this->status = self::EARTH_CAPX_INFO_NEW;
      }
    }

    // now we will insert only if record is truly new or has changed.
    if ($this->status == self::EARTH_CAPX_INFO_NEW) {
      $this->etag = $source_etag;
      $this->profilePhotoTimestamp = $source_ts;
      $this->profilePhotoFid = $photo_id;
      \Drupal::database()->insert(self::EARTH_CAPX_INFO_TABLE)
        ->fields([
          'sunetid' => $this->sunetid,
          'etag' => $this->etag,
          'photo_timestamp' => $this->profilePhotoTimestamp,
          'entity_id' => $entity_id,
          'profile_photo_id' => $this->profilePhotoFid,
        ])
        ->execute();
    }
  }

  /**
   * delete a record from the table by entity_id
   * @param string $su_id
   */
  public static function delete($entity_id = 0) {
    if ($entity_id > 0) {
      \Drupal::database()->delete(self::EARTH_CAPX_INFO_TABLE)
        ->condition('entity_id',$entity_id)
        ->execute();
    }
  }

  /*
   * the schema for this table to be retrieved by the module hook_schema call
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
          ]
        ],
        'primary key' => ['sunetid'],
      ],
    ];
  }
}
