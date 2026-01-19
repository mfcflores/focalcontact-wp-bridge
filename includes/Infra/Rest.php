<?php
namespace FCWPB\Infra;

if (!defined('ABSPATH')) {
    exit;
}

class Rest {

    const OPTION_KEY = 'fcwpb_settings';
    const OAUTH_KEY = 'fcwpb_oauth';

    public static function init() {
        add_action('rest_api_init', [self::class, 'register_routes']);
    }

    public static function register_routes() {
        register_rest_route('fcwpb/v1', '/test-contact', [
            'methods'             => 'GET',
            'callback'            => [self::class, 'test_contact'],
            'permission_callback' => function() {
                return current_user_can('manage_options');
            },
        ]);

        register_rest_route('fcwpb/v1', '/oauth/callback', [
            'methods' => 'GET',
            'callback' => [self::class, 'oauth_callback'],
            'permission_callback' => '__return_true',
        ]);
    }

    public static function test_contact() {
        $settings = get_option(self::OPTION_KEY, []);
        $oauth = get_option(self::OAUTH_KEY, []);

        if (
            empty($oauth['access_token']) ||
            empty($oauth['location_id'])
        ) {
            return [
                'ok' => false,
                'error' => 'Missing access_token or location_id',
            ];
        }

        $payload = [
            'email'     => 'test-' . time() . '@example.com',
            'firstName' => 'Test',
            'lastName'  => 'Contact',
            'locationId'=> $oauth['location_id'],
        ];

        $response = wp_remote_post(
            'https://services.leadconnectorhq.com/contacts/',
            [
                'headers' => [
                    'Authorization' => 'Bearer ' . $oauth['access_token'],
                    'Version'       => '2021-07-28',
                    'Content-Type'  => 'application/json',
                ],
                'body'    => wp_json_encode($payload),
                'timeout' => 20,
            ]
        );

        if (is_wp_error($response)) {
            return [
                'ok' => false,
                'error' => $response->get_error_message(),
            ];
        }

        return [
            'ok'     => true,
            'status' => wp_remote_retrieve_response_code($response),
            'body'   => json_decode(wp_remote_retrieve_body($response), true),
        ];
    }

    public static function oauth_callback() {
        $code = isset($_GET['code']) ? sanitize_text_field($_GET['code']) : '';

        if (!$code) {
            return ['ok' => false, 'error' => 'Missing code'];
        }

        $settings = get_option(self::OPTION_KEY, []);
        $oauth = get_option(self::OAUTH_KEY, []);
        $client_id     = $settings['client_id'] ?? '';
        $version_id    = $settings['version_id'] ?? '';
        $redirect_uri  = 'https://buzzwebmedia.com.au/leadconnector/oauth'; // Must match app config
        $client_secret = $settings['client_secret'] ?? '';

        // Exchange code for tokens
        $response = wp_remote_post('https://services.leadconnectorhq.com/oauth/token', [
            'body' => [
                'grant_type'    => 'authorization_code',
                'code'          => $code,
                'client_id'     => $client_id,
                'client_secret' => $client_secret,
                'redirect_uri'  => $redirect_uri,
            ],
            'timeout' => 20,
        ]);

        if (is_wp_error($response)) {
            return ['ok' => false, 'error' => $response->get_error_message()];
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (empty($body['access_token']) || empty($body['refresh_token'])) {
            return ['ok' => false, 'error' => $body];
        }

        // Persist tokens
        $oauth['access_token']  = $body['access_token'];
        $oauth['refresh_token'] = $body['refresh_token'];
        $oauth['location_id']   = $body['locationId'] ?? '';
        update_option(self::OAUTH_KEY, $settings);

        return ['ok' => true, 'tokens' => $body];
    }
}

Rest::init();
