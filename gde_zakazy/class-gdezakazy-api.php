<?php

class GdeZakazy_Api
{

    const BASE = 'https://xn--80aahefmcw9m.xn--p1ai/api/v1/';
    //const BASE = 'http://gdezakazy.php/api/v1/';

    public function request($token, $method, $path, $data = [])
    {
        $ch = curl_init();
        if (!is_array($data)) {
            $data = [];
        }
        if ($method == 'GET') {
            $data['token'] = $token;
            $path .= '?'.http_build_query($data);
        } else {
            $path .= '?'.http_build_query(['token' => $token]);
            curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        }
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/4.0 (compatible; API client)');
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_URL, self::BASE.$path);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        $response = curl_exec($ch);
        $return = [
            'code' => curl_getinfo($ch, CURLINFO_RESPONSE_CODE),
            'data' => json_decode($response, true),
        ];
        curl_close($ch);
        return $return;
    }

    public function checkApiToken($token)
    {
        $data = $this->request($token, 'GET', 'track/0');
        if ($data['code'] != 404 || !is_array($data['data']) || !isset($data['data']['message']) || $data['data']['message'] != 'Track not found') {
            return false;
        }
        return true;
    }

    public function getStatus($token)
    {
        if (!$token) {
            return [
                'status' => false,
                'canAdd' => false,
            ];
        }
        $data = $this->request($token, 'GET', '');
        if ($data['code'] != 200 || !is_array($data['data'])) {
            return [
                'status' => false,
                'canAdd' => false,
            ];
        }
        $limit = ($data['data']['wordpressSubscription'] === null ? $data['data']['wordpressLimit'] : null);
        $expired = ($data['data']['wordpressSubscription'] !== null ? (new DateTime($data['data']['wordpressSubscription']))->format('d.m.Y') : null);
        return [
            'status' => true,
            'limit' => $limit,
            'expired' => $expired,
            'canAdd' => ($limit > 0 || $limit === null),
        ];
    }

}