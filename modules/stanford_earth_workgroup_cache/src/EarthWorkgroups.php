<?php

namespace Drupal\stanford_earth_workgroup_cache;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\RequestException;
use Drupal\Core\Config\ImmutableConfig;

class EarthWorkgroups {

    protected $config;
    protected $httpClient;

    /**
     * Constructs a CropWidgetForm object.
     *
     * @param \GuzzleHttp\ClientInterface $http_client
     *   The Guzzle HTTP client.
     */
    public function __construct(ClientInterface $http_client,
                                ImmutableConfig $config = null) {
        $this->httpClient = $http_client;
        $this->config = $config;
    }

    public function getWorkgroupMembers($wg, $certin = null, $keyin = null) {
        try {
            if (!empty($certin) || !empty($keyin)) {
                $key = $keyin;
                $cert = $certin;
            } else {
                $key = $this->config->get('stanford_earth_workgroup_key');
                $cert = $this->config->get('stanford_earth_workgroup_cert');
            }

            $result = $this->httpClient->request('GET',
                'https://workgroupsvc.stanford.edu/v1/workgroups/' . $wg,
                ['cert' => $cert, 'ssl_key' => $key]);
            if ($result->getStatusCode() != 200) {
                \Drupal::logger('type')->error('Unable to get workgroup ' . $wg . '. ' . $result->getReasonPhrase());

                return FALSE;
            }
            $xml = simplexml_load_string($result->getBody());
            $xpath = $xml->xpath('members');
            $attribute = 'id';
            $sunets = [];
            if (is_array($xpath)) {
                $xpath0 = reset($xpath);
                if ($xpath0 !== FALSE) {
                    $members = $xpath0->xpath('member');
                    if (is_array($members)) {
                        foreach ($members as $member) {
                            $sunets[] = (string)$member->attributes()->$attribute;
                        }
                    }
                }
            }
            return $sunets;
        }
        catch (\Exception $e) {
            \Drupal::logger('type')->error('Workgroup API for ' . $wg . '. ' . $e->getMessage());
            return FALSE;
        }
    }
}
