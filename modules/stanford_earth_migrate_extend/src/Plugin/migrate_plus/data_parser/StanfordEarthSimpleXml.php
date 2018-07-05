<?php

namespace Drupal\stanford_earth_migrate_extend\Plugin\migrate_plus\data_parser;

use Drupal\migrate_plus\Plugin\migrate_plus\data_parser\SimpleXml;

/**
 * Obtain XML data for migration using the extended SimpleXML API.
 *
 * @DataParser(
 *   id = "stanford_earth_simple_xml",
 *   title = @Translation("Stanford Earth Simple XML")
 * )
 */
class StanfordEarthSimpleXml extends SimpleXml {

  protected $activeUrl;

  /**
   * Return the protected activeUrl index into the urls array.
   */
  public function getActiveUrl() {
    return $this->activeUrl;
  }

}
