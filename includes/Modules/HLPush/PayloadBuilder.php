<?php
namespace FCWPB\Modules\HLPush;

if (!defined('ABSPATH')) { exit; }

final class PayloadBuilder {

    /* -----------------------------
     * WooCommerce
     * ----------------------------- */
    public static function from_wc_order(int $order_id, string $event): array {
        if (!function_exists('wc_get_order')) {
            throw new \RuntimeException('WooCommerce not available');
        }

        $order = wc_get_order($order_id);
        if (!$order) {
            throw new \RuntimeException('Order not found');
        }

        $items = [];
        foreach ($order->get_items() as $item) {
            $items[] = [
                'name'       => $item->get_name(),
                'qty'        => (int) $item->get_quantity(),
                'total'      => (float) $item->get_total(),
                'product_id' => (int) $item->get_product_id(),
            ];
        }

        return [
            'event'  => $event,
            'source' => 'woocommerce',
            'contact' => [
                'email'     => (string) $order->get_billing_email(),
                'phone'     => (string) $order->get_billing_phone(),
                'firstName' => (string) $order->get_billing_first_name(),
                'lastName'  => (string) $order->get_billing_last_name(),
            ],
            'order' => [
                'id'       => $order_id,
                'status'   => $order->get_status(),
                'total'    => (float) $order->get_total(),
                'currency' => $order->get_currency(),
                'items'    => $items,
            ],
            'utm'  => self::read_utm_from_request_or_cookie(),
            'meta' => self::meta(),
        ];
    }

    /* -----------------------------
     * MetForm
     * ----------------------------- */
    public static function from_metform(array $args): array {
        return [
            'event'  => 'metform_submission',
            'source' => 'metform',
            'payload'=> $args,
            'utm'    => self::read_utm_from_request_or_cookie(),
            'meta'   => self::meta(),
        ];
    }

    /* -----------------------------
     * Gravity Forms
     * ----------------------------- */
    public static function from_gravity_forms(array $entry, array $form): array {
        $fields = [];
        $email = $phone = $first = $last = '';

        foreach ($form['fields'] as $field) {
            $fid   = (string) $field->id;
            $label = $field->label ?? "field_$fid";
            $value = rgar($entry, $fid);

            if ($value === '' || $value === null) {
                continue;
            }

            $fields[$fid] = [
                'label' => $label,
                'value' => $value,
            ];

            // Auto-detect common contact fields
            if ($field->type === 'email' && !$email) {
                $email = $value;
            }
            if ($field->type === 'phone' && !$phone) {
                $phone = $value;
            }
            if ($field->type === 'name' && is_array($value)) {
                $first = $value['first'] ?? '';
                $last  = $value['last'] ?? '';
            }
        }

        return [
            'event'  => 'gravity_form_submission',
            'source' => 'gravity_forms',
            'contact' => array_filter([
                'email'     => $email,
                'phone'     => $phone,
                'firstName' => $first,
                'lastName'  => $last,
            ]),
            'form' => [
                'id'     => $form['id'],
                'title'  => $form['title'],
                'entry'  => rgar($entry, 'id'),
                'fields' => $fields,
            ],
            'utm'  => self::read_utm_from_request_or_cookie(),
            'meta' => self::meta(),
        ];
    }

    /* -----------------------------
     * Helpers
     * ----------------------------- */
    private static function read_utm_from_request_or_cookie(): array {
        $settings = \fcwpb_get_settings();
        $keys = \fcwpb_sanitize_csv_keys((string)($settings['utm']['param_keys'] ?? ''));
        $out = [];

        foreach ($keys as $k) {
            $prefixed = ($settings['utm']['form_field_prefix'] ?? 'fc_') . $k;

            if (!empty($_REQUEST[$prefixed])) {
                $out[$k] = sanitize_text_field(wp_unslash($_REQUEST[$prefixed]));
                continue;
            }

            $cookie = 'fcwpb_' . $k;
            if (!empty($_COOKIE[$cookie])) {
                $out[$k] = sanitize_text_field(wp_unslash($_COOKIE[$cookie]));
            }
        }

        return $out;
    }

    private static function meta(): array {
        return [
            'site' => home_url('/'),
            'ts'   => time(),
        ];
    }
}
