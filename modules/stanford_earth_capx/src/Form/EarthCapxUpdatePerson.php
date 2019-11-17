<?php

namespace Drupal\stanford_earth_capx\Form;

use Drupal\config\Form\ConfigSingleImportForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Serialization\Yaml;
use Drupal\migrate_plus\Entity\Migration;
use Drupal\Core\Batch\BatchBuilder;
use Drupal\migrate\MigrateMessage;
use Drupal\migrate_tools\MigrateBatchExecutable;

/**
 * Contains Drupal\stanford_earth_capx\Form\EarthCapxUpdatePerson.
 */
class EarthCapxUpdatePerson extends ConfigSingleImportForm {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'stanford_earth_capx_update_person';
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

    $form['stanford_earth_update_sunetid'] = [
      '#type' => 'textfield',
      '#title' => $this->t('SUNetID'),
      '#description' => $this->t('SUNetID of the profile being imported.'),
      '#required' => TRUE,
    ];
    $form['config_type']['#default_value'] = 'migration';
    $form['config_type']['#disabled'] = TRUE;
    $form['advanced']['custom_entity_id']['#disabled'] = TRUE;
    $form['import']['#default_value'] = 'unused';
    $form['import']['#disabled'] = TRUE;

    /*
    $terms = \Drupal::entityTypeManager()->getStorage('taxonomy_term')
    ->loadByProperties(['vid' => 'people_search_terms']);
    $term_list = [];
    foreach ($terms as $key => $value) {
    $kids = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadChildren($key);
    if (empty($kids)) {
    $term_name = $value->getName();
    if (substr($term_name,0, 4) !== 'All ') {
    $term_list[$key] = $value->getName();
    }
    }
    }
    asort($term_list);

    $form['stanford_earth_update_search_terms'] = [
    '#type' => 'select',
    '#title' => 'Directory Search Terms',
    '#description' => 'Optional. Select terms for which this person is ' .
    'categorized in directory listings. Note: this gets reset every night ' .
    'based on workgroup membership.',
    '#multiple' => TRUE,
    '#options' => $term_list,
    ];

     */

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $sunetid = $form_state->getValue('stanford_earth_update_sunetid');
    $template = $this->configStorage->read('migrate_plus.migration.earth_capx_single_sunet');
    if (!empty($template['uuid'])) {
      unset($template['uuid']);
    }
    $batch_builder = new BatchBuilder();
    $batch_builder->setTitle($this->t('Create Single Profile Migration'));
    $batch_builder->setInitMessage($this->t('Creating profile import migrations for each requested workgroup.'));
    $batch_builder->setProgressive(TRUE);
    // Create migration configuration in batch mode.
    $batch_builder->addOperation(
      [
        $this,
        'earthCapxCreateSunetMigration',
      ],
      [
        $form,
        $form_state,
        $sunetid,
        $template,
      ]);
    $batch_builder->addOperation(
      [
        $this,
        'earthCapxImportSunetProfile',
      ],
      [
        $sunetid,
      ]
    );
    $batch_builder->addOperation(
      [
        $this,
        'earthCapxImportSunetCleanup',
      ],
      [
        $sunetid,
      ]
    );
    batch_set($batch_builder->toArray());

  }

  /**
   * Create migration config.
   *
   * @param array $form
   *   Form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Form State array.
   * @param string $sunetid
   *   Sunetid of profile.
   * @param array $fp_array
   *   Migration definition array.
   */
  public function earthCapxCreateSunetMigration(array $form, FormStateInterface $form_state, string $sunetid, array $fp_array) {
    $fp_array['id'] = 'earth_capx_single_sunet_' . $sunetid;
    $fp_array['source']['urls'] = [
      'https://cap.stanford.edu/cap-api/api/profiles/v1?uids=' . $sunetid .
      '&ps=100&p=1&whitelist=affiliations,displayName,shortTitle,bio,primaryContact,' .
      'profilePhotos,longTitle,internetLinks,contacts,meta,titles',
    ];
    $fp_array['label'] = 'Profile for ' . $sunetid;
    $form_state->setValue('import', Yaml::encode($fp_array));
    parent::validateForm($form, $form_state);
    $config_importer = $form_state->get('config_importer');
    $config_importer->import();
  }

  /**
   * Import single sunet profile via batch.
   *
   * @param string $sunetid
   *   Id of user to import.
   */
  public function earthCapxImportSunetProfile(string $sunetid) {
    $migration = Migration::load('earth_capx_single_sunet_' . $sunetid);
    /** @var \Drupal\migrate\Plugin\MigrationInterface $migration_plugin */
    $migration_plugin = \Drupal::service('plugin.manager.migration')
      ->createInstance($migration->id(), $migration->toArray());
    $migration_plugin->getIdMap()->prepareUpdate();
    $migrateMessage = new MigrateMessage();
    $options = [
      'limit' => 0,
      'update' => 1,
      'force' => 0,
    ];
    $executable = new MigrateBatchExecutable($migration_plugin, $migrateMessage, $options);
    $executable->batchImport();
  }

  /**
   * Cleanup migration of single sunet profile via batch.
   *
   * @param string $sunetid
   *   Id of user to import.
   */
  public function earthCapxImportSunetCleanup(string $sunetid) {
    // Delete the old migrations.
    $this->configStorage->delete('migrate_plus.migration.earth_capx_single_sunet_' . $sunetid);
    // Delete the old migration map and message tables.
    $db = \Drupal::database();
    $tables = array_merge($db->schema()
      ->findTables('migrate_map_earth_capx_single_sunet_' . $sunetid),
      $db->schema()->findTables('migrate_message_earth_capx_single_sunet_' . $sunetid));
    foreach ($tables as $table) {
      $db->schema()->dropTable($table);
    }

  }

}
