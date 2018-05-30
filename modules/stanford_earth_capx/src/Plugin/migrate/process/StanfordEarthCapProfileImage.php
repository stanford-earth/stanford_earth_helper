<?php

namespace Drupal\stanford_earth_capx\Plugin\migrate\process;

use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\StreamWrapper\StreamWrapperManagerInterface;
use Drupal\field\Entity\FieldConfig;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\Plugin\MigrateProcessInterface;
use Drupal\migrate\Plugin\Migration;
use Drupal\migrate\Row;
use Drupal\migrate_file\Plugin\migrate\process\FileImport;
use Drupal\file\Entity\File;
use Symfony\Component\DependencyInjection\ContainerInterface;
use GuzzleHttp\Exception\ClientException;

/**
 * Imports an image from a Stanford Profiles CAP API url 
 *
 * Extends the regular file_import plugin but adds the following additional
 * optional configuration keys.
 * - alt: The alt attribute for the image
 * - title: The title attribute for the image
 * - width: The width of the image
 * - height: The height of the image
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
 *
 * @endcode
 *
 * @see \Drupal\migrate\Plugin\MigrateProcessInterface
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

    //$field_config = FieldConfig::loadByName($this->configuration['destination_property_entity'],
    //    $this->configuration['destination_property_bundle'],$destination_property);
   // return NULL;
    //$this->configuration['destination'] = "public://kenneth-sharpo.jpg";
    // Ignore this setting.
    $this->configuration['id_only'] = FALSE;
    // Run the parent transform to do all the file handling.
    $value = parent::transform($value, $migrate_executable, $row, $destination_property);

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
