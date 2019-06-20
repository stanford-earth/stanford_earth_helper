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

    $wg = '';
    $privGroupPos = strpos($url, 'privGroups=');
    if ($privGroupPos !== FALSE) {
      $wg = substr($url, $privGroupPos + 11);
      $ampPos = strpos($wg, '&');
      if ($ampPos !== FALSE) {
        $wg = substr($wg, 0, $ampPos);
      }
    }

    $curUrl = $url;
    $continue = TRUE;
    $source_data_out = [];
    while ($continue) {
      $continue = FALSE;

      $response = $this->getDataFetcherPlugin()->getResponseContent($curUrl);

      // Convert objects to associative arrays.
      $source_data = json_decode($response, TRUE);

      // If json_decode() has returned NULL, it might be that the data isn't
      // valid utf8 - see http://php.net/manual/en/function.json-decode.php.
      if (is_null($source_data)) {
        $utf8response = utf8_encode($response);
        $source_data = json_decode($utf8response, TRUE);
      }

      if (isset($source_data['page']) && intval($source_data['page']) > 0 &&
        isset($source_data['totalPages']) &&
        intval($source_data['totalPages']) > intval($source_data['page'])) {
        $nextPage = 'p=' . strval(intval($source_data['page']) + 1);
        if (strpos($curUrl, '?') === FALSE) {
          $curUrl .= '?' . $nextPage;
        }
        else {
          $cut1 = strpos($curUrl, '?p=');
          if ($cut1 === FALSE) {
            $cut1 = strpos($curUrl, '&p=');
          }
          if ($cut1 === FALSE) {
            $curUrl .= '&' . $nextPage;
          }
          else {
            // $cut1 is ?p= or &p= location.
            $cut2 = strpos($curUrl, '&', intval($cut1) + 1);
            if ($cut2 === FALSE) {
              $curPage = substr($curUrl, intval($cut1) + 1);
            }
            else {
              $curPage = substr($curUrl, (intval($cut1) + 1), intval($cut2) - intval($cut1) - 1);
            }
            $curUrl = str_replace($curPage, $nextPage, $curUrl);
          }
        }
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
            if (empty($source_data[$selector])) {
              $source_data = [];
            }
            else {
              $source_data = $source_data[$selector];
            }
          }
        }
      }
      $source_data_out = array_merge($source_data_out, $source_data);
    }

    // Now we want to get the members of the workgroup from the workgroup API
    // and see if anyone is missing.
    if (!empty($wg)) {
      $wg_service = \Drupal::service('stanford_earth_workgroups.workgroup');
      $wg_members = $wg_service->getMembers($wg);
      if ($wg_members['status']['member_count'] > 0) {
        // Put our sunets from CAP in an array so easier to search
        // $wg_cap_members = [].
        foreach ($source_data_out as $profile) {
          $wg_cap_members[] = $profile['uid'];
        }
        foreach ($wg_members['members'] as $sunet => $name) {
          if (array_search($sunet, $wg_cap_members) === FALSE &&
            !empty($name)) {
            if (strpos($name, ',') !== FALSE) {
              $nsplit = explode(',', $name, 2);
              $lname = $nsplit[0];
              $fname = '';
              if (count($nsplit) > 1) {
                $fname = trim($nsplit[1]);
              }
              $dname = $fname . ' ' . $lname;
              $alias = strtolower($fname . '-' . $lname);
            }
            else {
              $dname = $name;
              $lname = $name;
              $fname = '';
              $alias = strtolower($name);
            }

            $source_data_out[] = [
              'uid' => $sunet,
              'displayName' => $dname,
              'alias' => $alias,
              'names' => [
                'preferred' => [
                  'firstName' => $fname,
                  'lastName' => $lname,
                ],
              ],
            ];
          }
        }
      }
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
