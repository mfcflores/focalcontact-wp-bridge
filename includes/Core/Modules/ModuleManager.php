<?php
namespace FCWPB\Core\Modules;

if (!defined('ABSPATH')) { exit; }

use FCWPB\Modules\UTM\UTMModule;
use FCWPB\Modules\HLPush\HLPushModule;
use FCWPB\Modules\HLExternalTracking\HLExternalTrackingModule;
use FCWPB\Modules\AbandonedCart\AbandonedCartModule;
use FCWPB\Modules\Webhooks\WebhooksModule;

final class ModuleManager {
    /** @var ModuleInterface[] */
    private $modules = [];

    public function register(ModuleInterface $module): void {
        $this->modules[$module->key()] = $module;
    }

    public function get(string $key): ?ModuleInterface {
        return $this->modules[$key] ?? null;
    }

    public function all(): array {
        return $this->modules;
    }

    public function register_defaults(): void {
        $this->register(new UTMModule());
        $this->register(new HLPushModule());
        $this->register(new HLExternalTrackingModule());
        $this->register(new AbandonedCartModule());
        $this->register(new WebhooksModule());
    }

    public function init_enabled_modules(): void {
        foreach ($this->modules as $module) {
            if ($module->is_enabled()) {
                $module->init();
            }
        }
    }
}
