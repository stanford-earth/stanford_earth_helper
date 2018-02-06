<?php

namespace Drupal\stanford_subsites\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\system\Form;
use Drupal\Core\Url;
use Drupal\Core\Link;
use Drupal\taxonomy\Entity\Vocabulary;


/**
 * Implements a ChosenConfig form.
 */
class SubsiteSettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'subsite_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['stanford_subsites.settings'];
  }

  /**
   * Chosen configuration form.
   *
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    // Stanford Subsite settings:
    $subsite_conf = $this->configFactory->get('stanford_subsites.settings');

    $vocabularies = Vocabulary::loadMultiple();
    foreach ($vocabularies as $key => $vocab) {
      $vocabularies[$key] = $vocab->label();
    }

    $form['create_taxonomy_term'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Automatically create a taxonomy term on subsite creation?'),
      '#default_value' => $subsite_conf->get('create_taxonomy_term'),
      '#description' => $this->t('Check box to enable.'),
    ];

    $form['create_taxonomy_vocabulary'] = [
      '#type' => 'select',
      '#title' => $this->t('Create a new term in which vocabulary?'),
      '#default_value' => $subsite_conf->get('create_taxonomy_vocabulary'),
      '#options' => $vocabularies,
      '#description' => $this->t('Select which vocabulary.'),
      '#states' => [
        'visible' => [
          ':input[name="create_taxonomy_term"]' => ['checked' => TRUE],
        ]
      ]
    ];

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Submit'),
    ];

    return $form;
  }

  /**
   * Subsites configuration form submit handler.
   *
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->configFactory->getEditable('stanford_subsites.settings');

    $config
      ->set('create_taxonomy_term', $form_state->getValue('create_taxonomy_term'))
      ->set('create_taxonomy_vocabulary', $form_state->getValue('create_taxonomy_vocabulary'));

    $config->save();

    parent::submitForm($form, $form_state);
  }

}
