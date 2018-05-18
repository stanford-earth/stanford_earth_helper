<?php

/**
 * @file
 * Contains \Drupal\stanford_earth_events\Form\ListedEventsForm.
 */

namespace Drupal\stanford_earth_events\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Component\Utility\UrlHelper;

/**
 * [ListedEventsForm description]
 */
class EventImportersForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'stanford_earth_events_listed';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'migrate_plus.migration.events_importer_unlisted',
      'migrate_plus.migration.events_importer',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $listed = $this->config('migrate_plus.migration.events_importer')->get('source.urls');
    $unlisted = $this->config('migrate_plus.migration.events_importer_unlisted')->get('source.urls');

    $listed_values = is_array($listed) ? implode($listed, PHP_EOL) : $listed;
    $unlisted_values = is_array($unlisted) ? implode($unlisted, PHP_EOL) : $unlisted;

    $form['listed_events'] = array(
      '#type' => 'textarea',
      '#title' => $this->t('Public or listed events'),
      '#default_value' => $listed_values,
      '#description' => $this->t("Enter one feed per line"),
      '#rows' => 30,
    );

    $form['unlisted_events'] = array(
      '#type' => 'textarea',
      '#title' => $this->t('Private or un-listed events'),
      '#default_value' => $unlisted_values,
      '#description' => $this->t("Enter one feed per line"),
      '#rows' => 30,
    );

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $listed = array_filter(explode(PHP_EOL, $form_state->getValue('listed_events')));
    $unlisted = array_filter(explode(PHP_EOL, $form_state->getValue('unlisted_events')));
    $listed = array_map('trim', $listed);
    $unlisted = array_map('trim', $unlisted);

    foreach ($listed as $k => $v) {
      if (empty($v)) {
        $form_state->setErrorByName('listed_events', $this->t('Cannot have empty lines'));
      }

      if (!UrlHelper::isValid($v, TRUE)) {
        $form_state->setErrorByName('listed_events', $this->t('Invalid url included in settings'));
      }

    }

    foreach ($unlisted as $k => $v) {
      if (empty($v)) {
        $form_state->setErrorByName('unlisted_events', $this->t('Cannot have empty lines'));
      }

      if (!UrlHelper::isValid($v, TRUE)) {
        $form_state->setErrorByName('unlisted_events', $this->t('Invalid url included in settings'));
      }
    }

  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {

    $listed = array_filter(explode(PHP_EOL, $form_state->getValue('listed_events')));
    $unlisted = array_filter(explode(PHP_EOL, $form_state->getValue('unlisted_events')));
    $listed = array_map('trim', $listed);
    $unlisted = array_map('trim', $unlisted);

    // Save the new configuration.
    $this->configFactory->getEditable('migrate_plus.migration.events_importer_unlisted')
      ->set('source.urls', $unlisted)
      ->save();

    $this->configFactory->getEditable('migrate_plus.migration.events_importer')
      ->set('source.urls', array_filter($listed))
      ->save();

    parent::submitForm($form, $form_state);
  }

}
