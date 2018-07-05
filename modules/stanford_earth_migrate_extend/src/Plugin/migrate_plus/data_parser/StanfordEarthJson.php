<?php

namespace Drupal\stanford_earth_migrate_extend\Plugin\migrate_plus\data_parser;

use Drupal\migrate_plus\Plugin\migrate_plus\data_parser\Json;

/**
 * Obtain JSON data for migration using this extension of migrate_plus Json API.
 *
 * @DataParser(
 *   id = "stanford_earth_json",
 *   title = @Translation("Stanford Earth JSON")
 * )
 */
class StanfordEarthJson extends Json {

  protected $activeUrl;

  /**
   * Return the protected activeUrl index into the urls array.
   */
  public function getActiveUrl() {
    return $this->activeUrl;
  }

}
