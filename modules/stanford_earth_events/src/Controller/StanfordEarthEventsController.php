<?php

namespace Drupal\stanford_earth_events\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\migrate\Plugin\MigrationPluginManager;
use Drupal\migrate_plus\Entity\Migration;
use Drupal\Core\Batch\BatchBuilder;
use Drupal\Core\Database\Connection;
use Drupal\Core\Config\ConfigFactory;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\stanford_earth_events\EarthEventsInfo;
use Drupal\Core\Entity\EntityTypeManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Stanford events controller.
 */
class StanfordEarthEventsController extends ControllerBase {

  use DependencySerializationTrait;
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
   * EntityTypeManager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManager
   */
  protected $entityTypeManager;

  /**
   * StanfordEarthEventsController constructor.
   *
   * @param Drupal\migrate\Plugin\MigrationPluginManager $mp
   *   The migration plugin manager.
   * @param \Drupal\Core\Database\Connection $db
   *   The database connection object.
   * @param \Drupal\Core\Config\ConfigFactory $cf
   *   The config factory object.
   * @param \Drupal\Core\Entity\EntityTypeManager $entityTypeManager
   *   The EntityTypeManager service.
   */
  public function __construct(MigrationPluginManager $mp,
                              Connection $db,
                              ConfigFactory $cf,
                              EntityTypeManager $entityTypeManager) {
    $this->mp = $mp;
    $this->db = $db;
    $this->cf = $cf;
    $this->entityTypeManager = $entityTypeManager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('plugin.manager.migration'),
      $container->get('database'),
      $container->get('config.factory'),
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function importEvents() {

    $eMigrations = $this->cf
      ->listAll('migrate_plus.migration.earth_events_importer');

    $batch_builder = new BatchBuilder();
    $batch_builder->setTitle($this->t('Import Events'));
    $batch_builder->addOperation(
      [
        new EarthEventsInfo(),
        'earthEventsMakeOrphans',
      ]
    );
    foreach ($eMigrations as $eMigration) {
      if (strpos($eMigration, "process") === FALSE) {
        $migration = Migration::load(substr($eMigration, strpos($eMigration, 'earth_events_importer')));
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
            substr($eMigration, strpos($eMigration, 'earth_events')),
            [
              'limit' => 0,
              'update' => 1,
              'force' => 0,
            ],
            $context,
          ]
        );
      }
    }
    $batch_builder->addOperation(
      [
        new EarthEventsInfo(),
        'earthEventsDeleteOrphans',
      ]
    );
    batch_set($batch_builder->toArray());
    return batch_process('/');
  }

}