<?php

namespace Drupal\stanford_earth_events;

use Drupal\Core\Database\Connection;
use Drupal\Core\Lock\DatabaseLockBackend;

/**
 * Defines the persistent database lock backend for Events.
 *
 * This backend differs from core PersistentDatabaseLockBackend in that
 * it does not preset the lockid to "persistent".
 *
 * @ingroup lock
 */
class EarthEventsLock extends DatabaseLockBackend {

  /**
   * Constructs a new EarthEventsLock.
   *
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection.
   */
  public function __construct(Connection $database) {
    // Do not call the parent constructor to avoid registering a shutdown
    // function that releases all the locks at the end of a request.
    $this->database = $database;
  }

  /**
   * {@inheritdoc}
   */
  public function getExistingLockId($name) {
    $this->normalizeName($name);
    try {
      $lock = $this->database->query('SELECT value FROM {semaphore} WHERE name = :name', [':name' => $name])
        ->fetchAssoc();
      $lockId = $lock['value'];
    }
    catch (\Exception $e) {
      $lockId = FALSE;
    }
    return $lockId;
  }

  /**
   * {@inheritdoc}
   */
  public function valid($name) {
    $this->normalizeName($name);
    $valid = TRUE;
    try {
      $lock = $this->database->query('SELECT expire FROM {semaphore} WHERE name = :name', [':name' => $name])
        ->fetchAssoc();
      $expire = (float) $lock['expire'];
      $now = microtime(TRUE);
      if ($now > $expire) {
        $valid = FALSE;
      }
    }
    catch (\Exception $e) {
      $valid = FALSE;
    }

    return $valid;
  }

  /**
   * {@inheritdoc}
   */
  public function releaseEventLock($name, $lockid) {
    $name = $this->normalizeName($name);

    unset($this->locks[$name]);
    try {
      $this->database->delete('semaphore')
        ->condition('name', $name)
        ->condition('value', $lockid)
        ->execute();
    }
    catch (\Exception $e) {
      $this->catchException($e);
    }
  }

}
