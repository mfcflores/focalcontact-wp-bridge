<?php
namespace FCWPB\Modules\Webhooks;

if (!defined('ABSPATH')) { exit; }

use FCWPB\Core\Modules\ModuleInterface;

final class WebhooksModule implements ModuleInterface {
    public function key(): string { return 'webhooks'; }
    public function label(): string { return 'Webhooks (Two-way sync)'; }
    public function description(): string { return 'Receives webhooks from FocalContact to update WP (optional).'; }
    public function is_enabled(): bool { return \fcwpb_is_module_enabled($this->key()); }

    public function init(): void {
        add_action('rest_api_init', function () {
            register_rest_route('fcwpb/v1', '/webhook', [
                'methods' => 'POST',
                'callback' => [$this, 'handle'],
                'permission_callback' => '__return_true',
            ]);
        });
    }

    public function handle(\WP_REST_Request $req): \WP_REST_Response {
        $payload = $req->get_json_params();
        if (!is_array($payload)) $payload = [];
        \fcwpb_log('info', 'Webhook received (placeholder)', ['keys' => array_keys($payload)]);
        return new \WP_REST_Response(['ok' => true], 200);
    }
}
