<?php
namespace Drupal\stanford_subsites\Overrides;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Config\ConfigFactoryOverrideInterface;
use Drupal\Core\Config\StorageInterface;
use Drupal\Core\Entity\EntityTypeManager;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * Example configuration override.
 */
class StanfordSubsitesMenuConfigOverrides implements ConfigFactoryOverrideInterface {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * [protected description]
   * @var [type]
   */
  protected $overrides;

  /**
   * [createInstance description]
   * @param  ContainerInterface  $container   [description]
   * @param  EntityTypeInterface $entity_type [description]
   * @return [type]                           [description]
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type) {
    return new static(
      $entity_type,
      $container->get('entity_type.manager'),
      $container->get('entity_type.manager')->getStorage($entity_type->id())
    );
  }

  /**
   * [__construct description]
   * @param EntityTypeInterface        $entity_type         [description]
   * @param EntityTypeManagerInterface $entity_type_manager [description]
   * @param EntityStorageInterface     $storage             [description]
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public function loadOverrides($names) {
    $overrides = array();

    // Prevent nested callbacks and multiple db query calls.
    if ($this->overrides) {
      return $this->overrides;
    }

    if (in_array('node.type.stanford_subsite', $names)) {

      // Always add the main menu.
      $available_menus = ['main'];
      // Get a list of all subsite parents.
      $query = $this->entityTypeManager->getStorage('node')->getQuery();
      $query->condition('type', "stanford_subsite")
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
    $this->overrides = $overrides;
    return $overrides;
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheSuffix() {
    return 'StanfordSubsitesMenuConfigOverrider';
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
