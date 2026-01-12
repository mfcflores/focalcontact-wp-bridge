<?php
namespace FCWPB\Admin;

if (!defined('ABSPATH')) { exit; }

final class SettingsPage {

    public function init(): void {
        add_action('admin_menu', [$this, 'menu']);
        add_action('admin_init', [$this, 'register_settings']);
    }

    public function menu(): void {
        add_menu_page(
            'FocalContact',
            'FocalContact',
            'manage_options',
            'fcwpb',
            [$this, 'render'],
            'dashicons-admin-links'
        );
    }

    public function register_settings(): void {
        register_setting('fcwpb_settings_group', 'fcwpb_settings', [
            'type' => 'array',
            'sanitize_callback' => [$this, 'sanitize'],
            'default' => \fcwpb_get_default_settings(),
        ]);
    }

    public function sanitize($input): array {
        $defaults = \fcwpb_get_settings();

        if (!is_array($input)) {
            return $defaults;
        }

        $settings = $defaults;

        // Connection
        $settings['connection']['client_id'] = sanitize_text_field($input['connection']['client_id'] ?? $settings['connection']['client_id']);
        $settings['connection']['client_secret']  = sanitize_text_field($input['connection']['client_secret'] ?? $settings['connection']['client_secret']);

        // Modules
        foreach ($settings['modules'] as $k => $_) {
            $settings['modules'][$k] = !empty($input['modules'][$k]);
        }

        // UTM
        $settings['utm']['param_keys'] = sanitize_text_field($input['utm']['param_keys'] ?? $settings['utm']['param_keys']);
        $settings['utm']['storage'] = in_array(($input['utm']['storage'] ?? ''), ['cookie', 'localStorage'], true) ? $input['utm']['storage'] : $settings['utm']['storage'];
        $settings['utm']['cookie_days'] = max(1, intval($input['utm']['cookie_days'] ?? $settings['utm']['cookie_days']));
        $settings['utm']['append_to_forms'] = !empty($input['utm']['append_to_forms']);
        $settings['utm']['form_field_prefix'] = preg_replace('/[^a-zA-Z0-9_\-]/', '', $input['utm']['form_field_prefix'] ?? $settings['utm']['form_field_prefix']);
        $settings['utm']['load_everywhere'] = !empty($input['utm']['load_everywhere']);

        // HL Tracking
        $settings['hl_tracking']['enabled'] = !empty($input['hl_tracking']['enabled']);
        $settings['hl_tracking']['public_only'] = !empty($input['hl_tracking']['public_only']);
        $settings['hl_tracking']['load_everywhere'] = !empty($input['hl_tracking']['load_everywhere']);
        $settings['hl_tracking']['include_paths'] = sanitize_textarea_field($input['hl_tracking']['include_paths'] ?? $settings['hl_tracking']['include_paths']);
        $settings['hl_tracking']['exclude_paths'] = sanitize_textarea_field($input['hl_tracking']['exclude_paths'] ?? $settings['hl_tracking']['exclude_paths']);

        // Paste-in script parsing (ALWAYS if non-empty)
        $raw = $input['hl_tracking']['raw_script'] ?? '';

        if (is_string($raw) && trim($raw) !== '') {
            $parsed = $this->parse_tracking_script($raw);
            if ($parsed) {
                $settings['hl_tracking']['src'] = $parsed['src'];
                $settings['hl_tracking']['tracking_id'] = $parsed['tracking_id'];
                $settings['hl_tracking']['async'] = $parsed['async'];
                $settings['hl_tracking']['defer'] = $parsed['defer'];
                \fcwpb_log('info', 'HL tracking script parsed', ['src' => $parsed['src']]);
                add_settings_error('fcwpb_settings', 'hl_tracking_parsed', 'Tracking script updated successfully.', 'updated');
            } else {
                \fcwpb_log('error', 'Could not parse HL tracking script. Expecting <script src="..." data-tracking-id="..."></script>.');
                add_settings_error('fcwpb_settings', 'hl_tracking_parse_failed', 'Tracking script could not be parsed. Existing script was kept.', 'error');
            }
        }

        // Event sync config (custom field id map JSON)
        if (!isset($settings['hl_events'])) $settings['hl_events'] = [];
        $settings['hl_events']['custom_field_id_map_json'] =
            sanitize_textarea_field($input['hl_events']['custom_field_id_map_json'] ?? ($settings['hl_events']['custom_field_id_map_json'] ?? ''));

        // Advanced
        $lvl = $input['advanced']['log_level'] ?? $settings['advanced']['log_level'];
        $settings['advanced']['log_level'] = in_array($lvl, ['error','info','debug'], true) ? $lvl : 'error';
        $settings['advanced']['use_queue'] = !empty($input['advanced']['use_queue']);

        return $settings;
    }

