<?php
namespace FCWPB\Infra;

if (!defined('ABSPATH')) { exit; }

final class HLClient {

    public static function post(string $endpoint, array $data): array {
        $settings = \fcwpb_get_settings();
        $base = rtrim($settings['connection']['api_base'] ?? '', '/');
        $key  = trim($settings['connection']['api_key'] ?? '');

        if ($base === '' || $key === '') {
            throw new \RuntimeException('Missing API base or API key.');
        }

        $url = $base . '/' . ltrim($endpoint, '/');

        $args = [
            'timeout' => 15,
            'headers' => [
                'Authorization' => 'Bearer ' . $key,
                'Content-Type'  => 'application/json',
                'Accept'        => 'application/json',
                'Version'       => '2021-07-28',
                'User-Agent'    => 'FCWPB/' . FCWPB_VERSION . ' (' . home_url('/') . ')',
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
     * Upsert a contact (create-or-update) according to HighLevel's duplicate rules.
     * Endpoint: POST /contacts/upsert  :contentReference[oaicite:3]{index=3}
     */
    public static function upsert_contact(array $contact): array {
        return self::post('contacts/upsert', $contact);
    }
}