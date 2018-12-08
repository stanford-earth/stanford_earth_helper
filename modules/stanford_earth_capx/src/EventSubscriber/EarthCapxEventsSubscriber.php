<?php

namespace Drupal\stanford_earth_capx\EventSubscriber;

use Drupal\migrate\Event\MigrateRollbackEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Drupal\migrate\MigrateException;
use Drupal\migrate\Event\MigrateEvents;
use Drupal\migrate\Event\MigratePreRowSaveEvent;
use Drupal\migrate\Event\MigratePostRowSaveEvent;
use Drupal\migrate\Event\MigrateRowDeleteEvent;
use Drupal\stanford_earth_capx\EarthCapxInfo;
use Symfony\Component\HttpKernel\Exception\HttpException;

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
      MigrateEvents::PRE_ROW_SAVE => 'migratePreRowSave',
      MigrateEvents::POST_ROW_SAVE => 'migratePostRowSave',
      MigrateEvents::POST_ROW_DELETE => 'migratePostRowDelete',
      MigrateEvents::PRE_ROLLBACK => 'migratePreRollback',
    ];
  }

  public function migratePreRollback(MigrateRollbackEvent $event) {

    // This event gets thrown for all migrations, so check that first.
    if (strpos($event->getMigration()->id(), 'earth_capx_importer') !== FALSE) {
      drupal_set_message('You may not roll back a profiles migration!');
      throw new HttpException('403','You may not roll back a profiles migration, buddy!');
      //throw new MigrateException('You can not roll back a profiles migration.', 0, NULL, 3, 2);
    }

  }

  private function getWorkgroup($event) {
    $urls = $event->getRow()->getSourceProperty('urls');
    $wg = '';
    if (!empty($urls)) {
      $url = reset($urls);
      $start = strpos($url,'privGroups=');
      if ($start !== FALSE) {
        $end = strpos($url,'&', $start);
        if ($end !== FALSE) {
          $start += 11;
          $wg = substr($url, $start, $end - $start );
        }
      }
    }
    return $wg;
  }

  /**
   * React to a migrate PRE_ROW_SAVE event.
   *
   * Decide if we really need to re-import a profile.
   *
   * @param \Drupal\migrate\Event\MigratePreRowSaveEvent $event
   *   Information about the migration source row being processed.
   */
  public function migratePreRowSave(MigratePreRowSaveEvent $event) {

    // This event gets thrown for all migrations, so check that first.
    if (strpos($event->getMigration()->id(), 'earth_capx_importer') !== 0) {
      return;
    }

    $wg = $this->getWorkgroup($event);
    // Get the row in question.
    $row = $event->getRow();
    // See if we already have migration information for this profile.
    $sunetid = $row->getSourceProperty('sunetid');
    $info = new EarthCapxInfo($sunetid);
    $photo_id = 0;
    $photo_field = $row->getDestinationProperty('field_s_person_image');
    if (!empty($photo_field['target_id'])) {
      $photo_id = $photo_field['target_id'];
    }
    // Check source data in the row against etag and photo info stored in table.
    $okay = $info->getOkayToUpdateProfile($row->getSource(), $photo_id, $wg);

    // If okay and a first time profile import for an existing SAML login...
    // We need to store a migration id_map record for the user.
    if ($okay && $info->isNew()) {
      $existing_user = user_load_by_name($sunetid);
      if ($existing_user !== FALSE) {
        $id_map = $event->getMigration()->getIdMap();
        $map_row = $id_map->getRowBySource(['sunetid' => $sunetid]);
        if (empty($map_row['destid1'])) {
          $id_map->saveIdMapping($row, [$existing_user->id()], NULL, 0);
        }
      }
    }

    // If not okay, throw an exception to skip this record.
    if (!$okay) {
      throw new MigrateException(NULL, 0, NULL, 3, 2);
    }
  }

  /**
   * React to a migrate POST_ROW_SAVE event.
   *
   * Save information that we will need to determine whether to reimport.
   *
   * @param \Drupal\migrate\Event\MigratePostRowSaveEvent $event
   *   Contains information about the migration source row being saved.
   */
  public function migratePostRowSave(MigratePostRowSaveEvent $event) {

    // This event gets thrown for all migrations, so check that first.
    if (strpos($event->getMigration()->id(), 'earth_capx_importer') !== 0) {
      return;
    }

    // Save CAP API etag and other information so we don't later re-import
    // a profile that has not changed.
    $row = $event->getRow();
    $destination = 0;
    $destination_ids = $event->getDestinationIdValues();
    if (!empty($destination_ids[0])) {
      $destination = intval($destination_ids[0]);
    }

    // Get the fid of the profile photo.
    $photoId = 0;
    $dest_values = $row->getDestinationProperty('field_s_person_image');
    if (!empty($dest_values['target_id'])) {
      $photoId = intval($dest_values['target_id']);
    }

    // Get the workgroup from the import event
    $wg = $this->getWorkgroup($event);

    $info = new EarthCapxInfo($row->getSourceProperty('sunetid'));
    $info->setInfoRecord($row->getSource(), $destination, $photoId, $wg);

    // update the person search terms based on the workgroup
    if (!empty($destination)) {

      if (!empty($wg)) {
        // next look up the workgroup in the profile_workgroups vocabulary
        $properties = [
          'vid' => 'profile_workgroups',
          'name' => $wg,
        ];
        $wg_terms = \Drupal::entityTypeManager()
          ->getStorage('taxonomy_term')
          ->loadByProperties($properties);

        // if we find it, record the search terms for the workgroup
        $term_array = [];
        if (!empty($wg_terms)) {
          $entity = reset($wg_terms);
          $search_terms = $entity->field_people_search_terms;
          foreach ($search_terms as $search_term) {
            if ($search_term->entity) {
              $id = $search_term->entity->id();
              $term_array[intval($id)] = $id;
            }
          }
        }

        $account = \Drupal\user\Entity\User::load($destination);
        if (empty($account->getPassword())) {
          $account->setPassword(user_password());
          $account->save();
        }

        // if we have search terms, load the user account and add them
        // to the field_profile_search_terms taxonomy reference field
        if (!empty($term_array)) {
          $termids = [];
          // $account = \Drupal\user\Entity\User::load($destination);
          $saved_terms = $account->get('field_profile_search_terms')->getValue();
          if (!empty($saved_terms)) {
            foreach ($saved_terms as $saved_term) {
              $termid = $saved_term['target_id'];
              $term_array[intval($termid)] = $termid;
            }
          }
          foreach($term_array as $key => $tid) {
            $termids[] = ['target_id' => $tid];
          }
          $account->field_profile_search_terms = $termids;
          if (strpos($wg,'faculty') !== FALSE) {
            $account->addRole('faculty');
          } else if (strpos($wg, 'staff') !== FALSE) {
            $account->addRole('staff');
          } else if (strpos($wg, 'student') !== FALSE) {
            $account->addRole('student');
          } else if (strpos($wg, 'postdoc') !== FALSE) {
            $account->addRole('student');
          }
          $account->save();
        }
      }
    }

  }

  /**
   * React to a migrate POST_ROW_DELETE event.
   *
   * If a rollback removes a profile, we want to delete it from our info table.
   *
   * @param \Drupal\migrate\Event\MigrateRowDeleteEvent $event
   *   $event Contains information on which profile by user id is deleted.
   */
  public function migratePostRowDelete(MigrateRowDeleteEvent $event) {

    // This event gets thrown for all migrations, so check that first.
    if (strpos($event->getMigration()->id(), 'earth_capx_importer') !== 0) {
      return;
    }

    $destination_ids = $event->getDestinationIdValues();
    $destination = 0;
    if (!empty($destination_ids['uid'])) {
      $destination = intval($destination_ids['uid']);
    }
    EarthCapxInfo::delete($destination);

  }

}
