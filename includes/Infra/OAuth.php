<?php

namespace FCWPB\Infra;
use FCWPB\Admin\SettingsPage;

if (!defined('ABSPATH')) exit;

final class OAuth {

    const OPTION_KEY = 'fcwpb_settings';
    const OAUTH_KEY = 'fcwpb_oauth';

    public function init(): void {
        add_action('admin_post_fcwpb_oauth', [$this, 'handle']);
    }

    public function handle(): void {
        if (empty($_GET['code'])) {
            wp_die('Missing OAuth code');
        }

        $settings_page = new SettingsPage();

        $code = sanitize_text_field($_GET['code']);
        $settings = get_option(self::OPTION_KEY, []);

        $client_id     = $settings['connection']['client_id'] ?? '';
        $client_secret = $settings['connection']['client_secret'] ?? '';

        if (!$client_id || !$client_secret) {
            wp_die('Client credentials missing');
        }

        $redirect_uri = admin_url('admin-post.php?action=fcwpb_oauth');

        $response = wp_remote_post(
            'https://services.leadconnectorhq.com/oauth/token',
            [
                'headers' => [
                    'Content-Type' => 'application/x-www-form-urlencoded',
                ],
                'body' => [
                    'grant_type'    => 'authorization_code',
                    'client_id'     => $client_id,
                    'client_secret' => $client_secret,
                    'code'          => $code,
                    'redirect_uri'  => $redirect_uri,
                ],
                'timeout' => 20,
            ]
        );

        if (is_wp_error($response)) {
            wp_die($response->get_error_message());
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (empty($body['access_token'])) {
        \fcwpb_log('error', 'OAuth failed', $body);
            wp_die('OAuth token exchange failed');
        }
        
        $oauth = [
            'access_token'  => $body['access_token'],
            'refresh_token' => $body['refresh_token'] ?? '',
            'expires_at'    => time() + intval($body['expires_in'] ?? 3600),
            'location_id'   => $body['locationId'] ?? '',
        ];

        update_option(self::OAUTH_KEY, $oauth);

        \fcwpb_log('info', 'OAuth connected', [
            'location_id' => $oauth['location_id'],
        ]);

        wp_safe_redirect(
            admin_url('admin.php?page=fcwpb&oauth=success')
        );
        exit;
    }
}

?>