<?php
namespace FCWPB\Infra;

if (!defined('ABSPATH')) {
    exit;
}

final class HLClient {

    // Storage keys for options
    const OPT = 'fcwpb_ghl_oauth';

    /**
     * Get stored token data
     */
    private static function get_token_data(): array {
        $data = get_option(self::OPT, []);
        if (!is_array($data)) {
            return [];
        }
        return $data;
    }

    /**
     * Save token data
     */
    private static function set_token_data(array $data): void {
        update_option(self::OPT, $data, true);
    }

    /**
     * Refresh access token if expired
     */
    private static function ensure_token(): string {
        $data = self::get_token_data();
        $now = time();

        // If no token stored, bail
        if (empty($data['access_token']) || empty($data['expires_at'])) {
            throw new \RuntimeException('Missing GHL OAuth token — connect the app first.');
        }

        // Refresh if expired or close to expiry (e.g., within 60 seconds)
        if ($now >= $data['expires_at'] - 60) {
            $data = self::refresh_token($data['refresh_token']);
            self::set_token_data($data);
        }

        return $data['access_token'];
    }

    /**
     * Refresh OAuth token
     */
    private static function refresh_token(string $refresh_token): array {
        $settings = \fcwpb_get_settings();

        $response = wp_remote_post('https://services.leadconnectorhq.com/oauth/token', [
            'body' => [
                'grant_type'    => 'refresh_token',
                'refresh_token' => $refresh_token,
                'client_id'     => $settings['connection']['client_id'] ?? '',
                'client_secret' => $settings['connection']['client_secret'] ?? '',
            ],
            'timeout' => 20,
        ]);

        if (is_wp_error($response)) {
            throw new \RuntimeException('OAuth refresh failed: ' . $response->get_error_message());
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        if (empty($body['access_token']) || empty($body['refresh_token'])) {
            throw new \RuntimeException('Invalid refresh response from GHL');
        }

        return [
            'access_token'  => $body['access_token'],
            'refresh_token' => $body['refresh_token'],
            'expires_at'    => time() + intval($body['expires_in']),
            'locationId'    => $body['locationId'] ?? '',
        ];
    }

    /**
     * Low‑level POST
     */
    public static function post(string $endpoint, array $data): array {
        $token = self::ensure_token();
        $base  = 'https://services.leadconnectorhq.com';

        $url = rtrim($base, '/') . '/' . ltrim($endpoint, '/');

        $args = [
            'timeout' => 20,
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
                'Version'       => '2021-07-28',
                'Content-Type'  => 'application/json',
                'Accept'        => 'application/json',
            ],
            'body' => wp_json_encode($data),
        ];

        $res = wp_remote_post($url, $args);
        if (is_wp_error($res)) {
            throw new \RuntimeException($res->get_error_message());
        }

        $code = wp_remote_retrieve_response_code($res);
        $body = wp_remote_retrieve_body($res);
        $json = json_decode($body, true);

        if ($code < 200 || $code >= 300) {
            \fcwpb_log('error', 'HL API error', ['code' => $code, 'body' => $body, 'endpoint' => $endpoint]);
            return ['ok' => false, 'code' => $code, 'body' => $body];
        }

        return ['ok' => true, 'code' => $code, 'body' => $json ?? $body];
    }

    /**
     * Upsert a contact
     */
    public static function upsert_contact(array $contact): array {
        return self::post('contacts/upsert', $contact);
    }
}
