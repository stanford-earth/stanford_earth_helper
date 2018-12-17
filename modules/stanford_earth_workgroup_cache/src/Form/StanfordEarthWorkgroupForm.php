<?php

namespace Drupal\stanford_earth_workgroup_cache\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Contains Drupal\stanford_earth_saml\Form\StanfordEarthSamlForm.
 */
class StanfordEarthWorkgroupForm extends ConfigFormBase {

  /**
   * The configuration factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * StanfordEarthSamlForm constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The ConfigFactory interface.
   */
  public function __construct(ConfigFactoryInterface $configFactory) {
    $this->configFactory = $configFactory;
    parent::__construct($configFactory);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory')
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'stanford_earth_workgroup_cache.adminsettings',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'stanford_earth_workgroup_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('stanford_earth_workgroup.adminsettings');

    $form['stanford_earth_workgroup_cert'] = [
      '#type' => 'textfield',
      '#title' => $this->t('MAIS Certificate Path'),
      '#description' => $this->t('Location on server of the MAIS WG API cert.'),
      '#default_value' => $config->get('stanford_earth_workgroup_cert'),
    ];

    $form['stanford_earth_workgroup_key'] = [
      '#type' => 'textfield',
      '#title' => $this->t('MAIS Key Path'),
      '#description' => $this->t('Location on server of the MAIS WG API key.'),
      '#default_value' => $config->get('stanford_earth_workgroup_cert'),
    ];

    $form['stanford_earth_workgroup_wgs'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Workgroups'),
      '#description' => $this->t('Stanford Workgroups whose SUNetIDs to cache.'),
      '#default_value' => $config->get('stanford_earth_workgroup_wgs'),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);
    $this->config('stanford_earth_workgroup.adminsettings')
      ->set('stanford_earth_workgroup_wgs', $form_state->getValue('stanford_earth_workgroup_wgs'))
      ->set('stanford_earth_workgroup_cert', $form_state->getValue('stanford_earth_workgroup_cert'))
      ->set('stanford_earth_workgroup_key', $form_state->getValue('stanford_earth_workgroup_key'))
      ->save();
    // If enabling auto403login, set the default 403 page to the redirect.
    $this->configFactory->getEditable('system.site')->set('page.403', $uri403)->save();
  }

}