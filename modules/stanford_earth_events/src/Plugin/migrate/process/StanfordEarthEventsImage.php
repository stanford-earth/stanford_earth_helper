<?php

namespace Drupal\stanford_earth_events\Plugin\migrate\process;

use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\StreamWrapper\StreamWrapperManagerInterface;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\Plugin\MigrateProcessInterface;
use Drupal\migrate\Row;
use Drupal\migrate_file\Plugin\migrate\process\FileImport;
use Drupal\file\Entity\File;
use Drupal\stanford_earth_events\EarthEventsInfo;
use Drupal\node\Entity\Node;

/**
 * Imports an image from a Stanford Events Feed url.
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
 *   id = "stanford_earth_events_image"
 * )
 */
class StanfordEarthEventsImage extends FileImport {

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

    // Get the URL to the profile image from the CAP API.
    $event_photo = $row->getSourceProperty('field_s_event_image');

    // If there is no profile photo URL, return the default media entity id.
    if (empty($event_photo)) {
      return EarthEventsInfo::getDefaultEventMediaEntity();
    }
    else {
      // The parent will download the image (if necessary) and get its fid.
      $this->configuration['id_only'] = FALSE;
      $value = parent::transform($value, $migrate_executable, $row,
        $destination_property);
      // Add the image field specific sub fields.
      foreach (['title', 'alt', 'width', 'height'] as $key) {
        if ($property = $this->configuration[$key]) {
          if ($property == '!file') {
            $file = File::load($value['target_id']);
            $value[$key] = 'Event image ' . $file->getFilename();
          }
          else {
            $value[$key] = $this->getPropertyValue($property, $row);
          }
        }
      }
      // Force alt tag to empty string for now.
      $value['alt'] = '';
      // Assume we will need to create a new media entity.
      $mid = NULL;
      // See if there is already an event node associated with this event.
      $node = NULL;
      if (!empty($row->getSourceProperty('nid'))) {
        $node = Node::load($row->getSourceProperty('nid'));
        if (!empty($node)) {
          // If we have an event node, and it has its media entity set, and...
          // It's value is not the default media entity id, then use that.
          $val = $node->get('field_s_event_media')->getValue();
          if (!empty($val[0]['target_id']) &&
            $val[0]['target_id']
            !== EarthEventsInfo::getDefaultEventMediaEntity($node)) {
            $mid = $val[0]['target_id'];
          }
        }
      }
      // If we got an existing media entity, load it, otherwise create new.
      $storage = \Drupal::entityTypeManager()->getStorage('media');
      $media_entity = NULL;
      if (!empty($mid)) {
        $media_entity = $storage->load($mid);
        if (empty($media_entity)) {
          $mid = NULL;
        }
      }
      if (empty($mid)) {
        $media_entity = $storage->create(['bundle' => 'image']);
      }
      $media_entity->get('field_media_image')->setValue($value);
      $media_entity->save();
      return $media_entity->id();
    }

  }

}
