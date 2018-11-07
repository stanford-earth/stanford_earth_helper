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

/**
 * Redirect from earth to pangea controller.
 */
class StanfordEarthCapxController extends ControllerBase {

  /**
   * {@inheritdoc}
   */
  public function updateAll($refresh) {

    if ($refresh) {
      \Drupal::database()->query("UPDATE {migrate_info_earth_capx_importer} SET photo_timestamp = 0")->execute();
    }

    $eMigrations = \Drupal::configFactory()
      ->listAll('migrate_plus.migration.earth_capx_import');

    foreach ($eMigrations as $eMigration) {
      \Drupal::logger('type')->info('importing ' . $eMigration);
      $migration = Migration::load(substr($eMigration, strpos($eMigration, 'earth')));
      $mp = \Drupal::getContainer()->get('plugin.manager.migration');
      $migration_plugin = $mp->createInstance($migration->id(), $migration->toArray());
      $migrateMessage = new MigrateMessage();
      $options = [
        'limit' => 0,
        'update' => 1,
        'force' => 0,
      ];
      $executable = new MigrateBatchExecutable($migration_plugin, $migrateMessage, $options);
      $executable->batchImport();
    }
    return batch_process('/');
  }

}
