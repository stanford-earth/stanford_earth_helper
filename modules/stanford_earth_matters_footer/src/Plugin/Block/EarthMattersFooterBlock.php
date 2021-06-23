<?php

namespace Drupal\stanford_earth_matters_footer\Plugin\Block;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\term_condition\Plugin\Condition\Term;

/**
 * Provides a block with a simple text.
 *
 * @Block(
 *   id = "stanford_earth_matters_footer_block",
 *   admin_label = @Translation("Earth Matters Footer Block"),
 * )
 */
class EarthMattersFooterBlock extends BlockBase {
  /**
   * {@inheritdoc}
   */
  public function build() {

    $build = [
      '#theme' => 'stanford_earth_matters_footer_block',
    ];
    
    return $build;
  }

  /**
   * {@inheritdoc}
   */
  protected function blockAccess(AccountInterface $account) {
    return AccessResult::allowedIfHasPermission($account, 'access content');
  }

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state) {
    $config = $this->getConfiguration();

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state) {
    $this->configuration['stanford_earth_matters_footer_settings'] = $form_state->getValue('stanford_earth_matters_footer_block_settings');
  }
}