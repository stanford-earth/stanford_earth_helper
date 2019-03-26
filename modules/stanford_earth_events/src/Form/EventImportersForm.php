<?php

namespace Drupal\stanford_earth_events\Form;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Entity\EntityTypeManager;
use Drupal\config\Form\ConfigSingleImportForm;
use Drupal\Core\Serialization\Yaml;
use Drupal\Core\Entity\EntityManagerInterface;
use Drupal\Core\Config\StorageInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\RendererInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Drupal\Core\Config\ConfigManagerInterface;
use Drupal\Core\Lock\LockBackendInterface;
use Drupal\Core\Config\TypedConfigManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Extension\ModuleInstallerInterface;
use Drupal\Core\Extension\ThemeHandlerInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Batch\BatchBuilder;

/**
 * ListedEventsForm description.
 */
class EventImportersForm extends ConfigSingleImportForm {

  /**
   * EntityTypeManager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManager
   */
  protected $entityTypeManager;

  /**
   * Database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $db;

  /**
   * EventImportersForm constructor.
   *
   * @param \Drupal\Core\Entity\EntityManagerInterface $entity_manager
   *   The entity manager.
   * @param \Drupal\Core\Config\StorageInterface $config_storage
   *   The config storage.
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The renderer service.
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $event_dispatcher
   *   The event dispatcher used to notify subscribers of config import events.
   * @param \Drupal\Core\Config\ConfigManagerInterface $config_manager
   *   The configuration manager.
   * @param \Drupal\Core\Lock\LockBackendInterface $lock
   *   The lock backend to ensure multiple imports do not occur at the same
   *   time.
   * @param \Drupal\Core\Config\TypedConfigManagerInterface $typed_config
   *   The typed configuration manager.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
   * @param \Drupal\Core\Extension\ModuleInstallerInterface $module_installer
   *   The module installer.
   * @param \Drupal\Core\Extension\ThemeHandlerInterface $theme_handler
   *   The theme handler.
   * @param \Drupal\Core\Entity\EntityTypeManager $entityTypeManager
   *   The EntityTypeManager service.
   * @param \Drupal\Core\Database\Connection $db
   *   The core Database object.
   */
  public function __construct(EntityManagerInterface $entity_manager,
                              StorageInterface $config_storage,
                              RendererInterface $renderer,
                              EventDispatcherInterface $event_dispatcher,
                              ConfigManagerInterface $config_manager,
                              LockBackendInterface $lock,
                              TypedConfigManagerInterface $typed_config,
                              ModuleHandlerInterface $module_handler,
                              ModuleInstallerInterface $module_installer,
                              ThemeHandlerInterface $theme_handler,
                              EntityTypeManager $entityTypeManager,
                              Connection $db) {
    $this->entityTypeManager = $entityTypeManager;
    $this->db = $db;

    parent::__construct($entity_manager, $config_storage, $renderer, $event_dispatcher, $config_manager, $lock,
      $typed_config, $module_handler, $module_installer, $theme_handler);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity.manager'),
      $container->get('config.storage'),
      $container->get('renderer'),
      $container->get('event_dispatcher'),
      $container->get('config.manager'),
      $container->get('lock.persistent'),
      $container->get('config.typed'),
      $container->get('module_handler'),
      $container->get('module_installer'),
      $container->get('theme_handler'),
      $container->get('entity_type.manager'),
      $container->get('database')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'stanford_earth_events';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    $form = parent::buildForm($form, $form_state);
    // When this is the confirmation step fall through to the confirmation form.
    if ($this->data) {
      return $form;
    }

    // Create a one field form for editing the list of workgroups for profiles.
    $feeds = $this->config('migrate_plus.migration_group.earth_events')
      ->get('feeds');

    // Fetch their current values.
    $feed_values = is_array($feeds) ? implode($feeds, PHP_EOL) : $feeds;

    // Read the raw data for this config name, encode it, and display it.
    $template = $this->configStorage->read('migrate_plus.migration.earth_events_template');
    $template['migration_group'] = 'earth_events';
    if (!empty($template['uuid'])) {
      unset($template['uuid']);
    }
    $form['import']['#default_value'] = Yaml::encode($template);
    $form['config_type']['#default_value'] = 'migration';
    $form['config_type']['#disabled'] = TRUE;
    $form['advanced']['custom_entity_id']['#disabled'] = TRUE;

    $form['feeds'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Stanford Events feeds to be imported.'),
      '#default_value' => $feed_values,
      '#description' => $this->t("Enter one full Stanford Events feed url per line."),
      '#rows' => 30,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {

    // Validate that the urls are in good format and no extra whitespace has
    // been added.
    $feeds = array_filter(explode(PHP_EOL, $form_state->getValue('feeds')));
    $feeds = array_map('trim', $feeds);

    // Check for empty lines and valid urls on listed events.
    foreach ($feeds as $v) {

      // No empty lines.
      if (empty($v)) {
        $form_state->setErrorByName('feeds', $this->t('Cannot have empty lines'));
      }

      // Valid url?
      if (!UrlHelper::isValid($v, TRUE)) {
        $form_state->setErrorByName('feeds', $this->t('Invalid url included in settings'));
      }

    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {

    // Actually do the save of the new configuration.
    $feeds = array_filter(explode(PHP_EOL, $form_state->getValue('feeds')));
    $feeds = array_map('trim', $feeds);

    // Save the new configuration.
    $this->configFactory->getEditable('migrate_plus.migration_group.earth_events')
      ->set('feeds', $feeds)
      ->save();

    // Delete the old migrations.
    $eMigrations = $this->configFactory->listAll('migrate_plus.migration.earth_events_importer');
    foreach ($eMigrations as $eMigration) {
      $this->configFactory->getEditable($eMigration)->delete();
    }

    // Delete the old migration map and message tables.
    $tables = array_merge($this->db->schema()
      ->findTables('migrate_map_earth_events_importer%'),
      $this->db->schema()->findTables('migrate_message_earth_events_importer%'));
    foreach ($tables as $table) {
      $this->db->schema()->dropTable($table);
    }

    // Make sure we have a taxonomy term saved for each feed.
    foreach ($feeds as $name) {
      $properties = [
        'name' => $name,
        'vid' => 'stanford_earth_event_feeds',
      ];
      $terms = $this->entityTypeManager
        ->getStorage('taxonomy_term')
        ->loadByProperties($properties);
      if (empty($terms)) {
        $title = '';
        $contents = file_get_contents($name);
        $xml = simplexml_load_string($contents);
        $title_attr = $xml->xpath('title');
        if (!empty($title_attr)) {
          $title = $title_attr[0]->__toString();
        }
        if (!empty($title)) {
          $properties['description'] = $title;
        }
        $entity = $this->entityTypeManager
          ->getStorage('taxonomy_term')->create($properties);
        $entity->save();
      }
    }

    // Create batch creation of migrations for each feed.
    $fp_array = Yaml::decode($form_state->getValue('import'));
    $batch_builder = new BatchBuilder();
    $batch_builder->setTitle(t('Create Event Migrations'));
    $batch_builder->setInitMessage(t('Creating Stanford Event import migrations for each requested feed.'));
    foreach ($feeds as $feed_idx => $feed) {
      // Create migration configuration in batch mode.
      $batch_builder->addOperation(
        [
          $this,
          'earthEventCreateFeedMigration',
        ],
        [
          $form,
          $form_state,
          $feed,
          $fp_array,
          $feed_idx,
        ]);
    }
    batch_set($batch_builder->toArray());
  }

  /**
   * Create migration config.
   *
   * @param array $form
   *   Form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Form State array.
   * @param string $feed
   *   Feed url.
   * @param array $fp_array
   *   Migration definition array.
   * @param int $feed_idx
   *   Index number for migration.
   */
  public function earthEventCreateFeedMigration(array $form,
                                                FormStateInterface $form_state,
                                                string $feed,
                                                array $fp_array,
                                                int $feed_idx) {
    $fp_array['id'] = 'earth_events_importer_' .
      str_pad(strval($feed_idx), 3, "0",
        STR_PAD_LEFT);
    $fp_array['source']['urls'] = [$feed];
    $fp_array['label'] = 'Events for ' . $feed;
    $form_state->setValue('import', Yaml::encode($fp_array));
    parent::validateForm($form, $form_state);
    $config_importer = $form_state->get('config_importer');
    $config_importer->import();
    drupal_set_message($this->t('Stanford events import for feed %feed configured.', [
      '%feed' => $feed,
    ]));
  }

}
