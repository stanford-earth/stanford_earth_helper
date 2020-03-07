<?php

namespace Drupal\stanford_earth_migrate_extend;

use Drupal\Core\Database\Connection;
use Drupal\Core\Lock\DatabaseLockBackend;

/**
 * Defines a persistent database lock backend for Stanford Earth migrations.
 *
 * This backend differs from core PersistentDatabaseLockBackend in that
 * it does not preset the lockid to "persistent".
 *
 * @ingroup lock
 */
class EarthMigrationLock extends DatabaseLockBackend {

  const EARTH_MIGRATION_LOCK_NAME = "EarthMigrationLock";

  /**
   * Lock name.
   *
   * @var string
   *   The name of the lock.
   */
  protected $name;

  /**
   * Constructs a new EarthMigrationLock.
   *
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection object.
   * @param string $lockName
   *   The name of the lock.
   */
  public function __construct(Connection $database,
                              string $lockName = NULL) {
    // Do not call the parent constructor to avoid registering a shutdown
    // function that releases all the locks at the end of a request.
    $this->database = $database;
    if (empty($lockName)) {
      $lockName = self::EARTH_MIGRATION_LOCK_NAME;
    }
    $this->name = $this->normalizeName($lockName);
  }

  /**
   * {@inheritdoc}
   */
  public function getExistingLockId() {
    try {
      $lock = $this->database->query('SELECT value FROM {semaphore} ' .
        'WHERE name = :name', [':name' => $this->name])
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
  public function valid() {
    $valid = TRUE;
    try {
      $lock = $this->database->query('SELECT expire FROM {semaphore} ' .
        'WHERE name = :name', [':name' => $this->name])
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
  public function releaseEarthMigrationLock($lockid) {
    unset($this->locks[$this->name]);
    try {
      $this->database->delete('semaphore')
        ->condition('name', $this->name)
        ->condition('value', $lockid)
        ->execute();
    }
    catch (\Exception $e) {
      $this->catchException($e);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function acquireLock($timeout = 30.0) {
    return parent::acquire($this->name, $timeout);
  }

}
