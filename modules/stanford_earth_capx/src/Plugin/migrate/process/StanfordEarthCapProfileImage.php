<?php

namespace Drupal\stanford_earth_capx\Plugin\migrate\process;

use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\StreamWrapper\StreamWrapperManagerInterface;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\Plugin\MigrateProcessInterface;
use Drupal\migrate\Row;
use Drupal\migrate_file\Plugin\migrate\process\FileImport;
use Drupal\file\Entity\File;
use Drupal\stanford_earth_capx\EarthCapxInfo;

/**
 * Imports an image from a Stanford Profiles CAP API url.
 *
 * Extends the regular file_import plugin but adds the following additional
 * optional configuration keys.
 * - alt: The alt attribute for the image
 * - title: The title attribute for the image
 * - width: The width of the image
 * - height: The height of the image.
 *
 * All of the above fields fields support copying destination values. These are
 * indicated by a starting @ sign. Values using @ must be wrapped in quotes.
 * (the same as it works with the 'source' key).
 *
 * Additionally, a special value is available to represent the filename of
 * the file '!file'. Useful to just populate the alt or title field with the
 * filename.
 *
 * @see Drupal\migrate\Plugin\migrate\process\Get
 *
 * @see Drupal\migrate_file\Plugin\migrate\process\FileImport.php
 *
 * @see \Drupal\migrate\Plugin\MigrateProcessInterface
 *
 * Example:
 *
 * @code
 * destination:
 *   plugin: entity:node
 * source:
 *   # assuming we're using a source plugin that lets us define fields like this
 *   item_selector: values
 *   fields:
 *     -
 *       name: image
 *       label: 'Main Image'
 *       selector: profilePhotos/bigger/url
 *   constants:
 *     file_destination: 'public://path/to/save/'
 * process:
 *   title:
 *     plugin: default_value
 *     default_value: Some Title
 *   uid:
 *     plugin: default_value
 *     default_value: 1
 *   field_image:
 *     plugin: stanford_earth_cap_profile_image
 *     source: image
 *     destination: constants/file_destination
 *     uid: @uid
 *     title: title
 *     alt: !file
 *     skip_on_missing_source: true
 *
 * @endcode
 *
 * @MigrateProcessPlugin(
 *   id = "stanford_earth_cap_profile_image"
 * )
 */
class StanfordEarthCapProfileImage extends FileImport {

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, array $plugin_definition, StreamWrapperManagerInterface $stream_wrappers, FileSystemInterface $file_system, MigrateProcessInterface $download_plugin) {
    $configuration += [
      'title' => NULL,
      'alt' => NULL,
      'width' => NULL,
      'height' => NULL,
      'destination_property_entity' => NULL,
      'destination_property_bundle' => NULL,
    ];
    parent::__construct($configuration, $plugin_id, $plugin_definition, $stream_wrappers, $file_system, $download_plugin);
  }

  /**
   * {@inheritdoc}
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {

    // See if we already have the current profile photo.
    $info = new EarthCapxInfo($row->getSourceProperty('sunetid'));
    $defaultProfileImage = $info::getDefaultProfileMediaEntity();
    $profile_photo = $row->getSourceProperty('profile_photo');
    if (empty($profile_photo)) {
      if (empty($defaultProfileImage['default_mid'])) {
        return NULL;
      } else {
        return $defaultProfileImage['default_mid'];
      }
    }
    else {
      /*
       * $fid = 0; $ts = 0;
       * $account = NULL;
       * if (sunetid from source) {
       *  $account = load account for sunetid
       *  if (!empty(account)) {
       *    get mid from account
       *    if not empty mid and mid <> default_mid {
       *    }
       *  }
       * }
       *
       */
      $fid = 0;
      $ts = 0;
      $account = NULL;
      if (!empty($row->getSourceProperty('sunetid'))) {
        $account = user_load_by_name($row->getSourceProperty('sunetid'));
        if (!empty($account)) {
          $val = $account->get('field_s_person_media')->getValue();
          if (!empty($val[0]['target_id']) && $val[0]['target_id'] !== $default_mid) {
            $storage = \Drupal::entityTypeManager()->getStorage('media');
            $mentity = $storage->load($val[0]['target_id']);
            if (!empty($mentity)) {
              $storage->delete([$mentity]);
            }
          }
          $account->get('field_s_person_media')->applyDefaultValue();

        }

      }
    }

    $photoId = $info->currentProfilePhotoId($row->getSource());
    if ($photoId) {
      $value = ['target_id' => $photoId];
    }
    else {
      $this->configuration['id_only'] = FALSE;
      $value = parent::transform($value, $migrate_executable, $row, $destination_property);
    }

    if ($value && is_array($value)) {
      // Add the image field specific sub fields.
      foreach (['title', 'alt', 'width', 'height'] as $key) {
        if ($property = $this->configuration[$key]) {
          if ($property == '!file') {
            $file = File::load($value['target_id']);
            $value[$key] = $file->getFilename();
          }
          else {
            $value[$key] = $this->getPropertyValue($property, $row);
          }
        }
      }
      return $value;
    }
    else {
      return NULL;
    }
  }

}
