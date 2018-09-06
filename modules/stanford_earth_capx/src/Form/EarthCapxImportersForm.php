<?php

namespace Drupal\stanford_earth_capx\Form;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Entity\EntityTypeManager;

/**
 * ListedEventsForm description.
 */
class EarthCapxImportersForm extends ConfigFormBase {

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
    return 'stanford_earth_capx_importers';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'stanford.earth.capx.workgroups',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    // Create a one field form for editing the list of workgroups for profiles.
    $wgs = $this->config('stanford.earth.capx.workgroups')->get('stanford_earth_capx_workgroups');

    // Fetch their current values.
    $wg_values = is_array($wgs) ? implode($wgs, PHP_EOL) : $wgs;

    $form['workgroups'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Workgroups for whom Stanford Profiles are imported'),
      '#default_value' => $wg_values,
      '#description' => $this->t("Enter one workgroup per line"),
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

    // Save the new configuration.
    $this->configFactory->getEditable('stanford.earth.capx.workgroups')
      ->set('stanford_earth_capx_workgroups', $wgs)
      ->save();

    $allTermId = $this->updateTerms('people_search_terms','All Stanford People');
    foreach ($wgs as $wg) {
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
    drupal_flush_all_caches();

    parent::submitForm($form, $form_state);
  }

}
