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
    $db = Database::getConnection();
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
            'mid_title' => [
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
            'image_title' => [
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
    return [
      '#type' => 'markup',
      '#markup' => $this->t('Hello, World!'),
    ];
  }

}
