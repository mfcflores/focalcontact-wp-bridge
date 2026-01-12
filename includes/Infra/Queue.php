<?php
namespace FCWPB\Infra;

if (!defined('ABSPATH')) { exit; }

final class Queue {
    const HOOK = 'fcwpb_process_job';

    public static function init(): void {
        add_action(self::HOOK, [__CLASS__, 'process_job'], 10, 2);
    }

    public static function enqueue(string $job_type, array $payload): void {
        $settings = \fcwpb_get_settings();
        $use_queue = !empty($settings['advanced']['use_queue']);

        if ($use_queue && function_exists('as_enqueue_async_action')) {
            as_enqueue_async_action(self::HOOK, [$job_type, $payload], 'fcwpb');
            return;
        }

        $ts = time() + 5;
        wp_schedule_single_event($ts, self::HOOK, [$job_type, $payload]);
    }

    public static function process_job(string $job_type, array $payload): void {
        try {
            switch ($job_type) {
                case 'hl_post':
                    $endpoint = $payload['endpoint'] ?? '';
                    $data     = $payload['data'] ?? [];
                    $result   = HLClient::post($endpoint, $data);
                    \fcwpb_log('info', 'HL POST completed', ['endpoint' => $endpoint, 'ok' => $result['ok'] ?? null]);
                    break;
                case 'hl_event':
                    $event = $payload['event'];
                    $ts    = gmdate('c', $payload['ts'] ?? time());
                    $data  = $payload['data'] ?? [];

                    $fields = [
                        "event_{$event}" => true,
                        "event_{$event}_at" => $ts,
                        "event_{$event}_data" => wp_json_encode($data),
                    ];

                    HLClient::post('/contacts/', [
                        'customField' => $fields
                    ]);

                    \fcwpb_log('info', 'HL event synced', ['event' => $event]);
                    break;
                default:
                    \fcwpb_log('error', 'Unknown job type', ['job_type' => $job_type]);
            }
        } catch (\Throwable $e) {
            \fcwpb_log('error', 'Queue job failed: ' . $e->getMessage(), ['job_type' => $job_type]);
        }
    }
}
