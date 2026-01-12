<?php
namespace FCWPB\Infra;

if (!defined('ABSPATH')) { exit; }

final class Migration {
    public static function maybe_migrate(): void {
        $done = get_option('fcwpb_migration_done', false);
        if ($done) return;
        update_option('fcwpb_migration_done', true);
    }
}
