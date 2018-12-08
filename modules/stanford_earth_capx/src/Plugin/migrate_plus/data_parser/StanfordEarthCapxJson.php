<?php

namespace Drupal\stanford_earth_capx\Plugin\migrate_plus\data_parser;

use Drupal\migrate_plus\Plugin\migrate_plus\data_parser\Json;

/**
 * Obtain JSON data for migration using this extension of migrate_plus Json API.
 *
 * @DataParser(
 *   id = "stanford_earth_capx_json",
 *   title = @Translation("Stanford Earth CapX JSON")
 * )
 */
class StanfordEarthCapxJson extends Json {

  protected $activeUrl;

  /**
   * Retrieves the JSON data and returns it as an array.
   *
   * @param string $url
   *   URL of a JSON feed.
   *
   * @return array
   *   The selected data to be iterated.
   *
   * @throws \GuzzleHttp\Exception\RequestException
   */
  protected function getSourceData($url) {
    $continue = TRUE;
    $source_data_out = [];
    while ($continue) {
      $continue = FALSE;

      $response = $this->getDataFetcherPlugin()->getResponseContent($url);

      // Convert objects to associative arrays.
      $source_data = json_decode($response, TRUE);

      // If json_decode() has returned NULL, it might be that the data isn't
      // valid utf8 - see http://php.net/manual/en/function.json-decode.php#86997.
      if (is_null($source_data)) {
        $utf8response = utf8_encode($response);
        $source_data = json_decode($utf8response, TRUE);
      }

      if (isset($source_data['page']) && intval($source_data['page']) > 0 &&
        isset($source_data['totalPages']) &&
        intval($source_data['totalPages']) > intval($source_data['page'])) {
        $nextPage = intval($source_data['page']) + 1;
        $url .= '&p=' . $nextPage;
        $continue = TRUE;
      }

      // Backwards-compatibility for depth selection.
      if (is_int($this->itemSelector)) {
        $source_data = $this->selectByDepth($source_data);
      }
      else {
        // Otherwise, we're using xpath-like selectors.
        $selectors = explode('/', trim($this->itemSelector, '/'));
        foreach ($selectors as $selector) {
          if (!empty($selector)) {
            $source_data = $source_data[$selector];
          }
        }
      }
      $source_data_out = array_merge($source_data_out, $source_data);
    }
    return $source_data_out;
  }

  /**
   * Return the protected activeUrl index into the urls array.
   */
  public function getActiveUrl() {
    return $this->activeUrl;
  }

}


