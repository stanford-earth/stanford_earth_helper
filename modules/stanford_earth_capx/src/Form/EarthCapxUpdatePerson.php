<?php

namespace Drupal\stanford_earth_capx\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

/**
 * Contains Drupal\stanford_earth_capx\Form\EarthCapxUpdatePerson.
 */
class EarthCapxUpdatePerson extends FormBase {

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

    $form['stanford_earth_update_sunetid'] = [
      '#type' => 'textfield',
      '#title' => $this->t('SUNetID'),
      '#description' => $this->t('SUNetID of the profile being imported.'),
      '#required' => TRUE,
    ];

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
      '#description' => 'Optional. Select terms for which this person is categorized in directory listings. Note: this gets reset every night based on workgroup membership.',
      '#multiple' => TRUE,
      '#options' => $term_list,
    ];

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => 'Import',
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $url = Url::fromUri('https://ksharp-earth.stanford.edu');
    $form_state->setRedirectUrl($url);
    // https://api.stanford.edu/profiles/v1 params see below
    //$params = array('uids' => $sunet, 'ps'=>1, 'whitelist'=>'affiliations,displayName,shortTitle,bio,primaryContact,profilePhotos,longTitle,internetLinks,contacts,meta,titles');
    //$data = ses_cap_lite_request('/profiles/v1',$params);
    //print 'cap data for '.$sunet.'<br /><pre>'; print_r($data); print '</pre>';

  }
}
