<?php

namespace Drupal\stanford_earth_matters_topic_bar\Plugin\Block;

use Drupal\Core\Block\BlockBase;

/**
 * Provides a block with a simple text.
 *
 * @Block(
 *   id = "stanford_earth_matters_topic_bar_block",
 *   admin_label = @Translation("Earth Matters Topic Bar Block"),
 * )
 */
class EarthMattersTopicBarBlock extends BlockBase {
  /**
   * {@inheritdoc}
   */
  public function build() {

    $build = [
      '#theme' => 'stanford_earth_matters_topic_bar_block',
    ];
    
    return $build;
  }
}