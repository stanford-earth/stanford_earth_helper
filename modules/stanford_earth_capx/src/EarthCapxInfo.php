<?php

namespace Drupal\stanford_earth_capx;

use Drupal\migrate\MigrateMessage;
use Drupal\user\Entity\User;
use Drupal\media\Entity\Media;
use Drupal\file\Entity\File;

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
   * Workgroup from which user profile comes.
   *
   * @var string
   */
  private $workgroups;

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
    $this->workgroups = [];
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
        if (!empty($record->workgroup_list)) {
          $this->workgroups = unserialize($record->workgroup_list);
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
   * @param string $wg
   *   The name of the workgroup being processed.
   */
  public function getOkayToUpdateProfile(array $source = [],
                                         int $photoId = NULL,
                                         string $wg = NULL) {
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
      if (!$oktoupdate && !empty($wg) && !in_array($wg, $this->workgroups)) {
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
   * @param string $wg
   *   Workgroup being processed.
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
   * Build a profile media table to use for cleanup.
   *
   * @throws \Exception
   */
  public static function buildProfileMediaTable() {
    set_time_limit(0);
    $db = \Drupal::database();

    // Create table if necessary.
    $schema = $db->schema();
    if ($schema->tableExists('{migrate_info_earth_capx_media}')) {
      $db->query('delete from {migrate_info_earth_capx_media}');
    }
    else {
      $schema->createTable('migrate_info_earth_capx_media',
        [
          'description' => "Stanford Profiles Media Information",
          'fields' => [
            'uid' => [
              'type' => 'int',
              'not null' => TRUE,
              'description' => "uid of account",
            ],
            'sunetid' => [
              'type' => 'varchar',
              'length' => 8,
              'not null' => FALSE,
              'description' => "account name",
            ],
            'mid' => [
              'type' => 'int',
              'not null' => FALSE,
              'description' => "media entity id",
            ],
            'mid_fid' => [
              'type' => 'int',
              'not null' => FALSE,
              'description' => "Image file id associated with mid",
            ],
            'origname' => [
              'type' => 'varchar',
              'length' => 255,
              'not null' => FALSE,
              'description' => "Title of media image file",
            ],
            'image_fid' => [
              'type' => 'int',
              'not null' => FALSE,
              'description' => "Image file id not associated with mid",
            ],
            'uri' => [
              'type' => 'varchar',
              'length' => 255,
              'not null' => FALSE,
              'description' => "Title of image file",
            ],
          ],
          'primary key' => ['uid'],
        ]
      );
    }

    // Find all media entities and image files.
    $q = [];
    $q[] = "SELECT u.uid, u.name, m.field_s_person_media_target_id, " .
      "f.field_media_image_target_id, x.origname, x.uri " .
      "FROM users_field_data u, user__field_s_person_media m, " .
      "media__field_media_image f, file_managed x WHERE u.uid = m.entity_id " .
      "AND m.field_s_person_media_target_id = f.entity_id AND " .
      "f.field_media_image_target_id = x.fid AND u.uid NOT IN " .
      "(SELECT DISTINCT entity_id FROM user__field_s_person_image " .
      "WHERE bundle = 'user')";

    $q[] = "SELECT u.uid, u.name, i.field_s_person_image_target_id, " .
      "x.origname, x.uri FROM users_field_data u, " .
      "user__field_s_person_image i, file_managed x " .
      "WHERE u.uid = i.entity_id AND " .
      "i.field_s_person_image_target_id = x.fid AND u.uid NOT IN " .
      "(SELECT distinct entity_id from user__field_s_person_media where " .
      "bundle = 'user')";

    $q[] = "SELECT u.uid, u.name, m.field_s_person_media_target_id, " .
      "f.field_media_image_target_id, x.origname, x.uri, " .
      "i.field_s_person_image_target_id FROM users_field_data u, " .
      "user__field_s_person_media m, media__field_media_image f, " .
      "file_managed x, user__field_s_person_image i WHERE u.uid = " .
      "m.entity_id AND m.field_s_person_media_target_id = f.entity_id AND " .
      "f.field_media_image_target_id = x.fid AND u.uid = i.entity_id AND " .
      "i.field_s_person_image_target_id = f.field_media_image_target_id";

    for ($i = 0; $i < count($q); $i++) {
      $image_recs = $db->query($q[$i]);
      foreach ($image_recs as $image_rec) {
        $uid = 0;
        $sunetid = "";
        $mid = 0;
        $mid_fid = 0;
        $origname = "";
        $uri = "";
        $image_fid = 0;
        if (!empty($image_rec->uid)) {
          $uid = $image_rec->uid;
        }
        if (!empty($image_rec->name)) {
          $sunetid = $image_rec->name;
        }
        if (!empty($image_rec->field_s_person_media_target_id)) {
          $mid = $image_rec->field_s_person_media_target_id;
        }
        if (!empty($image_rec->field_media_image_target_id)) {
          $mid_fid = $image_rec->field_media_image_target_id;
        }
        if (!empty($image_rec->origname)) {
          $origname = $image_rec->origname;
        }
        if (!empty($image_rec->uri)) {
          $uri = $image_rec->uri;
        }
        if (!empty($image_rec->field_s_person_image_target_id)) {
          $image_fid = $image_rec->field_s_person_image_target_id;
        }

        try {
          $db->insert('migrate_info_earth_capx_media')
            ->fields([
              'uid' => $uid,
              'sunetid' => $sunetid,
              'mid' => $mid,
              'mid_fid' => $mid_fid,
              'origname' => $origname,
              'image_fid' => $image_fid,
              'uri' => $uri,
            ])
            ->execute();
        }
        catch (Exception $e) {
          \Drupal::logger('type')->error($e->getMessage());
        }
      }
    }
  }

  /**
   * Delete a media entity for which a file is uses.
   *
   * @param string $fid
   *   File id of the file for which to delete a media entity.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public static function deleteMediaEntity($fid) {
    $db = \Drupal::database();
    $storage = \Drupal::entityTypeManager()->getStorage('media');
    $media = $db->query("select entity_id from media__field_media_image " .
      "WHERE bundle = 'image' and field_media_image_target_id = :target_id",
      [':target_id' => $fid]);
    foreach ($media as $medium) {
      $mentity = $storage->load($medium->entity_id);
      if (!empty($mentity)) {
        $storage->delete([$mentity]);
      }
    }
  }

  /**
   * Delete duplicate profile images.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public static function deleteDuplicateProfileImages() {
    set_time_limit(0);
    $db = \Drupal::database();
    set_time_limit(0);
    $fnames = $db->query("SELECT origname, uri from " .
      "{migrate_info_earth_capx_media}");
    foreach ($fnames as $fname) {
      $origname = $fname->origname;
      $uri = $fname->uri;
      // Delete duplicates.
      if (!empty($origname)) {
        $files = $db->query("select fid, uri " .
          "FROM file_managed WHERE origname = :origname",
          [':origname' => $origname]);
        foreach ($files as $foundfile) {
          $file = File::load($foundfile->fid);
          if ($foundfile->uri !== $uri) {
            self::deleteMediaEntity($foundfile->fid);
            $file->delete();
          }
        }
      }
    }
  }

  /**
   * Deletes unused profile images.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public static function deleteUnusedProfileImages() {
    set_time_limit(0);
    $db = \Drupal::database();
    set_time_limit(0);
    // Delete files not in use by any accounts.
    $q1 = "SELECT DISTINCT origname FROM file_managed WHERE fid NOT IN " .
      "(SELECT fid FROM file_usage WHERE type = 'user') AND " .
      " uri LIKE '%stanford_person%'";

    $orignames = $db->query($q1);
    foreach ($orignames as $origname) {
      $q2 = 'SELECT fid FROM file_managed WHERE uri NOT LIKE' .
        "'%" . $origname->origname . "' and origname = '" .
        $origname->origname . "'";
      $files = $db->query($q2);
      foreach ($files as $foundfile) {
        self::deleteMediaEntity($foundfile->fid);
        $file = File::load($foundfile->fid);
        $file->delete();
      }
    }
  }

  /**
   * Delete profile images associated with a workgroup.
   *
   * @param string $wg
   *   Workgroup name for which to look for users.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   * @throws \Drupal\Core\TypedData\Exception\ReadOnlyException
   */
  public static function deleteWorkgroupProfileImages($wg) {
    set_time_limit(0);
    $db = \Drupal::database();
    $user_fields = \Drupal::service('entity_field.manager')
      ->getFieldDefinitions('user', 'user');
    /** @var \Drupal\field\Entity\FieldConfig $media_field_def **/
    $media_field_def = $user_fields['field_s_person_media'];
    $default_value = $media_field_def->getDefaultValueLiteral();
    $default_image = NULL;
    if (!empty($default_value[0]['target_uuid'])) {
      $default_image = $default_value[0]['target_uuid'];
    }
    if (!empty($default_image)) {
      $mids = $db->query("SELECT mid FROM media WHERE uuid = :uuid",
        [':uuid' => $default_image]);
    }
    $default_mid = 0;
    $default_fid = 0;
    foreach ($mids as $mid) {
      $default_mid = $mid->mid;
    }
    if (!empty($default_mid)) {
      $storage = \Drupal::entityTypeManager()->getStorage('media');
      $mentity = $storage->load($default_mid);
      $fid_val = $mentity->get('field_media_image')->getValue();
      if (!empty($fid_val[0]['target_id'])) {
        $default_fid = $fid_val[0]['target_id'];
      }
    }

    $wg_service = \Drupal::service('stanford_earth_workgroups.workgroup');
    $wg_members = $wg_service->getMembers($wg);
    if ($wg_members['status']['member_count'] > 0) {
      foreach ($wg_members['members'] as $sunetid => $displayname) {
        /** @var \Drupal\user\Entity\User $account */
        $account = user_load_by_name($sunetid);
        $val = $account->get('field_s_person_image')->getValue();
        if (!empty($val[0]['target_id'])) {
          $fid = $val[0]['target_id'];
          if ($fid !== $default_fid) {
            self::deleteMediaEntity($fid);
            $file = File::load($fid);
            $file->delete();
          }
        }
        $account->get('field_s_person_image')->setValue(NULL);
        $val = $account->get('field_s_person_media')->getValue();
        if (!empty($val[0]['target_id']) && $val[0]['target_id'] !== $default_mid) {
          $storage = \Drupal::entityTypeManager()->getStorage('media');
          $mentity = $storage->load($val[0]['target_id']);
          if (!empty($mentity)) {
            $storage->delete([$mentity]);
          }
        }
        $account->get('field_s_person_media')->applyDefaultValue();
        $account->save();
      }
    }
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
          'workgroup_list' => [
            'type' => 'text',
            'not null' => FALSE,
            'size' => 'big',
            'description' => 'Workgroups in which this profile is found',
          ],
        ],
        'primary key' => ['sunetid'],
      ],
    ];
  }

}
