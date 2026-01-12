<?php
namespace FCWPB\Core\Modules;

if (!defined('ABSPATH')) { exit; }

interface ModuleInterface {
    public function key(): string;
    public function label(): string;
    public function description(): string;
    public function is_enabled(): bool;
    public function init(): void;
}
