<?php

namespace Drupal\stanford_earth_events\Plugin\migrate_plus\data_parser;

use Drupal\migrate_plus\Plugin\migrate_plus\data_parser\Json;
use Drupal\migrate\MigrateException;
use Drupal\stanford_earth_migrate_extend\EarthMigrationLock;
use Drupal\stanford_earth_events\EarthEventsInfo;

/**
 * Obtain JSON data for migration using this extension of migrate_plus Json API.
 *
 * @DataParser(
 *   id = "stanford_earth_localist_json",
 *   title = @Translation("Stanford Earth Localist JSON")
 * )
 */
class StanfordEarthLocalistJson extends Json {

  /**
   * Index in the url array of the current url.
   *
   * @var string
   */
  protected $activeUrl;

  /**
   * Return the protected activeUrl index into the urls array.
   */
  public function getActiveUrl() {
    return $this->activeUrl;
  }

  /**
   * {@inheritdoc}
   */
  protected function openSourceUrl($url) {
    // Intercept a MigrateException from the parent getting feed content
    // and kill a lock if we have it to cancel orphan deletion.
    try {
      parent::openSourceUrl($url);
    }
    catch (MigrateException $migrateException) {
      // See if we have a lock by looking for a lockid stored in our session.
      /** @var \Drupal\Core\TempStore\PrivateTempStore $session */
      $session = \Drupal::service('tempstore.private')
        ->get(EarthMigrationLock::EARTH_MIGRATION_LOCK_NAME);
      $mylockid = $session->get(EarthMigrationLock::EARTH_MIGRATION_LOCK_NAME);
      if (!empty($mylockid)) {
        // See if there is a lock in the semaphore table that matches our id.
        $lock = new EarthMigrationLock(\Drupal::database());
        $actual = $lock->getExistingLockId();
        // If they match, release the lock.
        if (!empty($actual) && $actual === $mylockid) {
          $lock->releaseEarthMigrationLock($mylockid);
          $session->delete(EarthMigrationLock::EARTH_MIGRATION_LOCK_NAME);
        }
      }
      // Continue propagating the exception.
      throw new MigrateException($migrateException->getMessage());
    }
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  protected function fetchNextRow() {
    $current = $this->iterator->current();
    if ($current) {
      foreach ($this->fieldSelectors() as $field_name => $selector) {
        $field_data = $current;
        $field_selectors = explode('/', trim($selector, '/'));
        foreach ($field_selectors as $field_selector) {
          if (is_array($field_data) && array_key_exists($field_selector, $field_data)) {
            $field_data = $field_data[$field_selector];
          }
          else {
            $field_data = '';
          }
        }
        $field_data = EarthEventsInfo::tweakLocalistFieldData($field_name,
          $field_data);
        $this->currentItem[$field_name] = $field_data;
        if ($field_name == 'field_event_status' && $field_data !== 'Live') {
          if (!empty($this->currentItem['title'])) {
            $this->currentItem['title'] .= ' - ' . $field_data;
          }
        }
      }
      if (!empty($this->configuration['include_raw_data'])) {
        $this->currentItem['raw'] = $current;
      }
      $this->iterator->next();
    }
  }
}
