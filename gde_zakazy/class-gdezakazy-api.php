<?php

class GdeZakazy_Api
{

    const BASE = 'https://xn--80aahefmcw9m.xn--p1ai/api/v1/';
    //const BASE = 'http://gdezakazy.php/api/v1/';

    public function request($token, $method, $path, $data = [])
    {
        if (!is_array($data)) {
            $data = [];
        }
        if ($method == 'GET') {
            $data['token'] = $token;
            $path .= '?'.http_build_query($data);
            $response = wp_remote_get(self::BASE.$path, [
                'timeout' => 5,
            ]);
        } else {
            $path .= '?'.http_build_query(['token' => $token]);
            $response = wp_remote_post(self::BASE.$path, [
                'method' => $method,
                'timeout' => 5,
                'body' => $data,
            ]);
        }
        return [
            'code' => wp_remote_retrieve_response_code($response),
            'data' => json_decode(wp_remote_retrieve_body($response), true),
        ];
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