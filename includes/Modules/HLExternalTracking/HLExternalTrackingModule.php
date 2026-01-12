<?php
namespace FCWPB\Modules\HLExternalTracking;

if (!defined('ABSPATH')) { exit; }

use FCWPB\Core\Modules\ModuleInterface;

final class HLExternalTrackingModule implements ModuleInterface {

    public function key(): string { return 'hl_external_tracking'; }
    public function label(): string { return 'HighLevel External Tracking Script'; }
    public function description(): string { return 'Loads the external tracking script and provides an event bridge.'; }
    public function is_enabled(): bool { return \fcwpb_is_module_enabled($this->key()); }

    public function init(): void {
        add_action('wp_footer', [$this, 'render_script'], 20);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_event_bridge'], 20);
    }

    private function is_frontend_request(): bool {
        // Block wp-admin (but don't confuse "logged-in frontend" with wp-admin)
        if (is_admin() && !wp_doing_ajax()) return false;

        // Block REST / AJAX / feeds / embeds / login
        if (defined('REST_REQUEST') && REST_REQUEST) return false;
        if (wp_doing_ajax()) return false;
        if (function_exists('is_feed') && is_feed()) return false;
        if (function_exists('is_embed') && is_embed()) return false;

        $pagenow = $GLOBALS['pagenow'] ?? '';
        if ($pagenow === 'wp-login.php' || $pagenow === 'wp-register.php') return false;

        return true;
    }

    private function should_load(): bool {
        if (!$this->is_frontend_request()) return false;

        $settings = \fcwpb_get_settings();
        $t = $settings['hl_tracking'] ?? [];

        if (empty($t['enabled'])) return false;
        if (empty($t['src']) || empty($t['tracking_id'])) return false;

        // Public-only logic (THIS PART IS CORRECT)
        if (!empty($t['public_only']) && is_user_logged_in()) return false;

        $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

        $exclude = \fcwpb_sanitize_path_list((string)($t['exclude_paths'] ?? ''));

        foreach ($exclude as $p) {
            if ($p === '/' || strpos($path, $p) === 0) return false;
        }

        if (empty($t['load_everywhere'])) {
            $include = \fcwpb_sanitize_path_list((string)($t['include_paths'] ?? ''));
            
            if (!$include) return false;

            foreach ($include as $p) {
                if ($p === '/' || strpos($path, $p) === 0) {
                    print_r($include); echo 'yes';
                    return true;
                }                
            }
            return false;
        }

        return true;
    }

    public function enqueue_event_bridge(): void {
        if (!$this->should_load()) return;

        wp_register_script(
            'fcwpb-hl-event-bridge',
            FCWPB_URL . 'assets/js/hl-event-bridge.js',
            [],
            FCWPB_VERSION,
            true
        );

        wp_localize_script('fcwpb-hl-event-bridge', 'FCWPB_HL', [
            'restEventUrl' => esc_url_raw(rest_url('fcwpb/v1/event')),
        ]);

        wp_enqueue_script('fcwpb-hl-event-bridge');
    }

    public function render_script(): void {
        if (!$this->should_load()) return;

        $settings = \fcwpb_get_settings();
        $t = $settings['hl_tracking'] ?? [];
        ?>
        <!-- FCWPB: HighLevel External Tracking Script -->
        <script
            data-debug="true"
            src="<?php echo esc_url($t['src']); ?>"
            data-tracking-id="<?php echo esc_attr($t['tracking_id']); ?>"
            <?php echo !empty($t['async']) ? 'async' : ''; ?>
            <?php echo !empty($t['defer']) ? 'defer' : ''; ?>>
        </script>
        <?php
    }
}
