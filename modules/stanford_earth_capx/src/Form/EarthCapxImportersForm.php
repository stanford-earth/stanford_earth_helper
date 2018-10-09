<?php

namespace Drupal\stanford_earth_capx\Form;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\config\Form\ConfigSingleImportForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Entity\EntityTypeManager;
use Drupal\Core\Serialization\Yaml;
use Drupal\Core\Entity\EntityManagerInterface;
use Drupal\Core\Config\StorageInterface;
use Drupal\Core\Render\RendererInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Drupal\Core\Config\ConfigManagerInterface;
use Drupal\Core\Lock\LockBackendInterface;
use Drupal\Core\Config\TypedConfigManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Extension\ModuleInstallerInterface;
use Drupal\Core\Extension\ThemeHandlerInterface;
use Drupal\taxonomy\Entity;
use Drupal\Core\Database\Connection;

/**
 * ListedEventsForm description.
 */
class EarthCapxImportersForm extends ConfigSingleImportForm {

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
   *   The lock backend to ensure multiple imports do not occur at the same time.
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
   *   The core Database object
   */
  public function __construct(EntityManagerInterface $entity_manager, StorageInterface $config_storage,
                              RendererInterface $renderer, EventDispatcherInterface $event_dispatcher,
                              ConfigManagerInterface $config_manager, LockBackendInterface $lock,
                              TypedConfigManagerInterface $typed_config, ModuleHandlerInterface $module_handler,
                              ModuleInstallerInterface $module_installer, ThemeHandlerInterface $theme_handler,
                              EntityTypeManager $entityTypeManager, Connection $db) {
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
    return 'stanford_earth_capx_importers';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'migrate_plus.migration_group.earth_capx',
    ];
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
    $wgs = $this->config('migrate_plus.migration_group.earth_capx')->get('workgroups');

    // Fetch their current values.
    $wg_values = is_array($wgs) ? implode($wgs, PHP_EOL) : $wgs;

    // Read the raw data for this config name, encode it, and display it.
    $template = $this->configStorage->read('migrate_plus.migration.earth_capx_template');
    $template['migration_group'] = 'earth_capx';
    if (!empty($template['uuid'])) {
      unset($template['uuid']);
    }
    $form['import']['#default_value'] = Yaml::encode($template);
    $form['config_type']['#default_value'] = 'migration';
    $form['config_type']['#disabled'] = true;
    $form['advanced']['custom_entity_id']['#disabled'] = true;

    $form['workgroups'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Workgroups for which Stanford Profiles are imported'),
      '#default_value' => $wg_values,
      '#description' => $this->t("Enter one workgroup per line"),
      '#rows' => 30,
    ];

    return $form; //parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {

    $fp_array_original = Yaml::decode($form_state->getValue('import'));

    // Validate that the urls are in good format and no extra whitespace has
    // been added.
    $wgs = array_filter(explode(PHP_EOL, $form_state->getValue('workgroups')));
    $wgs = array_map('trim', $wgs);

