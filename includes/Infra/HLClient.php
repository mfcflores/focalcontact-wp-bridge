<?php
namespace FCWPB\Infra;

if (!defined('ABSPATH')) {
    exit;
}

final class HLClient {

    const OPTION_KEY = 'fcwpb_settings';
    const OAUTH_KEY = 'fcwpb_oauth';

    /**
     * Get a valid access token (refresh if needed)
     */
    private static function get_access_token(): string {
        $settings = \fcwpb_get_settings();
        $oauth = get_option(self::OAUTH_KEY, []);

        if (empty($oauth['access_token']) || empty($oauth['expires_at'])) {
            throw new \RuntimeException('Missing OAuth token. Please connect HighLevel.');
        }

        // Refresh if expired (or about to)
        if (time() >= ($oauth['expires_at'] - 60)) {
            $oauth = self::refresh_token($oauth);
            $settings['oauth'] = $oauth;
            \fcwpb_update_settings($settings);
        }

        return $oauth['access_token'];
    }

    /**
     * Refresh OAuth token
     */
    private static function refresh_token(array $oauth): array {
        $settings = \fcwpb_get_settings();

        $response = wp_remote_post(
            'https://services.leadconnectorhq.com/oauth/token',
            [
                'headers' => [
                    'Content-Type' => 'application/x-www-form-urlencoded',
                ],
                'body' => [
                    'grant_type'    => 'refresh_token',
                    'refresh_token' => $oauth['refresh_token'],
                    'client_id'     => $settings['connection']['client_id'],
                    'client_secret' => $settings['connection']['client_secret'],
                ],
                'timeout' => 20,
            ]
        );

        if (is_wp_error($response)) {
            throw new \RuntimeException($response->get_error_message());
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (empty($body['access_token'])) {
            throw new \RuntimeException('Invalid refresh response from HighLevel');
        }

        return [
            'access_token'  => $body['access_token'],
            'refresh_token' => $body['refresh_token'] ?? $oauth['refresh_token'],
            'expires_at'    => time() + intval($body['expires_in'] ?? 3600),
            'location_id'   => $oauth['location_id'], // keep existing
        ];
    }
    
    public static function get(string $endpoint): array {
        $token = self::get_access_token();

        $url = 'https://services.leadconnectorhq.com/' . ltrim($endpoint, '/');

        $response = wp_remote_get($url, [
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
                'Version'       => '2021-07-28',
                'Accept'        => 'application/json',
            ],
            'timeout' => 20,
        ]);

        if (is_wp_error($response)) {
            throw new \RuntimeException($response->get_error_message());
        }

        $code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $json = json_decode($body, true);

        return [
            'ok'   => $code >= 200 && $code < 300,
            'code' => $code,
            'body' => $json ?? $body,
        ];
    }

    /**
     * Low-level POST request
     */
    public static function post(string $endpoint, array $data): array {
        $token = self::get_access_token();

        $url = 'https://services.leadconnectorhq.com/' . ltrim($endpoint, '/');

        $response = wp_remote_post($url, [
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
                'Version'       => '2021-07-28',
                'Content-Type'  => 'application/json',
                'Accept'        => 'application/json',
            ],
            'body'    => wp_json_encode($data),
            'timeout' => 20,
        ]);

        if (is_wp_error($response)) {
            throw new \RuntimeException($response->get_error_message());
        }

        $code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $json = json_decode($body, true);

        if ($code < 200 || $code >= 300) {
            \fcwpb_log('error', 'HighLevel API error', [
                'endpoint' => $endpoint,
                'code'     => $code,
                'body'     => $body,
            ]);
            return ['ok' => false, 'code' => $code, 'body' => $body];
        }

        return ['ok' => true, 'code' => $code, 'body' => $json];
    }

    /**
     * Upsert contact
     */
    public static function upsert_contact(array $contact): array {
        $settings = \fcwpb_get_settings();
        $oauth = get_option(self::OAUTH_KEY, []);

        if (empty($contact['locationId']) && !empty($oauth['location_id'])) {
            $contact['locationId'] = $oauth['location_id'];
        }

        return self::post('contacts/upsert', $contact);
    }
}
