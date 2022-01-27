<?php

namespace Drupal\stanford_earth_events\Plugin\views\field;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\views\ResultRow;
use Drupal\views\Plugin\views\display\DisplayPluginBase;
use Drupal\views\ViewExecutable;
use Drupal\Core\Entity\EntityTypeManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * A handler to provide a Localist department tags based on Earth terms.
 *
 * @ingroup views_field_handlers
 *
 * @ViewsField("localist_department_field")
 */
class LocalistDepartmentField extends FieldPluginBase implements
  ContainerFactoryPluginInterface {

  /**
   * @var $entityTypeManager \Drupal\Core\Entity\EntityTypeManager
   */
  protected $entityTypeManager;

  /**
   * The current display.
   *
   * @var string
   *   The current display of the view.
   */
  protected $currentDisplay;



  /**
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   * @param array $configuration
   * @param string $plugin_id
   * @param mixed $plugin_definition
   *
   * @return static
   */
  public static function create(ContainerInterface $container,
                                array $configuration,
                                $plugin_id,
                                $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager')
    );
  }

  /**
   * @param array $configuration
   * @param string $plugin_id
   * @param mixed $plugin_definition
   * @param \Drupal\Core\Entity\EntityTypeManager $entityTypeManager
   */
  public function __construct(array $configuration,
                              $plugin_id,
                              $plugin_definition,
                              EntityTypeManager $entityTypeManager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->entityTypeManager = $entityTypeManager;
  }

   /**
   * {@inheritdoc}
   */
  public function init(ViewExecutable $view, DisplayPluginBase $display, array &$options = NULL) {
    parent::init($view, $display, $options);
    $this->currentDisplay = $view->current_display;
  }

  /**
   * {@inheritdoc}
   */
  public function usesGroupBy() {
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function query() {
    // Do nothing -- to override the parent query.
  }

  /**
   * {@inheritdoc}
   */
  protected function defineOptions() {
    $options = parent::defineOptions();
    // First check whether the field should be hidden if the value(hide_alter_empty = TRUE) /the rewrite is empty (hide_alter_empty = FALSE).
    $options['hide_alter_empty'] = ['default' => FALSE];
    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function render(ResultRow $values) {
    $node = $values->_entity;
    //return $states[$node->get('field_phase')->getValue()[0]['value']];
    $departments = $node->get('field_s_event_department')->getValue();
    $deptStr = '';
    if (!empty($departments)) {
      foreach ($departments as $deptArray) {
        $target_id = '';
        if (!empty($deptArray['target_id'])) {
          $target_id = $deptArray['target_id'];
          if (!empty($target_id)) {
            // $deptTerm = \Drupal::getContainer()->get('entity_type.manager')
            $deptTerm = $this->entityTypeManager
              ->getStorage('taxonomy_term')->load($target_id);
            if (!empty($deptTerm)) {
              $localistArray = $deptTerm->get('field_term_department_localist')->getValue();
              if (!empty($localistArray)) {
                $localistDept = reset($localistArray);
                if (!empty($localistDept['value'])) {
                  if (!empty($deptStr)) {
                    $deptStr .= ', ';
                  }
                  $deptStr .= $localistDept['value'];
                }
              }
            }
          }
        }
      }
    }
    return $deptStr;
  }

}
