<?php

namespace Drupal\stanford_earth_matters_footer\Plugin\Block;

use Drupal\Core\Block\BlockBase;

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
}