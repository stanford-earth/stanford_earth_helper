<?php

namespace Drupal\stanford_earth_capx\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\migrate\Plugin\MigrationPluginManager;
use Drupal\Core\Batch\BatchBuilder;
use Drupal\Core\Database\Connection;
use Drupal\Core\Config\ConfigFactory;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\migrate_plus\Entity\Migration;
use Drupal\stanford_earth_capx\EarthCapxInfo;
use Symfony\Component\DependencyInjection\ContainerInterface;

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
    $eMigrations = $this->cf
      ->listAll('migrate_plus.migration.earth_capx_importer');

    $batch_builder = new BatchBuilder();
    $batch_builder->setFinishCallback(
      [
        new EarthCapxInfo(),
        'earthCapxPostImport',
      ]
    );
    foreach ($eMigrations as $eMigration) {
      if (strpos($eMigration, "process") === FALSE) {
        $batch_builder->addOperation(
          [
            $this,
            'earthCapxImportFeed',
          ],
          [
            substr($eMigration, strpos($eMigration, 'earth_capx')),
          ]
        );
      }
    }
    batch_set($batch_builder->toArray());
    EarthCapxInfo::earthCapxPreImport();
    return batch_process();
  }

  /**
   * Import profiles via batch.
   *
   * @param string $migrationId
   *   Name of the migration to import.
   */
  public function earthCapxImportFeed(string $migrationId) {
    $migration = Migration::load($migrationId);
    /** @var \Drupal\migrate\Plugin\MigrationInterface $migration_plugin */
    $migration_plugin = $this->mp->createInstance($migration->id(), $migration->toArray());
    $migration_plugin->getIdMap()->prepareUpdate();
    $migrateMessage = new MigrateMessage();
    $options = [
      'limit' => 0,
      'update' => 1,
      'force' => 0,
    ];
    $executable = new MigrateBatchExecutable($migration_plugin, $migrateMessage, $options);
    $executable->batchImport();
  }

}
