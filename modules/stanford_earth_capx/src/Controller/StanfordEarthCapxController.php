<?php

namespace Drupal\stanford_earth_capx\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\migrate\Plugin\MigrationPluginManager;
use Drupal\migrate_plus\Entity\Migration;
use Drupal\Core\Batch\BatchBuilder;
use Drupal\Core\Database\Connection;
use Drupal\Core\Config\ConfigFactory;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Render\HtmlResponse;
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

  public function updateSearchTerms() {
    /* @var $entity_service \Drupal\Core\Entity\EntityTypeManager */
    $entity_service = \Drupal::service('entity_type.manager');
    $terms = $entity_service
      ->getStorage('taxonomy_term')
      ->loadByProperties(['vid' => 'people_search_terms']);
    if (!empty($terms)) {
      foreach ($terms as $value) {
        $name = $value->getName();
        $name = str_replace('DEAN\'S OFFICE', 'Dean\'s Office', $name);
        $name = str_replace('All Dean', 'Dean', $name);
        $name = str_replace('SUSTAINABILITY', 'Change Leadership for Sustainability', $name);
        $name = str_replace('All Change', 'Change', $name);
        if (strpos($name,'E-IPER Program') === FALSE) {
          $name = str_replace('E-IPER', 'E-IPER Program', $name);
        }
        $name = str_replace('All E-IPER', 'E-IPER', $name);
        $name = str_replace('EARTH SYSTEMS', 'Earth Systems Program', $name);
        $name = str_replace('All Earth Systems', 'Earth Systems', $name);
        $name = str_replace('ERE', 'Energy Resources Engineering', $name);
        $name = str_replace('All Energy', 'Energy', $name);
        $name = str_replace('ESS', 'Earth System Science', $name);
        $name = str_replace('All Earth System', 'Earth System', $name);
        $name = str_replace('GEOPHYSICS', 'Geophysics', $name);
        $name = str_replace('All Geo', 'Geo', $name);
        $name = str_replace('GS', 'Geological Sciences', $name);
        $name = str_replace('All Geo', 'Geological', $name);
        $name = str_replace('Affiliated', 'Associated', $name);
        $value->setName($name);
        $value->save();
      }
    }
    $response = new HtmlResponse();
    $response->setContent('Update done.');
    return $response;
  }
}
