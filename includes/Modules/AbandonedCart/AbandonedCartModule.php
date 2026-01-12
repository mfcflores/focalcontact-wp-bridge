<?php
namespace FCWPB\Modules\AbandonedCart;

if (!defined('ABSPATH')) { exit; }

use FCWPB\Core\Modules\ModuleInterface;
use FCWPB\Infra\Queue;

final class AbandonedCartModule implements ModuleInterface {

    public function key(): string { return 'abandoned_cart'; }
    public function label(): string { return 'Woo Abandoned Cart'; }
    public function description(): string { return 'Captures cart/checkout identity and triggers abandon events (optional).'; }
    public function is_enabled(): bool { return \fcwpb_is_module_enabled($this->key()); }

    public function init(): void {
        if (!class_exists('WooCommerce')) return;

        add_action('wp_enqueue_scripts', [$this, 'enqueue'], 20);
        add_action('woocommerce_cart_updated', [$this, 'cart_updated'], 10);
    }

    public function enqueue(): void {
        if (is_admin()) return;
        if (!(function_exists('is_cart') && is_cart()) && !(function_exists('is_checkout') && is_checkout())) return;

        wp_register_script('fcwpb-abandon', FCWPB_URL . 'assets/js/abandoned-cart.js', [], FCWPB_VERSION, true);
        wp_localize_script('fcwpb-abandon', 'FCWPB_ABANDON', [
            'restEventUrl' => esc_url_raw(rest_url('fcwpb/v1/event')),
        ]);
        wp_enqueue_script('fcwpb-abandon');
    }

    public function cart_updated(): void {
        try {
            $payload = [
                'type' => 'cart_updated',
                'source' => 'woocommerce',
                'ts' => time(),
            ];
            Queue::enqueue('hl_post', [
                'endpoint' => 'events',
                'data' => $payload,
            ]);
        } catch (\Throwable $e) {
            \fcwpb_log('error', 'Cart updated push failed: ' . $e->getMessage());
        }
    }
}
