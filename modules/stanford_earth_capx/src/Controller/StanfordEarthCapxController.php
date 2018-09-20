<?php

namespace Drupal\stanford_earth_capx\Controller;

use Drupal\Core\Render\HtmlResponse;
use Drupal\Core\Routing\TrustedRedirectResponse;
use Drupal\Core\Controller\ControllerBase;
use Drupal\migrate_plus\Entity\Migration;
use Drupal\migrate_tools\MigrateExecutable;
use Drupal\migrate\MigrateMessage;
use Drupal\migrate\Plugin\MigrationInterface;

/**
 * Redirect from earth to pangea controller.
 */
class StanfordEarthCapxController extends ControllerBase {

    /**
     * {@inheritdoc}
     */
    public function updateAll() {

        $eMigrations = \Drupal::configFactory()->listAll('migrate_plus.migration.earth_capx_import');

        foreach ($eMigrations as $eMigration) {
            print 'importing '.$eMigration . '</br>';
            //$migration = new Migration([$eMigration],'migration');
            //$migration = Migration::load($eMigration);
            //$executable = new MigrateExecutable($migration, $log, ['update' => true]);
            //$executable->import();
        }
        //$response = new TrustedRedirectResponse('\user');
        \Drupal::service('page_cache_kill_switch')->trigger();
        //$response->send();
        $response = new HtmlResponse('Done!');
        return $response;

    }
}
