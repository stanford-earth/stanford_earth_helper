<?php
namespace Drupal\stanford_subsites\Overrides;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Config\ConfigFactoryOverrideInterface;
use Drupal\Core\Config\StorageInterface;
use Drupal\Core\Entity\EntityTypeManager;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * Configuration override class for subsite module.
 *
 * This class has a task that overrides the available menus that a content type
 * has. As the config setting for the menus are stored on the content type
 * itself, and each new parent subsite creates a menu, and subsite nodes are
 * content, it is not easy or maintainable to keep the content type's settings
 * in sync. For this reason we alter the settings dynamically using the config
 * override api.
 */
class StanfordSubsitesMenuConfigOverrides implements ConfigFactoryOverrideInterface {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Cached storage for available overrides.
   *
   * @var array
   */
  protected $overrides;

  /**
   * Boolean flag on wether or not the overrides check has already been called.
   *
   * @var bool
   */
  protected $called;

  /**
   * Dependency injection.
   *
   * @param ContainerInterface $container
   *   See docs on type.
   * @param EntityTypeInterface $entity_type
   *   See docs on type.
   *
   * @return static
   *   A static class.
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type) {
    return new static(
      $entity_type,
      $container->get('entity_type.manager'),
      $container->get('entity_type.manager')->getStorage($entity_type->id())
    );
  }

  /**
   * {@inheritdoc}
   *
   * @param EntityTypeManagerInterface $entity_type_manager
   *   See Drupal API Docs.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public function loadOverrides($names) {
    $overrides = array();

    // We only want to act on one config setting so do a check for it.
    if (in_array('node.type.stanford_subsite', $names)) {

      // The SQL query below caused an infinite loop as it loads config too.
      // This prevents it from being called and returns the cached results
      // when available.
      $this->called++;
      if ($this->called > 1) {
        if ($this->overrides) {
          return $this->overrides;
        }
        return $overrides;
      }

      // Always add the main menu as an option.
      $available_menus = ['main'];

      // Get a list of all subsite parents.
      $query = $this->entityTypeManager->getStorage('node')->getQuery();
      $query->condition('type', "stanford_subsite")
            ->condition('field_s_subsite_ref', NULL, 'IS NULL');
      $entity_ids = $query->execute();

      // Each parent subsite will have a menu available that uses their id.
      foreach ($entity_ids as $sub_id) {
        $available_menus[] = "subsite-menu-" . $sub_id;
      }

      // These settings show up as third party settings when in use.
      // This differes from looking at the config itself but this is what works.
      $overrides['node.type.stanford_subsite'] = [
        'third_party_settings' => [
          'menu_ui' => [
            'available_menus' => $available_menus,
          ],
        ],
      ];

      // Cache the overrides so that we can simply return them instead of
      // having to process them all over again.
      $this->overrides = $overrides;
    }

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
