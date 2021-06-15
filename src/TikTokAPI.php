<?php

namespace TikTokMarketingAPI;

use Exception;
use TikTokMarketingAPI\Models\Advertiser;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

/**
 * Class TikTokAPI
 * @package TTAPI
 *
 * @property string $app_id
 * @property string $secret
 */
class TikTokAPI {
    const BASE_URL = "https://ads.tiktok.com/open_api/v1.2";
    const SANDBOX_URL = "https://sandbox-ads.tiktok.com/open_api/v1.2";

    public function __construct($app_id = "", $secret = "") {
        $this->app_id = $app_id ?? getenv('TIKTOK_APP_ID');
        $this->secret = $secret ?? getenv('TIKTOK_SECRET');
    }

    /**
     * @throws GuzzleException
     * @throws Exception
     */
    public function getAccessToken($auth_code = "") {
        $auth_code ??= $_GET['auth_code'];

        if (empty($auth_code)) {
            throw new Exception("TikTokMarketingAPI: auth_code missing. Did you initialized the Authentication flow?");
        }

        return $this->execute("oauth2/access_token/", [
            'auth_code' => $auth_code
        ]);
    }

    /**
     * @throws GuzzleException
     */
    public function subscribeToLeads(Advertiser $advertiser, $form_id, $callback) {
        [$advertiser_id, $access_token] = $this->getAdvertiserData($advertiser);

        return $this->execute("subscription/subscribe/", [
            'subscription_detail' => [
                'advertiser_id' => $advertiser_id,
                'access_token' => $access_token,
                'page_id' => $form_id
            ],
            'object' => 'LEAD',
            'url' => $callback
        ]);
    }

    /**
     * @throws GuzzleException
     */
    public function unsubscribeToLeads($subscription_id) {
        $this->execute("subscription/unsubscribe/", [
            'subscription_id' => $subscription_id
        ]);
    }

    /**
     * @throws GuzzleException
     */
    public function getSubscriptions() {
        return $this->execute("subscription/get/", [
            'object' => 'LEAD',
        ], 'GET');
    }

    /**
     * @throws GuzzleException
     */
    public function getForms(Advertiser $advertiser, $full = false, $sandbox = false) {
        [$advertiser_id, $access_token] = $this->getAdvertiserData($advertiser);

        $result = $this->execute("pages/get/", [
            'business_type' => 'LEAD_GEN',
            'advertiser_id' => $advertiser_id,
            'access_token' => $access_token,
            'sandbox' => $sandbox
        ], 'GET');

        if (!$full && array_key_exists('list', $result)) {
            $result = $result['list'];
        }

        return $result ?? [];
    }

    /**
     * @throws GuzzleException
     */
    public function createTestLead(Advertiser $advertiser, $page_id) {
        $this->deleteTestLead($advertiser, $page_id);

        [$advertiser_id, $access_token] = $this->getAdvertiserData($advertiser);

        return $this->execute("pages/leads/mock/create/", [
            'advertiser_id' => $advertiser_id,
            'access_token' => $access_token,
            'page_id' => $page_id
        ]);
    }

    /**
     * @throws GuzzleException
     */
    public function deleteTestLead(Advertiser $advertiser, $page_id) {
        [$advertiser_id, $access_token] = $this->getAdvertiserData($advertiser);

        $lead = $this->getTestLead($advertiser, $page_id);

        if (!isset($lead['meta_data']))
            return $lead;

        return $this->execute("pages/leads/mock/delete/", [
            'advertiser_id' => $advertiser_id,
            'access_token' => $access_token,
            'lead_id' => $lead['meta_data']['lead_id']
        ]);
    }

    /**
     * @throws GuzzleException
     */
    public function getTestLead(Advertiser $advertiser, $page_id) {
        [$advertiser_id, $access_token] = $this->getAdvertiserData($advertiser);

        return $this->execute("pages/leads/mock/get/", [
            'advertiser_id' => $advertiser_id,
            'access_token' => $access_token,
            'page_id' => $page_id
        ], 'GET');
    }

    /**
     * @throws GuzzleException
     */
    public function getLeads(Advertiser $advertiser, $page_id) {
        [$advertiser_id, $access_token] = $this->getAdvertiserData($advertiser);

        $task = $this->execute("pages/leads/task/", [
            'advertiser_id' => $advertiser_id,
            'access_token' => $access_token,
            'page_id' => $page_id
        ],'GET');

        sleep(10);

        return $this->execute("pages/leads/task/download/", [
            'advertiser_id' => $advertiser_id,
            'access_token' => $access_token,
            'task_id' => $task['task_id']
        ],'GET');
    }

    /**
     * @throws GuzzleException
     */
    private function execute($url, $data, $method = "POST") {
        $client = new Client();

        $options = [
            'verify' => $data['verifyssl'] ?? true,
            'sandbox' => false
        ];

        if (isset($data['sandbox'])) {
            $options['sandbox'] = $data['sandbox'];
            unset($data['sandbox']);
        }

        $data = array_merge($data, [
            'app_id' => $this->app_id,
            'secret' => $this->secret
        ]);

        $options["json"] = $data;
        $options['headers'] = [
            "Content-Type" => 'application/json'
        ];

        if (isset($data['access_token'])) {
            $options['headers']["Access-Token"] = $data['access_token'];
        }

        if ($options['sandbox']) {
            $response = $client->request($method, self::SANDBOX_URL."/".$url, $options);
        } else {
            $response = $client->request($method, self::BASE_URL."/".$url, $options);
        }

        $response = $response->getBody()->getContents();

        if (!!json_decode($response)) {
            $response = json_decode($response, true);

            if (isset($response['data'])) {
                $response = $response['data'];
            }
        }

        return $response;
    }

    private function getAdvertiserData(Advertiser $advertiser): array {
        return [$advertiser->advertiser_id, $advertiser->access_token];
    }
}
