<?php

namespace Drupal\stanford_earth_migrate_extend\Plugin\migrate_plus\data_parser;

use Drupal\migrate_plus\Plugin\migrate_plus\data_parser\SimpleXml;
use Drupal\migrate\MigrateException;
use Drupal\stanford_earth_events\EarthEventsLock;

/**
 * Obtain XML data for migration using the extended SimpleXML API.
 *
 * @DataParser(
 *   id = "stanford_earth_simple_xml",
 *   title = @Translation("Stanford Earth Simple XML")
 * )
 */
class StanfordEarthSimpleXml extends SimpleXml {

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
    // Clear XML error buffer. Other Drupal code that executed during the
    // migration may have polluted the error buffer and could create false
    // positives in our error check below. We are only concerned with errors
    // that occur from attempting to load the XML string into an object here.
    libxml_clear_errors();

    // Code from ksharp starts here. Intercept a MigrateException from getting
    // feed content and kill a lock if we have it to cancel orphan deletion.
    try {
      $xml_data = $this->getDataFetcherPlugin()->getResponseContent($url);
    }
    catch (MigrateException $migrateException) {
      // See if we have a lock by looking for a lockid stored in our session.
      /** @var \Drupal\Core\TempStore\PrivateTempStore $session */
      $session = \Drupal::service('tempstore.private')->get('EarthEventsInfo');
      $mylockid = $session->get('eartheventslockid');
      if (!empty($mylockid)) {
        // See if there is a lock in the semaphore table that matches our id.
        $lock = new EarthEventsLock(\Drupal::database());
        $actual = $lock->getExistingLockId('EarthEventsLock');
        // If they match, release the lock.
        if (!empty($actual) && $actual === $mylockid) {
          $lock->releaseEventLock('EarthEventsInfo', $mylockid);
          $session->delete('eartheventslockid');
        }
      }
      // Continue propagating the exception.
      throw new MigrateException($migrateException->getMessage());
    }
    // End code from ksharp.
    $xml = simplexml_load_string($xml_data);

    // If there were errors return false.
    $errors = libxml_get_errors();
    if ($errors) {
      return FALSE;
    }

    $this->registerNamespaces($xml);
    $xpath = $this->configuration['item_selector'];
    $this->matches = $xml->xpath($xpath);

    // Everything went well.
    return TRUE;
  }

}
