<?php

namespace Drupal\stanford_earth_capx\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\migrate\Plugin\MigrationPluginManager;
use Drupal\migrate_plus\Entity\Migration;
use Drupal\Core\Batch\BatchBuilder;
use Drupal\Core\Database\Connection;
use Drupal\Core\Config\ConfigFactory;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\user\Entity\User;
use Drupal\media\Entity\Media;

/**
 * Redirect from earth to pangea controller.
 */
class StanfordEarthCapxController extends ControllerBase {
  use StringTranslationTrait;

  /**
   * Migration plugin manager.
   *
   * @var Drupal\migrate\Plugin\MigrationPluginManager
   *   The migration plugin manager object.
   */
  protected $mp;

  /**
   * Database connection object.
   *
   * @var \Drupal\Core\Database\Connection
   *   The database connection.
   */
  protected $db;

  /**
   * Drupal config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactory
   *   The factory object.
   */
  protected $cf;

  /**
   * StanfordEarthCapxController constructor.
   *
   * @param Drupal\migrate\Plugin\MigrationPluginManager $mp
   *   The migration plugin manager.
   * @param \Drupal\Core\Database\Connection $db
   *   The database connection object.
   * @param \Drupal\Core\Config\ConfigFactory $cf
   *   The config factory object.
   */
  public function __construct(MigrationPluginManager $mp,
                              Connection $db,
                              ConfigFactory $cf) {
    $this->mp = $mp;
    $this->db = $db;
    $this->cf = $cf;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('plugin.manager.migration'),
      $container->get('database'),
      $container->get('config.factory')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function updateAll($refresh) {

    if ($refresh) {
      $this->db->query("UPDATE {migrate_info_earth_capx_importer} SET photo_timestamp = 0, workgroup_list=''")->execute();
      $this->db->query("DELETE FROM {user__field_profile_search_terms}")->execute();
    }

    $eMigrations = $this->cf
      ->listAll('migrate_plus.migration.earth_capx_import');

    $batch_builder = new BatchBuilder();
    $batch_builder->setTitle($this->t('Import Profiles'));

    foreach ($eMigrations as $eMigration) {
      $migration = Migration::load(substr($eMigration, strpos($eMigration, 'earth')));
      // $mp = \Drupal::getContainer()->get('plugin.manager.migration');
      $migration_plugin = $this->mp->createInstance($migration->id(), $migration->toArray());
      $migration_plugin->getIdMap()->prepareUpdate();
      $context = [
        'sandbox' => [
          'total' => 200,
          'counter' => 0,
          'batch_limit' => 200,
          'operation' => 1,
        ],
      ];
      $batch_builder->addOperation(
        '\Drupal\migrate_tools\MigrateBatchExecutable::batchProcessImport',
        [
          substr($eMigration, strpos($eMigration, 'earth')),
          [
            'limit' => 0,
            'update' => 1,
            'force' => 0,
          ],
          $context,
        ]
      );
    }
    batch_set($batch_builder->toArray());
    return batch_process('/');
  }

  public function cleanupMedia() {

    // create table if necessary
    $db = \Drupal::database();
    $schema = $db->schema();
    if ($schema->tableExists('{migrate_info_earth_capx_media}')) {
      $db->query('delete from {migrate_info_earth_capx_media}');
    } else {
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

    $q1 = "SELECT u.uid, u.name, m.field_s_person_media_target_id, " .
      "f.field_media_image_target_id, x.origname, x.uri, " .
      "i.field_s_person_image_target_id FROM users_field_data u, " .
      "user__field_s_person_media m, media__field_media_image f, " .
      "file_managed x, user__field_s_person_image i WHERE u.uid = "  .
      "m.entity_id AND m.field_s_person_media_target_id = f.entity_id AND " .
      "f.field_media_image_target_id = x.fid AND u.uid = i.entity_id AND " .
      "i.field_s_person_image_target_id = f.field_media_image_target_id";

    /*
     *
     * select u.uid, u.name, m.field_s_person_media_target_id, f.field_media_image_target_id, x.origname, x.uri from users_field_data u, user__field_s_person_media m, media__field_media_image f, file_managed x where u.uid = m.entity_id and m.field_s_person_media_target_id = f.entity_id and f.field_media_image_target_id = x.fid and u.uid not in (select distinct entity_id from user__field_s_person_image where bundle = 'user');
*
*    select u.uid, u.name, i.field_s_person_image_target_id, x.origname, x.uri from users_field_data u, user__field_s_person_image i, file_managed x where u.uid = i.entity_id and i.field_s_person_image_target_id = x.fid and u.uid not in (select distinct entity_id from user__field_s_person_media where bundle = 'user');
*
     */
    $image_recs = \Drupal::database()->query($q1);
    foreach ($image_recs as $key => $image_rec) {
      $uid = 0;
      $sunetid = "";
      $mid = 0;
      $mid_fid = 0;
      $origname = "";
      $uri = "";
      $image_fid = 0;
      if (!empty($image_rec->uid)) $uid = $image_rec->uid;
      if (!empty($image_rec->name)) $sunetid = $image_rec->name;
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
        \Drupal::database()->insert('migrate_info_earth_capx_media')
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
      if (!empty($origname)) {
        //$origbase = substr($origname, 0, strpos($origname, ".jpg"));
        $files = \Drupal::database()->query("select fid, uri FROM file_managed WHERE origname = :origname", [':origname' => $origname]);
        foreach ($files as $key => $foundfile) {
          $xyz = $foundfile->uri;
          $xy2 = 1;
        }
      }
    }

    /*
    $uids = \Drupal::entityQuery('user')
      ->condition('field_s_person_media.target_id', 0, '>')
      ->execute();
    //$uids = \Drupal::entityQuery('user')
    //  ->condition('field_s_person_image.target_id', 0, '>')
    //->execute();
    foreach ($uids as $uid => $uid_str) {
      $account = User::load($uid);
      //$mid = $account->field_s_person_media->target_id;
      //$media = Media::load($mid);
      //$media_image_fid = $media->field_media_image->target_id;
      //$media_image_title = $media->field_media_image->title;
      $name = $account->getUsername();
      $image_fid = $account->field_s_person_image->target_id;
      $image_title = $account->field_s_person_image->title;
      $xyz = 1;
    }
    */

    return [
      '#type' => 'markup',
      '#markup' => $this->t('Hello, World!'),
    ];
  }

}
