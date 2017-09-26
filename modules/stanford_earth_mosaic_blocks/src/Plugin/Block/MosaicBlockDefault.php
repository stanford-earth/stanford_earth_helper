<?php

namespace Drupal\stanford_earth_mosaic_blocks\Plugin\Block;

use Drupal\Core\Block\BlockBase;

/**
 * Provides a 'Hello' Block.
 *
 * @Block(
 *   id = "mosaic_block_default",
 *   admin_label = @Translation("Mosaic Block - 1"),
 * )
 */
class MosaicBlockDefault extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() {
    return array(
      '#pattern' => 'section_photo_mosaic_quotes',
    );
  }

}
