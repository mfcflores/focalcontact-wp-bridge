<?php
/**
 * Plugin Name: FocalContact WP Bridge
 * Description: A modular WordPress plugin that connects WordPress (forms + WooCommerce + tracking) to FocalContact (HighLevel) sub-accounts.
 * Version: 0.2.0
 * Author: Buzz Web Media
 * License: GPLv2 or later
 * Text Domain: fcwpb
 */

if (!defined('ABSPATH')) { exit; }

define('FCWPB_VERSION', '1.0.0');
define('FCWPB_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('FCWPB_PATH', plugin_dir_path(__FILE__));
define('FCWPB_URL', plugin_dir_url(__FILE__));
define('FCWPB_FILE', __FILE__);

require_once FCWPB_PATH . 'includes/bootstrap.php';

add_action('plugins_loaded', function () {
    \FCWPB\Core\Plugin::instance()->init();
});