    private function parse_tracking_script(string $html): ?array {
        if (!class_exists('DOMDocument')) {
            if (preg_match('/<script[^>]*src=["\']([^"\']+)["\'][^>]*data-tracking-id=["\']([^"\']+)["\'][^>]*>(?:<\/script>)?/i', $html, $m)) {
                return [
                    'src' => esc_url_raw($m[1]),
                    'tracking_id' => sanitize_text_field($m[2]),
                    'async' => stripos($html, ' async') !== false,
                    'defer' => stripos($html, ' defer') !== false,
                ];
            }
            return null;
        }

        libxml_use_internal_errors(true);
        $dom = new \DOMDocument();
        $dom->loadHTML($html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);

        $scripts = $dom->getElementsByTagName('script');
        if ($scripts->length === 0) return null;

        $script = $scripts->item(0);
        if (!$script) return null;

        $src = $script->getAttribute('src');
        $tid = $script->getAttribute('data-tracking-id');
        if (!$src || !$tid) return null;

        return [
            'src' => esc_url_raw($src),
            'tracking_id' => sanitize_text_field($tid),
            'async' => $script->hasAttribute('async'),
            'defer' => $script->hasAttribute('defer'),
        ];
    }

    public function render(): void {
        if (!current_user_can('manage_options')) return;

        $settings = \fcwpb_get_settings();
        $log = get_option('fcwpb_log', []);
        if (!is_array($log)) $log = [];

        $client_id  = esc_attr($settings['connection']['client_id']);
        $version_id = explode('-', $client_id)[0];

        $redirect_uri = urlencode('https://buzzwebmedia.com.au/leadconnector/oauth');

        $scope = urlencode(
            'businesses.readonly businesses.write ' .
            'contacts.readonly contacts.write ' .
            'oauth.readonly oauth.write ' .
            'locations.readonly'
        );

        ?>
        <div class="wrap">
            <h1>FocalContact WP Bridge</h1>

            <?php settings_errors('fcwpb_settings'); ?>

            <style>
                .fcwpb-layout { display:flex; gap:24px; align-items:flex-start; }
                .fcwpb-nav {
                    position: sticky; top: 32px;
                    min-width: 220px; max-width: 260px;
                    background: #fff; border: 1px solid #ccd0d4;
                    padding: 12px; border-radius: 8px;
                }
                .fcwpb-nav strong { display:block; margin-bottom:8px; }
                .fcwpb-nav a { display:block; padding:6px 0; text-decoration:none; }
                .fcwpb-main { flex: 1; min-width: 0; }
                .fcwpb-inline-box { background:#f6f7f7; border:1px solid #ccd0d4; padding:12px; border-radius:8px; }
            </style>

            <div class="fcwpb-layout">
                <div class="fcwpb-nav">
                    <strong>Jump to</strong>
                    <a href="#fcwpb-section-connection">Connection</a>
                    <a href="#fcwpb-section-modules">Modules</a>
                    <a href="#fcwpb-section-utm">UTM Settings</a>
                    <a href="#fcwpb-section-hl">HighLevel External Tracking</a>
                    <a href="#fcwpb-section-advanced">Advanced</a>
                    <a href="#fcwpb-section-diagnostics">Diagnostics</a>
                </div>

                <div class="fcwpb-main">
                    <form method="post" action="options.php">
                        <?php settings_fields('fcwpb_settings_group'); ?>

                        <h2 id="fcwpb-section-connection" class="title">Connection</h2>
                        <table class="form-table" role="presentation">
                            <tr>
                                <th scope="row"><label for="fcwpb_client_id">Client ID</label></th>
                                <td><input id="fcwpb_client_id" type="text" class="regular-text"
                                        name="fcwpb_settings[connection][client_id]"
                                        value="<?php echo esc_attr($settings['connection']['client_id'] ?? ''); ?>"></td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="fcwpb_client_secret">Client Secret</label></th>
                                <td><input id="fcwpb_client_secret" type="password" class="regular-text"
                                        name="fcwpb_settings[connection][client_secret]"
                                        value="<?php echo esc_attr($settings['connection']['client_secret'] ?? ''); ?>"></td>
                            </tr>
                            <tr>
                                <th scope="row">HighLevel OAuth</th>
                                <td>
                                    <a class="button button-primary"
                                    href="https://marketplace.gohighlevel.com/oauth/chooselocation?response_type=code&client_id=<?php echo $client_id; ?>&redirect_uri=<?php echo $redirect_uri; ?>&scope=<?php echo $scope; ?>&version_id=<?php echo $version_id; ?>">Connect to HighLevel</a>
                                </td>
                            </tr>
                        </table>

                        <h2 id="fcwpb-section-modules" class="title">Modules</h2>
                        <table class="form-table" role="presentation">
                            <?php echo $this->module_row('utm', 'UTM & Attribution Persistence', 'Captures UTMs and persists them across pages/sessions, then appends to forms.'); ?>
                            <?php echo $this->module_row('hl_push', 'Push Forms & WooCommerce to FocalContact', 'Sends submissions and orders to the connected sub-account.'); ?>
                            <?php echo $this->module_row('hl_external_tracking', 'HighLevel External Tracking Script', 'Loads the external tracking script (structured storage + safe output).'); ?>
                            <?php echo $this->module_row('abandoned_cart', 'Woo Abandoned Cart', 'Captures cart/checkout identity and triggers abandon events (optional).'); ?>
                            <?php echo $this->module_row('webhooks', 'Webhooks (Two-way sync)', 'Receives webhooks from FocalContact to update WP (optional).'); ?>
                        </table>

                        <h2 id="fcwpb-section-utm" class="title">UTM Settings</h2>
                        <table class="form-table" role="presentation">
                            <tr>
                                <th scope="row">UTM Keys</th>
                                <td>
                                    <input type="text" class="large-text"
                                        name="fcwpb_settings[utm][param_keys]"
                                        value="<?php echo esc_attr($settings['utm']['param_keys']); ?>">
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">Storage</th>
                                <td>
                                    <select name="fcwpb_settings[utm][storage]">
                                        <option value="cookie" <?php selected($settings['utm']['storage'], 'cookie'); ?>>Cookie</option>
                                        <option value="localStorage" <?php selected($settings['utm']['storage'], 'localStorage'); ?>>localStorage</option>
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">Cookie Days</th>
                                <td>
                                    <input type="number" min="1" max="365" name="fcwpb_settings[utm][cookie_days]" value="<?php echo esc_attr(intval($settings['utm']['cookie_days'])); ?>">
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">Append to forms</th>
                                <td>
                                    <label>
                                        <input type="checkbox" name="fcwpb_settings[utm][append_to_forms]" value="1" <?php checked(!empty($settings['utm']['append_to_forms'])); ?>>
                                        Add hidden fields to forms before submit
                                    </label>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">Field prefix</th>
                                <td>
                                    <input type="text" class="regular-text" name="fcwpb_settings[utm][form_field_prefix]" value="<?php echo esc_attr($settings['utm']['form_field_prefix']); ?>">
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">Load everywhere</th>
                                <td>
                                    <label>
                                        <input type="checkbox" name="fcwpb_settings[utm][load_everywhere]" value="1" <?php checked(!empty($settings['utm']['load_everywhere'])); ?>>
                                        Load the UTM script on all public pages
                                    </label>
                                </td>
                            </tr>
                        </table>

                        <h2 id="fcwpb-section-hl" class="title">HighLevel External Tracking</h2>

                        <p class="description">
                            HighLevel’s external tracking script records page views and tracks embedded HighLevel / LeadConnector form submissions.
                            Use the event bridge to send your own events when needed.
                        </p>

                        <table class="form-table" role="presentation">
                            <tr>
                                <th scope="row">Enable injection</th>
                                <td>
                                    <label>
                                        <input type="checkbox" name="fcwpb_settings[hl_tracking][enabled]" value="1" <?php checked(!empty($settings['hl_tracking']['enabled'])); ?>>
                                        Output the tracking script on the site
                                    </label>
                                    <p class="description">Also requires the module toggle "HighLevel External Tracking Script" to be enabled.</p>
                                </td>
                            </tr>

                            <tr>
                                <th scope="row">Tracking script</th>
                                <td>
                                    <?php if (!empty($settings['hl_tracking']['src'])): ?>
                                    <p><strong>Saved values:</strong></p>
                                    <p><code>src</code>: <?php echo esc_html($settings['hl_tracking']['src']); ?></p>
                                    <p><code>data-tracking-id</code>: <?php echo esc_html($settings['hl_tracking']['tracking_id']); ?></p>
                                    <hr>
                                    <?php endif; ?>

                                    <textarea class="large-text code" rows="4"
                                    name="fcwpb_settings[hl_tracking][raw_script]"
                                    placeholder="Add your new external tracking script here if you need to update your script"></textarea>

                                    <p class="description">
                                    On save, the script URL and tracking ID are extracted and stored safely.
                                    </p>
                                </td>
                            </tr>

                            <tr>
                                <th scope="row">Public-only</th>
                                <td>
                                    <label>
                                        <input type="checkbox" name="fcwpb_settings[hl_tracking][public_only]" value="1" <?php checked(!empty($settings['hl_tracking']['public_only'])); ?>>
                                        Do not load for logged-in users
                                    </label>
                                </td>
                            </tr>

                            <tr>
                                <th scope="row">Load everywhere</th>
                                <td>
                                    <label>
                                        <input type="checkbox" name="fcwpb_settings[hl_tracking][load_everywhere]" value="1" <?php checked(!empty($settings['hl_tracking']['load_everywhere'])); ?>>
                                        Load on all public pages
                                    </label>
                                    <p class="description">If turned off, use include/exclude path rules below.</p>
                                </td>
                            </tr>

                            <tr>
                                <th scope="row">Include paths</th>
                                <td>
                                    <textarea class="large-text code" rows="3" name="fcwpb_settings[hl_tracking][include_paths]" placeholder="/
/contact
/pricing"><?php echo esc_textarea($settings['hl_tracking']['include_paths']); ?></textarea>
                                    <p class="description">One path per line (or comma-separated). Matches by prefix.</p>
                                </td>
                            </tr>

                            <tr>
                                <th scope="row">Exclude paths</th>
                                <td>
                                    <textarea class="large-text code" rows="3" name="fcwpb_settings[hl_tracking][exclude_paths]" placeholder="/wp-json
/wp-admin"><?php echo esc_textarea($settings['hl_tracking']['exclude_paths']); ?></textarea>
                                    <p class="description">Exclude overrides include.</p>
                                </td>
                            </tr>
                        </table>

                        <h3>Event Sync (Contacts)</h3>
                        <p class="description">
                            Events are attached to contacts via HighLevel’s contact upsert.
                            To write per-event fields, provide a JSON map of <code>field_key</code> → <code>customFieldId</code>.
                        </p>

                        <table class="form-table" role="presentation">
                            <tr>
                                <th scope="row">Custom field ID map (JSON)</th>
                                <td>
                                    <textarea
                                        class="large-text code"
                                        rows="6"
                                        name="fcwpb_settings[hl_events][custom_field_id_map_json]"
                                        placeholder='{
  "event_add_to_cart": "CUSTOM_FIELD_ID",
  "event_add_to_cart_at": "CUSTOM_FIELD_ID",
  "event_add_to_cart_data": "CUSTOM_FIELD_ID"
}'><?php echo esc_textarea($settings['hl_events']['custom_field_id_map_json'] ?? ''); ?></textarea>
                                    <p class="description">
                                        If empty/invalid, events will still be applied as contact tags (and logged), but custom fields won’t be written.
                                    </p>
                                </td>
                            </tr>
                        </table>

                        <h2 id="fcwpb-section-advanced" class="title">Advanced</h2>
                        <table class="form-table" role="presentation">
                            <tr>
                                <th scope="row">Queue</th>
                                <td>
                                    <label>
                                        <input type="checkbox" name="fcwpb_settings[advanced][use_queue]" value="1" <?php checked(!empty($settings['advanced']['use_queue'])); ?>>
                                        Use background queue for API calls
                                    </label>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">Log level</th>
                                <td>
                                    <select name="fcwpb_settings[advanced][log_level]">
                                        <option value="error" <?php selected($settings['advanced']['log_level'], 'error'); ?>>Errors</option>
                                        <option value="info" <?php selected($settings['advanced']['log_level'], 'info'); ?>>Info</option>
                                        <option value="debug" <?php selected($settings['advanced']['log_level'], 'debug'); ?>>Debug</option>
                                    </select>
                                </td>
                            </tr>
                        </table>

                        <?php submit_button(); ?>
                    </form>

                    <hr>

                    <h2 id="fcwpb-section-diagnostics">Diagnostics</h2>
                    <p><strong>Site fingerprint:</strong> <code><?php echo esc_html(\fcwpb_get_site_fingerprint()); ?></code></p>

                    <h3>Recent log</h3>
                    <div style="background:#fff;border:1px solid #ccd0d4;padding:12px;max-height:280px;overflow:auto;">
                        <pre style="margin:0;white-space:pre-wrap;"><?php echo esc_html(implode("\n", array_slice($log, -80))); ?></pre>
                    </div>

                </div>
            </div>
        </div>
        <?php
    }

    private function module_row(string $key, string $label, string $desc): string {
        $settings = \fcwpb_get_settings();
        $checked = !empty($settings['modules'][$key]) ? 'checked' : '';
        $html = '<tr><th scope="row">' . esc_html($label) . '</th><td>';
        $html .= '<label><input type="checkbox" name="fcwpb_settings[modules][' . esc_attr($key) . ']" value="1" ' . $checked . '> Enabled</label>';
        $html .= '<p class="description">' . esc_html($desc) . '</p>';
        $html .= '</td></tr>';
        return $html;
    }
}
