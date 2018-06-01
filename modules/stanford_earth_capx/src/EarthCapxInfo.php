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

class EarthCapxInfo
{

  const EARTH_CAPX_INFO_INVALID = 0;
  const EARTH_CAPX_INFO_NEW = 1;
  const EARTH_CAPX_INFO_FOUND = 2;

  const EARTH_CAPX_INFO_TABLE = 'migrate_info_earth_capx_importer';

  private $sunetid;
  private $etag;
  private $profilePhotoTimestamp;
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
      }
    }
  }

  /**
   * return true if profile should be updated because it is new
   * or because the etag has changed.
   * @param array $source
   */
  public function getOkayToUpdateProfile($source = []) {
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
      if ($this->etag !== $source_etag) {
        $oktoupdate = true;
      }
    }
    return $oktoupdate;
  }
  
  /**
   * update the table with information from the source array
   * @param array $source
   */
  public function setInfoRecord($source = []) {
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
    if (!empty($source['profilePhoto']) && is_string($source['profilePhoto'])) {
      $ts1 = strpos($source['profilePhoto'],"ts=");
      if ($ts1 !== false) {
        $ts2 = substr($source['profilePhoto'],$ts1);
        if (strpos($ts2,"&") !== false)  {
          $source_ts = substr($ts2,3,strpos($ts2,"&")-3);
        } else {
          $source_ts = substr($ts2,3);
        }
      }
    }

    // if existing in table, see if we need to update
    if ($this->status == self::EARTH_CAPX_INFO_FOUND) {
      if ($this->etag !== $source_etag ||
        $this->profilePhotoTimestamp !== $source_ts) {
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
      \Drupal::database()->insert(self::EARTH_CAPX_INFO_TABLE)
        ->fields([
          'sunetid' => $this->sunetid,
          'etag' => $this->etag,
          'photo_timestamp' => $this->profilePhotoTimestamp,
        ])
        ->execute();
    }
  }

  /**
   * delete a record from the table by sunetid
   * @param string $su_id
   */
  public static function delete($su_id = "") {
    if (!empty($su_id)) {
      \Drupal::database()->delete(self::EARTH_CAPX_INFO_TABLE)
        ->condition('sunetid',$su_id)
        ->execute();
    }
  }

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
        ],
        'primary key' => ['sunetid'],
      ],
    ];
  }
}
