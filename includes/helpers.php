<?php
if (!defined('ABSPATH')) { exit; }

function fcwpb_get_settings(): array {
    static $cache = null;

    if ($cache !== null) {
        return $cache;
    }

    $settings = get_option('fcwpb_settings', []);

    $defaults = [
        'connection' => [
            'client_id' => '',
            'client_secret'  => '',
        ],
        'modules' => [
            'utm' => true,
            'hl_push' => true,
            'hl_external_tracking' => false,
            'abandoned_cart' => false,
            'webhooks' => false,
        ],
        'utm' => [
            'param_keys' => 'utm_source,utm_medium,utm_campaign,utm_term,utm_content,gclid,fbclid,msclkid',
            'storage' => 'cookie', // cookie|localStorage
            'cookie_days' => 90,
            'append_to_forms' => true,
            'form_field_prefix' => 'fc_',
            'load_everywhere' => true,
        ],
        'hl_tracking' => [
            // Stored as structured fields only. Admin can paste the script; we extract these.
            'enabled' => false,
            'src' => '',
            'tracking_id' => '',
            'async' => false,
            'defer' => false,

            // Page conditions
            'public_only' => true,       // do not load for logged-in users
            'load_everywhere' => true,   // if false, use include/exclude lists
            'include_paths' => '',       // newline or comma separated path prefixes, e.g. /, /pricing, /contact
            'exclude_paths' => '',       // newline or comma separated path prefixes
        ],
        'advanced' => [
            'log_level' => 'error', // error|info|debug
            'use_queue' => true,
        ],
    ];

    // Merge only NON-OAUTH defaults
    $settings = array_replace_recursive($defaults, $settings);

    $cache = $settings;
    return $settings;
}

function fcwpb_get_default_settings(): array {
    return [
        'connection' => [
            'client_id' => '',
            'client_secret'  => '',
        ],
        'modules' => [
            'utm' => true,
            'hl_push' => true,
            'hl_external_tracking' => false,
            'abandoned_cart' => false,
            'webhooks' => false,
        ],
        'utm' => [
            'param_keys' => 'utm_source,utm_medium,utm_campaign,utm_term,utm_content,gclid,fbclid,msclkid',
            'storage' => 'cookie', // cookie|localStorage
            'cookie_days' => 90,
            'append_to_forms' => true,
            'form_field_prefix' => 'fc_',
            'load_everywhere' => true,
        ],
        'hl_tracking' => [
            // Stored as structured fields only. Admin can paste the script; we extract these.
            'enabled' => false,
            'src' => '',
            'tracking_id' => '',
            'async' => false,
            'defer' => false,

            // Page conditions
            'public_only' => true,       // do not load for logged-in users
            'load_everywhere' => true,   // if false, use include/exclude lists
            'include_paths' => '',       // newline or comma separated path prefixes, e.g. /, /pricing, /contact
            'exclude_paths' => '',       // newline or comma separated path prefixes
        ],
        'advanced' => [
            'log_level' => 'error', // error|info|debug
            'use_queue' => true,
        ],
    ];
}

function fcwpb_update_settings(array $settings): void {
    update_option('fcwpb_settings', $settings);
}

function fcwpb_is_module_enabled(string $key): bool {
    $settings = fcwpb_get_settings();
    return !empty($settings['modules'][$key]);
}

function fcwpb_log(string $level, string $message, array $context = []): void {
    $settings = fcwpb_get_settings();
    $allowed = ['error' => 0, 'info' => 1, 'debug' => 2];
    $current = $allowed[$settings['advanced']['log_level'] ?? 'error'] ?? 0;
    $incoming = $allowed[$level] ?? 0;
    if ($incoming > $current) return;

    $line = '[' . gmdate('c') . "] {$level}: {$message}";
    if (!empty($context)) $line .= ' ' . wp_json_encode($context);

    $buf = get_option('fcwpb_log', []);
    if (!is_array($buf)) $buf = [];
    $buf[] = $line;
    if (count($buf) > 200) $buf = array_slice($buf, -200);
    update_option('fcwpb_log', $buf, false);

    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log('FCWPB ' . $line);
    }
}

function fcwpb_sanitize_csv_keys(string $csv): array {
    $parts = array_map('trim', explode(',', (string)$csv));
    $parts = array_filter($parts, function ($p) { return $p !== ''; });
    $parts = array_values(array_unique($parts));
    return array_values(array_filter($parts, function ($p) {
        return (bool) preg_match('/^[a-zA-Z0-9_\-]+$/', $p);
    }));
}

function fcwpb_sanitize_path_list(string $raw): array {
    // Accept newline or comma separated.
    $raw = str_replace(["\r\n", "\r"], "\n", (string)$raw);
    $raw = str_replace(',', "\n", $raw);
    $lines = array_map('trim', explode("\n", $raw));
    $lines = array_filter($lines, function ($v) { return $v !== ''; });
    $out = [];
    foreach ($lines as $p) {
        // Only keep path component, enforce leading slash.
        $p = trim($p);
        if ($p === '') continue;
        if ($p[0] !== '/') $p = '/' . $p;
        // Drop querystring if provided.
        $p = explode('?', $p, 2)[0];
        $out[] = $p;
    }
    $out = array_values(array_unique($out));
    return $out;
}

function fcwpb_get_site_fingerprint(): string {
    $salt = defined('AUTH_SALT') ? AUTH_SALT : 'fcwpb';
    return hash('sha256', home_url('/') . '|' . $salt);
}

add_filter( 'gform_field_content', function ( $content, $field, $value, $lead_id, $form_id ) {

    // Only apply to dynamically populated fields
    if ( empty( $field->allowsPrepopulate ) || empty( $field->inputName ) ) {
        return $content;
    }

    // Build your prefixed key
    $fcwpb_key = 'fcwpb_' . sanitize_key( $field->inputName );

    // Inject data attribute into all input/select/textarea elements
    $content = preg_replace(
        '/<(input|select|textarea)([^>]+)/i',
        '<$1$2 data-fcwpb-key="' . esc_attr( $fcwpb_key ) . '"',
        $content,
        1 // Only first field element
    );

    return $content;

}, 10, 5 );