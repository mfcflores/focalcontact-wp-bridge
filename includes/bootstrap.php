<?php
if (!defined('ABSPATH')) { exit; }

require_once FCWPB_PATH . 'includes/helpers.php';

// Simple PSR-4-ish autoloader for this plugin only.
spl_autoload_register(function ($class) {
    if (strpos($class, 'FCWPB\\') !== 0) return;
    $relative = str_replace('FCWPB\\', '', $class);
    $relative = str_replace('\\', DIRECTORY_SEPARATOR, $relative);
    $file = FCWPB_PATH . 'includes/' . $relative . '.php';
    if (file_exists($file)) require_once $file;
});
