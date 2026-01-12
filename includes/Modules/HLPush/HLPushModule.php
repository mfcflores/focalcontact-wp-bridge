<?php
namespace FCWPB\Modules\HLPush;

use FCWPB\Core\Modules\ModuleInterface;
use FCWPB\Infra\Queue;
use FCWPB\Infra\HLClient;

final class HLPushModule implements ModuleInterface {

    public function key(): string { return 'hl_push'; }
    public function label(): string { return 'Push Forms & WooCommerce to FocalContact'; }
    public function description(): string { return 'Sends submissions and orders to the connected sub-account.'; }
    public function is_enabled(): bool { return \fcwpb_is_module_enabled($this->key()); }

    public function init(): void {
        add_action('woocommerce_checkout_order_processed', [$this, 'on_order_processed'], 10, 3);
        add_action('gform_after_submission', [$this, 'on_gravityform_submission'], 10, 2);
        add_action('metform_after_store_form_data', [$this, 'on_metform_submission'], 10, 4);
    }

    private function get_location(): string {
        $tokens = \FCWPB\Infra\HLClient::get_tokens();
        return $tokens['locationId'] ?? '';
    }

    public function on_order_processed($order_id): void {
        $payload = \FCWPB\Modules\HLPush\PayloadBuilder::from_wc_order($order_id, 'order_processed');
        $payload['locationId'] = $this->get_location();
        Queue::enqueue('hl_post', ['endpoint' => 'contacts/upsert', 'data' => $payload]);
    }

    public function on_gravityform_submission($entry, $form): void {
        $payload = \FCWPB\Modules\HLPush\PayloadBuilder::from_gravity_forms($entry, $form);
        $payload['locationId'] = $this->get_location();
        Queue::enqueue('hl_post', ['endpoint' => 'contacts/upsert', 'data' => $payload]);
    }

    public function on_metform_submission(...$args): void {
        $payload = \FCWPB\Modules\HLPush\PayloadBuilder::from_metform($args);
        $payload['locationId'] = $this->get_location();
        Queue::enqueue('hl_post', ['endpoint' => 'contacts/upsert', 'data' => $payload]);
    }
}
