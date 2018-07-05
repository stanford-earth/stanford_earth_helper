<?php

namespace Drupal\stanford_earth_events\Form;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Entity\EntityTypeManager;

/**
 * ListedEventsForm description.
 */
class EventImportersForm extends ConfigFormBase {

  /**
   * EntityTypeManager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManager
   */
  protected $entityTypeManager;

  /**
   * EventImportersForm constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManager $entityTypeManager
   *   The EntityTypeManager service.
   */
  public function __construct(EntityTypeManager $entityTypeManager) {

    $this->entityTypeManager = $entityTypeManager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager')
    );
  }

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

    // Create a two field form for editing the list of public and private event
    // feeds from events.stanford.edu.
    $listed = $this->config('migrate_plus.migration.events_importer')->get('source.urls');
    $unlisted = $this->config('migrate_plus.migration.events_importer_unlisted')->get('source.urls');

    // Fetch their current values.
    $listed_values = is_array($listed) ? implode($listed, PHP_EOL) : $listed;
    $unlisted_values = is_array($unlisted) ? implode($unlisted, PHP_EOL) : $unlisted;

    $form['listed_events'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Public or listed events'),
      '#default_value' => $listed_values,
      '#description' => $this->t("Enter one feed per line"),
      '#rows' => 30,
    ];

    $form['unlisted_events'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Private or un-listed events'),
      '#default_value' => $unlisted_values,
      '#description' => $this->t("Enter one feed per line"),
      '#rows' => 30,
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {

    // Validate that the urls are in good format and no extra whitespace has
    // been added.
    $listed = array_filter(explode(PHP_EOL, $form_state->getValue('listed_events')));
    $unlisted = array_filter(explode(PHP_EOL, $form_state->getValue('unlisted_events')));
    $listed = array_map('trim', $listed);
    $unlisted = array_map('trim', $unlisted);

    // Check for empty lines and valid urls on listed events.
    foreach ($listed as $v) {

      // No empty lines.
      if (empty($v)) {
        $form_state->setErrorByName('listed_events', $this->t('Cannot have empty lines'));
      }

      // Valid url?
      if (!UrlHelper::isValid($v, TRUE)) {
        $form_state->setErrorByName('listed_events', $this->t('Invalid url included in settings'));
      }

    }

    // Check for empty lines and valid urls on unlisted events.
    foreach ($unlisted as $v) {
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

    // Actually do the save of the new configuration.
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

    foreach (array_merge($listed, $unlisted) as $name) {
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

    // Clear out all caches to ensure the config gets picked up.
    drupal_flush_all_caches();

    parent::submitForm($form, $form_state);
  }

}
