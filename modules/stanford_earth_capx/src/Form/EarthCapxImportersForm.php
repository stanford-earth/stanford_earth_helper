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
use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\Core\Database\Connection;
use Drupal\Core\Batch\BatchBuilder;

/**
 * EarthCapxImportersForm description.
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
   * EarthCapxImportersForm constructor.
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
   * @param \Drupal\Core\Extension\ModuleExtensionList $module_list
   *   The module extension list.
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
                              Connection $db,
                              ModuleExtensionList $module_list) {
    $this->entityTypeManager = $entityTypeManager;
    $this->db = $db;

    parent::__construct($entity_manager, $config_storage, $renderer, $event_dispatcher, $config_manager, $lock,
      $typed_config, $module_handler, $module_installer, $theme_handler, $module_list);
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
      $container->get('database'),
      $container->get('extension.list.module')
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

    // Create a field for editing the list of workgroups for profiles.
    $wgs = $this->config('migrate_plus.migration_group.earth_capx')
      ->get('workgroups');
    $wg_values = is_array($wgs) ? implode($wgs, PHP_EOL) : $wgs;

    // Create a field for editing department names for profile taxonomies.
    $depts = $this->config('migrate_plus.migration_group.earth_capx')
      ->get('departments');
    $dept_values = is_array($depts) ? implode($depts, PHP_EOL) : $depts;

    // Read the raw data for this config name, encode it, and display it.
    $template = $this->configStorage->read('migrate_plus.migration.earth_capx_template');
    $template['migration_group'] = 'earth_capx';
    if (!empty($template['uuid'])) {
      unset($template['uuid']);
    }
    $form['import']['#default_value'] = Yaml::encode($template);
    $form['config_type']['#default_value'] = 'migration';
    $form['config_type']['#disabled'] = TRUE;
    $form['advanced']['custom_entity_id']['#disabled'] = TRUE;
    $description = "Department name to use per code " .
      "in the form 'dept|name' such as 'ere|Energy Resources Engineering'";
    $form['departments'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Department Names'),
      '#default_value' => $dept_values,
      '#description' => $this->t($description),
      '#rows' => 10,
    ];
    $description = "Enter one workgroup per line. " .
      "Department Regular Factory workgroups should go first. " .
      "Workgroup names not in the form stem:dept-persontype-personsubtype " .
      "should be appended with |dept|persontype|personsubtype";
    $form['workgroups'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Workgroups for which Stanford Profiles are imported'),
      '#default_value' => $wg_values,
      '#description' => $this->t($description),
      '#rows' => 30,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {

    // Validate that the workgroups are in good format and no extra whitespace
    // has been added.
    $wgs = array_filter(explode(PHP_EOL, $form_state->getValue('workgroups')));
    $wgs = array_map('trim', $wgs);

    // Check for empty lines on workgroups.
    foreach ($wgs as $wg) {
      // No empty lines.
      if (empty($wg)) {
        $form_state->setErrorByName('workgroups', $this->t('Cannot have empty lines'));
        return;
      }
    }
  }

  /**
   * Return an array of people search terms from workgroup name.
   *
   * @param string $department
   *   Department acronym from workgroup; if null, terms for person type only.
   * @param string $full_name
   *   Department full name associated with code from workgroup name.
   * @param string $ptype
   *   People Type from workgroup; if null, terms for department only.
   * @param string $psubtype
   *   People Sub-Type from workgroup; may be null.
   *
   * @return array
   *   Array of search terms; empty array if both $department and $ptype NULL.
   */
  private function getTermNames($department = NULL,
                                $full_name = NULL,
                                $ptype = NULL,
                                $psubtype = NULL) {
    if (empty($department) && empty($ptype)) {
      return [];
    }
    $dept = '';
    if (!empty($department)) {
      if (!empty($full_name)) {
        $dept = $full_name;
      }
      else {
        $dept = $department;
      }
    }
    $terms[] = 'All People';
    if (!empty($dept)) {
      $terms[] = $dept;
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
   * @param bool $notreg
   *   If true, and is a "reg faculty" workgroup, do not include members in all.
   */
  private function updateSearchTerms(array $terms, $wg = NULL, $notreg = FALSE) {

    // Only allow department regular faculty to be "all regular faculty".
    if ($notreg) {
      if ($key = array_search('All Regular Faculty', $terms)) {
        unset($terms[$key]);
      }
    }

    // Find term existing in People Search Terms vocabulary or create it.
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
      // If term is not found, create it and get its term id.
      if (empty($terms)) {
        $entity = $this->entityTypeManager
          ->getStorage('taxonomy_term')->create($properties);
        $entity->save();
        $termid = $entity->id();
      }
      // If the term is found, it should be only once; get last one in array.
      else {
        foreach ($terms as $key => $value) {
          $termid = strval($key);
          break;
        }
      }
      // If we found or created a term, save its id to $termids array.
      if ($termid) {
        $termids[] = ['target_id' => strval($termid)];
      }
    }

    // If we have a workgroup and a termid, save to profile_workgroups vocab.
    if (!empty($wg) && !empty($termids)) {
      $properties = [
        'vid' => 'profile_workgroups',
        'name' => $wg,
      ];
      $wg_terms = $this->entityTypeManager
        ->getStorage('taxonomy_term')
        ->loadByProperties($properties);
      // Find or create a term for the workgroup.
      if (empty($wg_terms)) {
        $entity = $this->entityTypeManager
          ->getStorage('taxonomy_term')->create($properties);
      }
      else {
        $entity = reset($wg_terms);
      }
      // Add the profile search term id to the workgroup term.
      $entity->field_people_search_terms = $termids;
      $entity->save();
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {

    // Actually do the save of the new configuration.
    $config = $this->configFactory->getEditable('migrate_plus.migration_group.earth_capx');

    $wgs = array_filter(explode(PHP_EOL, $form_state->getValue('workgroups')));
    $wgs = array_map('trim', $wgs);
    // Save the new configuration.
    $config->set('workgroups', $wgs)->save();
    $depts = array_filter(explode(PHP_EOL, $form_state->getValue('departments')));
    $depts = array_map('trim', $depts);
    $config->set('departments', $depts)->save();
    $wg_depts = [];
    foreach ($depts as $dept) {
      $keyval = explode('|', $dept);
      $wg_depts[$keyval[0]] = $keyval[1];
    }

    // Delete the old migrations.
    $eMigrations = $this->configFactory->listAll('migrate_plus.migration.earth_capx_import');
    foreach ($eMigrations as $eMigration) {
      if (strpos($eMigration, "000_preprocess") === FALSE &&
        strpos($eMigration, "999_postprocess") === FALSE) {
        $this->configFactory->getEditable($eMigration)->delete();
      }
    }

    // Delete the old migration map and message tables.
    $tables = array_merge($this->db->schema()
      ->findTables('migrate_map_earth_capx_importer%'),
      $this->db->schema()->findTables('migrate_message_earth_capx_importer%'));
    foreach ($tables as $table) {
      $this->db->schema()->dropTable($table);
    }

    // Make sure we have generic search terms for departments.
    foreach ($wg_depts as $wg_dept => $wg_dept_name) {
      $terms = $this->getTermNames($wg_dept, $wg_dept_name);
      if ($terms) {
        $this->updateSearchTerms($terms);
      }
    }
    // Make sure we have generic search terms for faculty.
    foreach (['regular', 'emeritus', 'associated'] as $psubtype) {
      $terms = $this->getTermNames(NULL, NULL,
        'faculty', $psubtype);
      if ($terms) {
        $this->updateSearchTerms($terms);
      }
    }

    // Make sure we have generic search terms for postdocs.
    $terms = $this->getTermNames(NULL, NULL,
      'postdocs');
    if ($terms) {
      $this->updateSearchTerms($terms);
    }

    // Make sure we have generic search terms for students.
    foreach (['graduate', 'undergraduate'] as $psubtype) {
      $terms = $this->getTermNames(NULL, NULL,
        'students', $psubtype);
      if ($terms) {
        $this->updateSearchTerms($terms);
      }
    }
    // Make sure we have generic search terms for staff.
    foreach (['admin', 'research', 'teaching'] as $psubtype) {
      $terms = $this->getTermNames(NULL, NULL,
        'staff', $psubtype);
      if ($terms) {
        $this->updateSearchTerms($terms);
      }
    }

    $fp_array = Yaml::decode($form_state->getValue('import'));
    $batch_builder = new BatchBuilder();
    $batch_builder->setTitle($this->t('Create Profile Migrations'));
    $batch_builder->setInitMessage($this->t('Creating profile import migrations for each requested workgroup.'));
    $wgs_used = [];
    foreach ($wgs as $wg_idx => $wg_str) {
      $wg_bits = explode('|', $wg_str);
      $wg = reset($wg_bits);
      $m_index = intval($wg_idx) + 1;
      // Create migration configuration in batch mode.
      $batch_builder->addOperation(
        [
          $this,
          'earthCapxCreateWgMigration',
        ],
        [
          $form,
          $form_state,
          $wg,
          $fp_array,
          $m_index,
        ]);
      $wgs_used[] = $wg;
      // Update taxonomy with search terms for this workgroup.
      $wg_parts = explode(':', $wg);
      if (count($wg_parts) > 1) {
        $wg_terms = explode('-', $wg_parts[1], 3);
        $notallregular = FALSE;
        if (count($wg_bits) > 1) {
          array_shift($wg_bits);
          $wg_new_term = [];
          foreach ($wg_bits as $wgkey => $wg_bit) {
            if ($wg_bit === 'notallregular') {
              $notallregular = TRUE;
            }
            else {
              if ($wg_bit === '*') {
                $wg_new_term[] = $wg_terms[$wgkey];
              }
              else {
                $wg_new_term[] = $wg_bit;
              }
            }
          }
          $wg_terms = $wg_new_term;
        }

        $ptype = NULL;
        $psubtype = NULL;
        if (count($wg_terms) > 1) {
          $ptype = $wg_terms[1];
          if (count($wg_terms) > 2) {
            $psubtype = $wg_terms[2];
          }
        }
        $terms = $this->getTermNames($wg_terms[0], $wg_depts[$wg_terms[0]],
          $ptype, $psubtype);
        if ($terms) {
          $this->updateSearchTerms($terms, $wg, $notallregular);
        }

      }
      else {
        $messenger = \Drupal::messenger();
        $messenger->addError(
          $this->t('Invalid workgroup name %wg could not be processed.',
            [
              '%wg' => $wg,
            ]
          )
        );
      }
    }
    $batch_builder->addOperation(
      [
        $this,
        'earthCapxDeleteUnusedWorkgroupTaxonomyTerms',
      ],
      [
        $wgs_used,
      ]);
    batch_set($batch_builder->toArray());
  }

  /**
   * Create migration config.
   *
   * @param array $form
   *   Form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Form State array.
   * @param string $wg
   *   Workgroup name.
   * @param array $fp_array
   *   Migration definition array.
   * @param int $wg_idx
   *   Index number for migration.
   */
  public function earthCapxCreateWgMigration(array $form, FormStateInterface $form_state, string $wg, array $fp_array, int $wg_idx) {
    $fp_array['id'] = 'earth_capx_importer_' . str_pad(strval($wg_idx), 3, "0", STR_PAD_LEFT);
    $fp_array['source']['urls'] = [
      'https://cap.stanford.edu/cap-api/api/profiles/v1?privGroups=' . $wg .
      '&ps=100&p=1&whitelist=affiliations,displayName,shortTitle,bio,primaryContact,' .
      'profilePhotos,longTitle,internetLinks,contacts,meta,titles',
    ];
    $fp_array['label'] = 'Profiles for ' . $wg;
    $form_state->setValue('import', Yaml::encode($fp_array));
    parent::validateForm($form, $form_state);
    $config_importer = $form_state->get('config_importer');
    $config_importer->import();
    drupal_set_message($this->t('Profile import for workgroup %wg configured.', [
      '%wg' => $wg,
    ]));
  }

  /**
   * Batched function to remove unused workgroup taxonomy terms.
   *
   * @param array $wgs
   *   An array of workgroup names.
   */
  public function earthCapxDeleteUnusedWorkgroupTaxonomyTerms(array $wgs) {
    // Note: this does *not* delete any matching Person Search terms; those
    // would need to be deleted manually if they are no longer used.
    //
    // Build a list of current Profiles Workgroups terms, then delete the
    // ones not currently specified in the form.
    $wg_term_list = [];
    $taxonomy_storage = $this->entityTypeManager
      ->getStorage('taxonomy_term');
    // Get all of our workgroup terms.
    $wg_terms = $taxonomy_storage
      ->loadByProperties(['vid' => 'profile_workgroups']);
    // For each term, get its name and put in array for quick searching.
    foreach ($wg_terms as $wg_tid => $wg_term) {
      $wg_term_list[$wg_tid] = $wg_term->getName();
    }
    // If a workgroup from our form is in the list of terms,
    // delete it from the list; the remaining terms will be the list
    // of terms no longer used and so can be deleted.
    foreach ($wgs as $wg) {
      $key = array_search($wg, $wg_term_list);
      if ($key !== FALSE) {
        unset($wg_terms[$key]);
      }
    }
    // Delete the remaining terms.
    $taxonomy_storage->delete($wg_terms);
  }

}
