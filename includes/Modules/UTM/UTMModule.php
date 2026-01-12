<?php
namespace FCWPB\Modules\UTM;

if (!defined('ABSPATH')) { exit; }

use FCWPB\Core\Modules\ModuleInterface;

final class UTMModule implements ModuleInterface {

    public function key(): string { return 'utm'; }
    public function label(): string { return 'UTM & Attribution Persistence'; }
    public function description(): string { return 'Captures UTMs and persists them across pages/sessions, then appends to forms.'; }
    public function is_enabled(): bool { return \fcwpb_is_module_enabled($this->key()); }

    public function init(): void {
        add_action('wp_enqueue_scripts', [$this, 'enqueue'], 5);
    }

    public function enqueue(): void {
        if (is_admin()) return;

        $settings = \fcwpb_get_settings();
        $utm = $settings['utm'] ?? [];

        if (empty($utm['load_everywhere'])) {
            if (function_exists('is_checkout') && is_checkout()) {
                // ok
            } elseif (function_exists('is_cart') && is_cart()) {
                // ok
            } else {
                return;
            }
        }

        wp_register_script(
            'fcwpb-utm',
            FCWPB_URL . 'assets/js/utm.js',
            [],
            FCWPB_VERSION,
            true
        );

        $param_keys = \fcwpb_sanitize_csv_keys((string)($utm['param_keys'] ?? 'utm_source,utm_medium,utm_campaign,utm_term,utm_content,gclid,fbclid,msclkid'));
        wp_localize_script('fcwpb-utm', 'FCWPB_UTM', [
            'keys' => $param_keys,
            'storage' => $utm['storage'] ?? 'cookie',
            'cookieDays' => intval($utm['cookie_days'] ?? 90),
            'appendToForms' => !empty($utm['append_to_forms']),
            'fieldPrefix' => $utm['form_field_prefix'] ?? 'fc_',
            'restEventUrl' => esc_url_raw(rest_url('fcwpb/v1/event')),
        ]);

        wp_enqueue_script('fcwpb-utm');
    }
}
