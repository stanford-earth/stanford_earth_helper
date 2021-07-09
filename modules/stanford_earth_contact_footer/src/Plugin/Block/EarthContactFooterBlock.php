<?php

namespace Drupal\stanford_earth_contact_footer\Plugin\Block;

use Drupal\Core\Block\BlockBase;

/**
 * Provides a block with a simple text.
 *
 * @Block(
 *   id = "stanford_earth_contact_footer_block",
 *   admin_label = @Translation("Earth Contact Footer Block"),
 * )
 */
class EarthContactFooterBlock extends BlockBase {
  /**
   * {@inheritdoc}
   */
  public function build() {

    $build = [
      '#theme' => 'stanford_earth_contact_footer_block',
    ];
    
    return $build;
  }
}