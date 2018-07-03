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
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  public function getActiveUrl() {
    return $this->activeUrl;
  }

}
