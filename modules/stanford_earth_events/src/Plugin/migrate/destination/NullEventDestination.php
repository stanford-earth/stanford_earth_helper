<?php

namespace Drupal\stanford_earth_events\Plugin\migrate\destination;

use Drupal\migrate\Plugin\migrate\destination\DestinationBase;
use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\migrate\Row;

/**
 * Provides null destination plugin.
 *
 * @MigrateDestination(
 *   id = "null_event",
 *   requirements_met = true
 * )
 */
class NullEventDestination extends DestinationBase {

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, MigrationInterface $migration) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $migration);
    $this->supportsRollback = FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function getIds() {
    return [
      'nid' => [
        'type' => "integer",
        'unsigned' => TRUE,
        'size' => "normal",
        'min' => "",
        'max' => "",
        'prefix' => "",
        'suffix' => "",
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function fields(MigrationInterface $migration = NULL) {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function import(Row $row, array $old_destination_id_values = []) {
    return [1];
  }

  /**
   * {@inheritdoc}
   */
  public function processedCount() {
    return 1;
  }

  /**
   * {@inheritdoc}
   */
  public function importedCount() {
    return 1;
  }

  /**
   * {@inheritdoc}
   */
  public function updateCount() {
    return 1;
  }

}
