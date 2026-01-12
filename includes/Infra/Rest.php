<?php
namespace FCWPB\Infra;

if (!defined('ABSPATH')) { exit; }

final class Rest {

    public static function init(): void {
        /*add_action('rest_api_init', function () {
            register_rest_route('fcwpb/v1', '/event', [
                'methods'  => 'POST',
                'callback' => [__CLASS__, 'handle_event'],
                'permission_callback' => '__return_true',
            ]);
        });*/

        add_action('rest_api_init', function () {
            register_rest_route('fcwpb/v1', '/test-contact', [
                'methods'  => 'GET',
                'permission_callback' => '__return_true',
                'callback' => function () {
                    try {
                        $result = \FCWPB\Infra\HLClient::post('contacts', [
                            'email'     => 'test+' . time() . '@example.com',
                            'firstName' => 'Test',
                            'lastName'  => 'Contact',
                        ]);

                        return new \WP_REST_Response([
                            'ok'     => true,
                            'result' => $result,
                        ], 200);

                    } catch (\Throwable $e) {
                        return new \WP_REST_Response([
                            'ok'    => false,
                            'error' => $e->getMessage(),
                        ], 500);
                    }
                },
            ]);
        });

    }

    private static function snake_case(string $s): string {
        $s = strtolower(trim($s));
        $s = preg_replace('/[^a-z0-9]+/', '_', $s);
        $s = trim($s, '_');
        return $s ?: 'event';
    }

    private static function iso_utc_from_ms(int $ms): string {
        $sec = (int) floor($ms / 1000);
        return gmdate('c', $sec);
    }

    private static function try_resolve_identity(array $payload): array {
        $data = is_array($payload['data'] ?? null) ? $payload['data'] : [];

        $email = sanitize_email($data['email'] ?? '');
        $phone = sanitize_text_field($data['phone'] ?? '');

        // If logged-in, use WP user as a strong identity
        if ((!$email || !$phone) && is_user_logged_in()) {
            $u = wp_get_current_user();
            if ($u && $u->exists()) {
                if (!$email && !empty($u->user_email)) $email = sanitize_email($u->user_email);
            }
        }

        // WooCommerce: if available, try customer session email
        if ((!$email || !$phone) && function_exists('WC')) {
            try {
                $wc = WC();
                if ($wc && isset($wc->customer)) {
                    if (!$email && method_exists($wc->customer, 'get_email')) {
                        $e = $wc->customer->get_email();
                        if ($e) $email = sanitize_email($e);
                    }
                    if (!$phone && method_exists($wc->customer, 'get_billing_phone')) {
                        $p = $wc->customer->get_billing_phone();
                        if ($p) $phone = sanitize_text_field($p);
                    }
                }
            } catch (\Throwable $e) {}
        }

        return ['email' => $email, 'phone' => $phone];
    }

    public static function handle_event(\WP_REST_Request $req): \WP_REST_Response {
        $p = $req->get_json_params();
        if (!is_array($p)) $p = [];

        $type = sanitize_key($p['type'] ?? '');
        if (!$type) {
            return new \WP_REST_Response(['ok' => false, 'error' => 'Missing type'], 400);
        }

        Queue::enqueue('hl_event', [
            'event' => $type,
            'event_id' => sanitize_text_field($p['id'] ?? ''),
            'ts' => intval($p['ts'] ?? time()),
            'data' => $p['data'] ?? [],
            'context' => [
                'site' => home_url('/'),
                'received_at' => time(),
            ],
        ]);

        return new \WP_REST_Response(['ok' => true], 200);
    }
}
