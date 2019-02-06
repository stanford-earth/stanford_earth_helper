<?php

namespace Drupal\stanford_earth_capx\Controller;

use Drupal\Core\Render\HtmlResponse;
use Drupal\Core\Routing\TrustedRedirectResponse;
use Drupal\Core\Controller\ControllerBase;
use Drupal\migrate\Plugin\MigrationPluginManager;
use Drupal\migrate_plus\Entity\Migration;
use Drupal\migrate_tools\MigrateExecutable;
use Drupal\migrate\MigrateMessage;
use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\migrate_tools\MigrateBatchExecutable;
use Drupal\Core\Batch\BatchBuilder;

/**
 * Redirect from earth to pangea controller.
 */
class StanfordEarthCapxController extends ControllerBase {

  /**
   * {@inheritdoc}
   */
  public function updateAll($refresh) {

    if ($refresh) {
      \Drupal::database()->query("UPDATE {migrate_info_earth_capx_importer} SET photo_timestamp = 0, workgroup_list=''")->execute();
      \Drupal::database()->query("DELETE FROM {user__field_profile_search_terms}")->execute();
    }

    $eMigrations = \Drupal::configFactory()
      ->listAll('migrate_plus.migration.earth_capx_import');

    $batch_builder = new BatchBuilder();
    $batch_builder->setTitle(t('Import Profiles'));

    foreach ($eMigrations as $key => $eMigration) {
      $migration = Migration::load(substr($eMigration, strpos($eMigration, 'earth')));
      $mp = \Drupal::getContainer()->get('plugin.manager.migration');
      $migration_plugin = $mp->createInstance($migration->id(), $migration->toArray());
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
            'force' => 0.
          ],
          $context,
        ]
      );
    }
    batch_set($batch_builder->toArray());
    return batch_process('/');
  }

}