    // Check for empty lines and valid urls on listed events.
    foreach ($wgs as $wg) {
      // No empty lines.
      if (empty($wg)) {
        $form_state->setErrorByName('workgroups', $this->t('Cannot have empty lines'));
        return;
      }
    }
  }

  /**
   * Return an array of people search terms from workgroup name
   *
   * @param string $department
   *   Department acronym from workgroup; if null, terms for person type only.
   * @param string $ptype
   *   People Type from workgroup; if null, terms for department only.
   * @param string $psubtype
   *   People Sub-Type from workgroup; may be null.
   *
   * @return array
   *   Array of search terms; empty array if both $department and $ptype NULL.
   */
  private function getTermNames($department = NULL, $ptype = NULL, $psubtype = NULL) {
    if (empty($department) && empty($ptype)) {
      return [];
    }
    $terms[] = 'All People';
    $dept = '';
    switch ($department) {
      case 'ere':
        $dept = 'ERE';
        break;
      case 'eess':
        $dept = 'ESS';
        break;
      case 'ges':
        $dept = 'GS';
        break;
      case 'geophysics':
        $dept = 'GEOPHYSICS';
        break;
      case 'eiper':
        $dept = 'E-IPER';
        break;
      case 'esys':
        $dept = 'EARTH SYSTEMS';
        break;
      case 'ssp':
        $dept = 'SUSTAINABILITY';
        break;
    }
    if (!empty($dept)) {
      $terms[] = 'All ' . $dept;
    }
    if (!empty($ptype)) {
      $terms[] = 'All ' . ucfirst($ptype);
      if (!empty($psubtype)) {
        $terms[] = 'All ' . ucfirst($psubtype) . ' ' . ucfirst($ptype);
      }
      if (!empty($dept)) {
        $terms[] = $dept . ' ' . ucfirst($ptype);
        if (!empty($psubtype)) {
          $terms[] = $dept . ' ' . ucfirst($psubtype) . ' ' . ucfirst($ptype);
        }
      }
    }
    return $terms;
  }

  /**
   * Look for a taxonomy term and create if necessary; create term linking wg.
   *
   * @param array $terms
   *   Taxonomy terms to look for in people_search_terms.
   * @param string $wg
   *   Create or update a workgroup term with the matching search terms.
   *
   */
  private function updateSearchTerms($terms, $wg = NULL) {
    $termids = [];
    foreach ($terms as $term) {
      $properties = [
        'vid' => 'people_search_terms',
        'name' => $term,
      ];
      $terms = $this->entityTypeManager
        ->getStorage('taxonomy_term')
        ->loadByProperties($properties);
      $termid = FALSE;
      if (empty($terms)) {
        $entity = $this->entityTypeManager
          ->getStorage('taxonomy_term')->create($properties);
        $entity->save();
        $termid = $entity->id();
      }
      else {
        foreach ($terms as $key => $value) {
          $termid = strval($key);
          break;
        }
      }
      if ($termid) {
        $termids[] = ['target_id' => strval($termid)];
      }
    }
    if (!empty($wg) && !empty($termids)) {
      $properties = [
        'vid' => 'profile_workgroups',
        'name' => $wg,
      ];
      $wg_terms = $this->entityTypeManager
        ->getStorage('taxonomy_term')
        ->loadByProperties($properties);
      if (empty($wg_terms)) {
        $entity = $this->entityTypeManager
          ->getStorage('taxonomy_term')->create($properties);
      }
      else {
        $entity = reset($wg_terms);
      }
      $entity->field_people_search_terms = $termids;
      $entity->save();
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {

    $save_max_exec = ini_get('max_execution_time');
    ini_set('max_execution_time', 240);
    // Actually do the save of the new configuration.
    $wgs = array_filter(explode(PHP_EOL, $form_state->getValue('workgroups')));
    $wgs = array_map('trim', $wgs);
    //// Save the new configuration.
    $this->configFactory->getEditable('migrate_plus.migration_group.earth_capx')
      ->set('workgroups', $wgs)
      ->save();

    // delete the old migrations
    $eMigrations = $this->configFactory->listAll('migrate_plus.migration.earth_capx_import');
    foreach ($eMigrations as $eMigration) {
      $this->configFactory->getEditable($eMigration)->delete();
    }

    // delete the old migration map and message tables
    $tables = array_merge($this->db->schema()->findTables('migrate_map_earth_capx_importer%'),
      $this->db->schema()->findTables('migrate_message_earth_capx_importer%'));
    foreach ($tables as $key => $table) {
        $this->db->schema()->dropTable($table);
    }

    // make sure we have generic search terms for departments
    $wg_depts = ['eiper', 'esys', 'ere', 'ess', 'geophysics', 'ges', 'ssp'];
    foreach ($wg_depts as $wg_dept) {
      $terms = $this->getTermNames($wg_dept);
      if ($terms) {
        $this->updateSearchTerms($terms);
      }
    }
    // make sure we have generic search terms for faculty
    foreach (['regular', 'emeritus', 'affiliated'] as $psubtype) {
      $terms = $this->getTermNames(NULL, 'faculty', $psubtype);
      if ($terms) {
        $this->updateSearchTerms($terms);
      }
    }

    /*
    // make sure we have generic search terms for postdocs
    $terms = $this->getTermNames(NULL, 'postdocs');
    $this->updateSearchTerms($terms);
    // make sure we have generic search terms for students
    foreach (['graduate', 'undergraduate'] as $psubtype) {
      $terms = $this->getTermNames(NULL, 'students', $psubtype);
      if ($terms) {
        $this->updateSearchTerms($terms);
      }
    }
    // make sure we have generic search terms for staff
    foreach (['admin', 'research', 'teaching'] as $psubtype) {
      $terms = $this->getTermNames(NULL, 'staff', $psubtype);
      if ($terms) {
        $this->updateSearchTerms($terms);
      }
    }
    */
    
    $fp_array = Yaml::decode($form_state->getValue('import'));
    foreach ($wgs as $wg) {
      // create migration config
      $random_id = random_int(0,10000);
      $fp_array['id'] = 'earth_capx_importer_' . strval($random_id);
      $fp_array['source']['urls'] = ['https://cap.stanford.edu/cap-api/api/profiles/v1?privGroups=' . $wg .
        '&ps=1000&whitelist=displayName,shortTitle,bio,primaryContact,profilePhotos,' .
        'longTitle,internetLinks,contacts,meta,titles'];
      $fp_array['label'] = 'Profiles for ' . $wg;
      $form_state->setValue('import', Yaml::encode($fp_array));
      parent::validateForm($form, $form_state);

      $config_importer = $form_state->get('config_importer');
      $config_importer->import();

      // update taxonomy with search terms for this workgroup
      $wg_parts = explode(':', $wg);
      if ($wg_parts[0] === 'earthsci' && count($wg_parts) > 1) {
        $wg_parts_str = $wg_parts[1];
        if ($wg_parts_str === 'ssp-staff') {
          $wg_parts_str = 'ssp-staff-admin';
        }
        $wg_terms = explode('-', $wg_parts_str, 3);
        if (in_array($wg_terms[0], ['eess', 'ere', 'ges', 'geophysics', 'eiper', 'esys', 'ssp'])) {
          $ptype = NULL;
          $psubtype = NULL;
          if (count($wg_terms) > 1) {
            $ptype = $wg_terms[1];
            if (count($wg_terms) > 2) {
              $psubtype = $wg_terms[2];
              if ($psubtype === 'regulars') {
                $psubtype = 'regular';
              }
              else if ($psubtype === 'graduate-phd') {
                $psubtype = 'graduate';
              }
              else if ($psubtype === 'advisors') {
                $psubtype = 'affiliated';
              }
            }
          }
          $terms = $this->getTermNames($wg_terms[0], $ptype, $psubtype);
          if ($terms) {
            $this->updateSearchTerms($terms, $wg);
          }
        }
      }
      else {
        drupal_set_message('Invalid workgroup name ' . $wg . ' could not be processed.');
      }
    }

    // Clear out all caches to ensure the config gets picked up.
    drupal_flush_all_caches();
    ini_set('max_execution_time', $save_max_exec);
  }

}
