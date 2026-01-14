<?php
namespace FCWPB\Core;

if (!defined('ABSPATH')) { exit; }

use FCWPB\Core\Modules\ModuleManager;
use FCWPB\Admin\SettingsPage;
use FCWPB\Infra\Queue;
use FCWPB\Infra\Rest;
use FCWPB\Infra\Migration;
use FCWPB\Infra\OAuth;

final class Plugin {
    private static $instance = null;

    /** @var ModuleManager */
    public $modules;

    public static function instance(): Plugin {
        if (self::$instance === null) self::$instance = new Plugin();
        return self::$instance;
    }

    public function init(): void {
        Migration::maybe_migrate();

        if (is_admin()) {
            (new SettingsPage())->init();
        }

        (new OAuth())->init();

        Queue::init();
        Rest::init();

        $this->modules = new ModuleManager();
        $this->modules->register_defaults();
        $this->modules->init_enabled_modules();
    }
}
