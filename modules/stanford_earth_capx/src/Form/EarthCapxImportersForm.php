<?php

namespace Drupal\stanford_earth_capx\Form;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Form\ConfigFormBase;
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
   */
  public function __construct(EntityManagerInterface $entity_manager, StorageInterface $config_storage,
                              RendererInterface $renderer, EventDispatcherInterface $event_dispatcher,
                              ConfigManagerInterface $config_manager, LockBackendInterface $lock,
                              TypedConfigManagerInterface $typed_config, ModuleHandlerInterface $module_handler,
                              ModuleInstallerInterface $module_installer, ThemeHandlerInterface $theme_handler,
                              EntityTypeManager $entityTypeManager) {
    $this->entityTypeManager = $entityTypeManager;
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
      $container->get('entity_type.manager')
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

    // Validate that the urls are in good format and no extra whitespace has
    // been added.
    $wgs = array_filter(explode(PHP_EOL, $form_state->getValue('workgroups')));
    $wgs = array_map('trim', $wgs);

    // Check for empty lines and valid urls on listed events.
    foreach ($wgs as $v) {
      // No empty lines.
      if (empty($v)) {
        $form_state->setErrorByName('workgroups', $this->t('Cannot have empty lines'));
      }
    }
  }

  /**
   * Look for a taxonomy term, create if necessary, and return its tid.
   *
   * @param string $vocabulary
   *   Taxonomy vocabulary to look in.
   * @param string $term
   *   Taxonomy term to look for in the vocabulary.
   *
   * @return string
   *   Term id (tid) of term or FALSE if not found and uncreated.
   */
  private function updateTerms($vocabulary, $term, $parent = NULL) {
    $properties = [
      'name' => $term,
      'vid' => $vocabulary,
    ];
  //  if (!empty($parent)) {
  //    $properties['parent'] = $parent;
  //  }
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
    return $termid;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {

    // Actually do the save of the new configuration.
    $wgs = array_filter(explode(PHP_EOL, $form_state->getValue('workgroups')));
    $wgs = array_map('trim', $wgs);
    //// Save the new configuration.
    $this->configFactory->getEditable('migrate_plus.migration_group.earth_capx')
      ->set('workgroups', $wgs)
      ->save();

    // delete the old migrations
    //$this->configFactory->getEditable('migrate_plus.migration.earth_capx_importer')->delete();
    $eMigrations = $this->configFactory->listAll('migrate_plus.migration.earth.capx_import');
    foreach ($eMigrations as $eMigration) {
      $this->configFactory->getEditable($eMigration)->delete();
    }

    $allTermId = $this->updateTerms('people_search_terms','All Stanford People');
    $fp_array = Yaml::decode($form_state->getValue('import'));
    foreach ($wgs as $wg) {
      // create migration config
      //$fp = drupal_get_path('module','stanford_earth_capx');
      //$fp_array = Yaml::decode($form_state->getValue('import'));
      $random_id = random_int(0,10000); // base64_encode(random_bytes(6));
      $fp_array['id'] = 'earth_capx_importer_' . strval($random_id);
      $fp_array['source']['urls'] = ['https://cap.stanford.edu/cap-api/api/profiles/v1?privGroups=' . $wg . '&ps=1000'];
      $fp_array['label'] = 'Profiles for ' . $wg;
      $form_state->setValue('import', Yaml::encode($fp_array));
      parent::validateForm($form, $form_state);

      $config_importer = $form_state->get('config_importer');
      $config_importer->import();

      // update taxonomy
      $dept = '';
      $ptype = [];
      $wgtest = explode(":", $wg);
      if ($wgtest[0] === "earthsci" && count($wgtest) > 1) {
        $dtype = explode("-", $wgtest[1]);
        if (count($dtype) > 1) {
          if ($dtype[0] === 'deans' && $dtype[1] === 'office' && count($dtype) == 3) {
            $dept = "Dean's Office";
            if ($dtype[2] === 'staff') {
              $ptype = ['staff', 'admin'];
            }
            elseif ($dtype[2] === 'faculty') {
              $ptype = ['faculty', 'regular'];
            }
          }
          elseif ($dtype[0] === 'ssp' || $dtype[0] === 'changeleadership'
            || $dtype[0] === 'sustainleadership') {
          }
          else {
            foreach ($dtype as $key => $value) {
              if ($key == 0) {
                switch ($value) {
                  case 'ere':
                    $dept = 'Energy Resources Engineering';
                    break;
                  case 'eess':
                    $dept = 'Earth System Science';
                    break;
                  case 'esys':
                    $dept = 'Earth Systems Program';
                    break;
                  case 'eiper':
                    $dept = 'E-IPER Program';
                    break;
                  case 'ges':
                    $dept = 'Geological Sciences';
                    break;
                  case 'geophysics':
                    $dept = 'Geophysics';
                    break;
                  default:
                    $dept = '';
                }
              }
              else {
                $ptype[] = $value;
              }
            }
          }
        }
        if (!empty($dept) && !empty($ptype)) {
          $termids = [$allTermId];
          $deptid = $this->updateTerms('people_search_terms',$dept,$allTermId);
          if (!empty($deptid) && !in_array($deptid,$termids)) {
            $termids[] = $deptid;
          }
        }
      }
      else {
        drupal_set_message('Invalid workgroup name ' . $wg . ' could not be processed.');
      }
    }

    // Clear out all caches to ensure the config gets picked up.
    //drupal_flush_all_caches();

    //parent::submitForm($form, $form_state);
  }

}
