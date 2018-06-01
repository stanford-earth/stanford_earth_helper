<?php

namespace Drupal\stanford_earth_capx\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Drupal\migrate\Event\MigrateEvents;
use Drupal\migrate\Event\MigratePreRowSaveEvent;
use Drupal\migrate\Event\MigratePostRowSaveEvent;
use Drupal\migrate\Row;
use Drupal\stanford_earth_capx\EarthCapxInfo;

/**
 * Class EntityTypeSubscriber.
 *
* @package Drupal\stanford_earth_capx\EventSubscriber
*/
class EarthCapxEventsSubscriber implements EventSubscriberInterface {

  /**
   * {@inheritdoc}
   *
   * @return array
   *   The event names to listen for, and the methods that should be executed.
   */
  public static function getSubscribedEvents() {
    return [
      MigrateEvents::POST_ROW_SAVE => 'migratePostRowSave',
    ];
  }

  /**
   * React to a migrate POST_ROW_SAVE event.
   *
   * @param \Drupal\migrate\Event\MigratePostRowSaveEvent $event
   *
   */
  public function migratePostRowSave(MigratePostRowSaveEvent $event) {
    $source = $event->getRow()->getSource();
    $sunetid = '';
    if (!empty($source['sunetid'])) $sunetid = $source['sunetid'];
    $info = new EarthCapxInfo($sunetid);
    $info->setInfoRecord($source);
  }
  
}
