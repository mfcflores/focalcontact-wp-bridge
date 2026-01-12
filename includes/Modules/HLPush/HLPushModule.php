<?php
namespace FCWPB\Modules\HLPush;

if (!defined('ABSPATH')) { exit; }

use FCWPB\Core\Modules\ModuleInterface;
use FCWPB\Infra\Queue;

final class HLPushModule implements ModuleInterface {

    public function key(): string { return 'hl_push'; }
    public function label(): string { return 'Push Forms & WooCommerce to FocalContact'; }
    public function description(): string { return 'Sends submissions and orders to the connected sub-account.'; }
    public function is_enabled(): bool { return \fcwpb_is_module_enabled($this->key()); }

    public function init(): void {
        // WooCommerce
        add_action('woocommerce_checkout_order_processed', [$this, 'on_order_processed'], 10, 3);
        add_action('woocommerce_payment_complete', [$this, 'on_payment_complete'], 10);

        // Forms
        add_action('metform_after_store_form_data', [$this, 'on_metform_submission'], 10, 4);
        add_action('gform_after_submission', [$this, 'on_gravityform_submission'], 10, 2);
    }

    /* -----------------------------
     * WooCommerce
     * ----------------------------- */
    public function on_order_processed($order_id): void {
        $this->push_wc((int) $order_id, 'order_processed');
    }

    public function on_payment_complete($order_id): void {
        $this->push_wc((int) $order_id, 'payment_complete');
    }

    private function push_wc(int $order_id, string $event): void {
        if (!$order_id) return;

        try {
            Queue::enqueue('hl_post', [
                'endpoint' => 'contacts',
                'data'     => PayloadBuilder::from_wc_order($order_id, $event),
            ]);
        } catch (\Throwable $e) {
            \fcwpb_log('error', 'WC push failed: ' . $e->getMessage(), ['order_id' => $order_id]);
        }
    }

    /* -----------------------------
     * MetForm
     * ----------------------------- */
    public function on_metform_submission(...$args): void {
        try {
            Queue::enqueue('hl_post', [
                'endpoint' => 'contacts',
                'data'     => PayloadBuilder::from_metform($args),
            ]);
        } catch (\Throwable $e) {
            \fcwpb_log('error', 'MetForm push failed: ' . $e->getMessage());
        }
    }

    /* -----------------------------
     * Gravity Forms
     * ----------------------------- */
    public function on_gravityform_submission($entry, $form): void {
        try {
            Queue::enqueue('hl_post', [
                'endpoint' => 'contacts',
                'data'     => PayloadBuilder::from_gravity_forms($entry, $form),
            ]);
        } catch (\Throwable $e) {
            \fcwpb_log('error', 'Gravity Forms push failed: ' . $e->getMessage());
        }
    }
}
