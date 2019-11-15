<?php

namespace Drupal\stanford_earth_capx\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\migrate\Plugin\MigrationPluginManager;
use Drupal\Core\Database\Connection;
use Drupal\Core\Config\ConfigFactory;
use Drupal\Core\StringTranslation\StringTranslationTrait;
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
      $this->db->query("UPDATE {migrate_info_earth_capx_importer} " .
        "SET photo_timestamp = 0")->execute();
    }

    return [
      '#type' => 'markup',
      '#markup' => $this->t('Run using drush migrate:import.'),
    ];
  }

}
