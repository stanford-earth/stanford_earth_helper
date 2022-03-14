<?php

namespace Drupal\stanford_earth_events\Plugin\migrate_plus\data_parser;

use Drupal\Component\Serialization\Json;
use Drupal\migrate_plus\Plugin\migrate_plus\data_parser\SimpleXml;
use Drupal\migrate\MigrateException;
use Drupal\stanford_earth_migrate_extend\EarthMigrationLock;
use Drupal\stanford_earth_events\EarthEventsInfo;

/**
 * Obtain XML data for migration using the extended SimpleXML API.
 *
 * @DataParser(
 *   id = "stanford_earth_localist_xml",
 *   title = @Translation("Stanford Earth Localist XML")
 * )
 */
class StanfordEarthLocalistXml extends SimpleXml {

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
    $target_element = array_shift($this->matches);
    if ($target_element !== FALSE && !is_null($target_element)) {
      $evtTitle = '';
      foreach ($target_element->xpath('title') as $value) {
        $evtTitle = ((string) $value);
        break;
      }
      if (!empty($evtTitle)) {
        $datePos = strpos($evtTitle, 'interested');
        if ($datePos !== FALSE) {
          $evtTitle = substr($evtTitle, $datePos + 12);
          $datePos = strpos($evtTitle, ':');
          if ($datePos !== FALSE) {
            $evtTitle = substr($evtTitle, $datePos + 2);
          }
        }
      }
      $title = $evtTitle;
      $title = str_replace('"', '', $title);
      $title = str_replace("'", "", $title);
      $title = str_replace(',', '', $title);
      $atPos = strrpos($title, ' at ');
      if ($atPos !== false) {
        $title = substr($title, 0, $atPos);
      }
      $title = rawurlencode($title);
      $instanceId = '';
      foreach ($target_element->xpath('guid') as $value) {
        $instanceId = ((string) $value);
        break;
      }
      if (!empty($instanceId)) {
        $instPos = strpos($instanceId, 'EventInstance_');
        if ($instPos !== FALSE) {
          $instanceId = substr($instanceId, $instPos + 14);
        }
      }
      $eventUrl = "https://events.stanford.edu/api/2/events/search?search=%22" .
        $title . '%22&days=365';
      $contents = @file_get_contents($eventUrl);
      $current = [];
      if (!empty($contents)) {
        $json = Json::decode($contents);
        if (!empty($json['events']) && is_array($json['events'])) {
          foreach ($json['events'] as $event) {
            if (!empty($event['event'] &&
              is_array($event['event']) &&
              !empty($event['event']['title']))) {
              if (strpos($evtTitle, $event['event']['title']) !== false) {
                $evtId = '';
                if (!empty($event['event']['event_instances'][0]['event_instance']['id'])) {
                  $evtId = ((string) $event['event']['event_instances'][0]['event_instance']['id']);
                  if ($evtId === $instanceId) {
                    $current = $event['event'];
                    break;
                  }
                }
              }
             }
          }
        }
      }
      if (!empty($current)) {
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
        }
      }
      else {
        $this->currentItem['guid'] = '99999999999999';
        $this->currentItem['title'] = $evtTitle;
        $this->currentItem['description'] = 'Unable to import event.';
        $this->currentItem['origin_id'] = 'EARTH-999999';
      }
    }
  }
}
