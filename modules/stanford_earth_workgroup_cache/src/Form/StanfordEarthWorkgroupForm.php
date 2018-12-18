<?php

namespace Drupal\stanford_earth_workgroup_cache\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\stanford_earth_workgroup_cache\EarthWorkgroups;
use GuzzleHttp\ClientInterface;

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
     * The HTTP client to fetch the feed data with.
     *
     * @var \GuzzleHttp\ClientInterface
     */
    protected $httpClient;

    /**
   * StanfordEarthSamlForm constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The ConfigFactory interface.
     *
     *   @param \GuzzleHttp\ClientInterface $http_client
     *   The Guzzle HTTP client.
     */
  public function __construct(ConfigFactoryInterface $configFactory,
                                ClientInterface $http_client) {
    $this->configFactory = $configFactory;
    parent::__construct($configFactory);
    $this->httpClient = $http_client;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
        $container->get('http_client')
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
      '#default_value' => $config->get('stanford_earth_workgroup_key'),
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
    public function validateForm(array &$form, FormStateInterface $form_state) {
        parent::validateForm($form, $form_state);
        $wgs = array_filter(explode(PHP_EOL, $form_state->getValue('stanford_earth_workgroup_wgs')));
        $wgs = array_map('trim', $wgs);
        if (empty($wgs)) {
            $form_state->setErrorByName('', $this->t('No workgroups to check.'));
            return;
        }
        // Check for empty lines and valid urls on listed events.
        foreach ($wgs as $wg) {
            if (empty($wg)) {
                $form_state->setErrorByName('stanford_earth_workgroup_wgs', $this->t('Cannot have empty lines'));
                return;
            }
        }

        $wg = reset($wgs);
        $wgObj = new EarthWorkgroups($this->httpClient);
        $wg_found = $wgObj->getWorkgroupMembers($wg,
            $form_state->getValue('stanford_earth_workgroup_cert'),
            $form_state->getValue('stanford_earth_workgroup_key'));
        if (empty($wg_found)) {
            $form_state->setErrorByName('',
                'Unable to retrieve workgroup information.');
        }
    }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);
    $this->configFactory->getEditable('stanford_earth_workgroup.adminsettings')
        ->set('stanford_earth_workgroup_wgs', $form_state->getValue('stanford_earth_workgroup_wgs'))
      ->set('stanford_earth_workgroup_cert', $form_state->getValue('stanford_earth_workgroup_cert'))
      ->set('stanford_earth_workgroup_key', $form_state->getValue('stanford_earth_workgroup_key'))
      ->save();
  }

}