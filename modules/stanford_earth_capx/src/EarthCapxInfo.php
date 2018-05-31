<?php
/**
 * Created by PhpStorm.
 * User: kennethsharp1
 * Date: 5/30/18
 * Time: 9:29 PM
 */

namespace Drupal\stanford_earth_capx;

use Drupal\Core\Database;

class EarthCapxInfo
{

  /**
   * @var sunetid
   */
  private $sunetid;

  /**
   * @var etag
   */
  private $etag;

  /**
   * @var profilePhotoTimestamp
   */
  private $profilePhotoTimestamp;

  /**
   * @param $sunetid
   */
  public function __construct($su_id = "") {
    $su_id = (string) $su_id;
    $sunetid = $su_id;
    $etag = "";
    $profilePhotoTimestamp = 0;
    if (!empty($sunetid)) {
      $db = \Drupal::database();
      $result = $db->query("SELECT * FROM {migrate_info_earth_capx_importer} WHERE sunetid = :sunetid",
        [ ':sunetid' => $sunetid]
      );
      foreach ($result as $record) {
        $xyz = print_r($record,true);
      }
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
            'type' => 'int',
            'unsigned' => TRUE,
            'not null' => FALSE,
            'description' => "Timestamp of profile photo update",
          ],
        ],
        'primary key' => ['sunetid'],
      ],
    ];
  }
}
