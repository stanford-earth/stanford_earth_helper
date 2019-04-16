<?php

namespace Drupal\stanford_earth_events;

use Drupal\Core\Database\Connection;

/**
 * Defines the persistent database lock backend for Events.
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

}

