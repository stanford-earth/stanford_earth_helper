<?php
namespace Drupal\stanford_subsites\Overrides;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Config\ConfigFactoryOverrideInterface;
use Drupal\Core\Config\StorageInterface;

/**
 * Example configuration override.
 */
class StanfordSubsitesMenuConfigOverrides implements ConfigFactoryOverrideInterface {

  /**
   * {@inheritdoc}
   */
  public function loadOverrides($names) {
    $overrides = array();
    if (in_array('node.type.stanford_subsite', $names)) {

      // Always add the main menu.
      $available_menus = ['main'];
      // Get a list of all subsite parents.
      $query = \Drupal::service('entity.query')
        ->get('node')
        ->condition('type', "stanford_subsite")
        ->condition('field_s_subsite_ref', NULL, 'IS NULL');
      $entity_ids = $query->execute();
      foreach ($entity_ids as $sub_id) {
        $available_menus[] = "subsite-menu-" . $sub_id;
      }

      $overrides['node.type.stanford_subsite'] = [
        'third_party_settings' => [
          'menu_ui' => [
            'available_menus' => $available_menus,
          ],
        ],
      ];
    }
    return $overrides;
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheSuffix() {
    return 'StanfordSubsitesMenuConfig';
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheableMetadata($name) {
    return new CacheableMetadata();
  }

  /**
   * {@inheritdoc}
   */
  public function createConfigObject($name, $collection = StorageInterface::DEFAULT_COLLECTION) {
    return NULL;
  }

}
