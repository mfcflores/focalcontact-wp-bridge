<?php
namespace FCWPB\Infra;

if (!defined('ABSPATH')) { exit; }

final class Queue {

    const HOOK = 'fcwpb_process_job';

    public static function init(): void {
        add_action(self::HOOK, [__CLASS__, 'process_job'], 10, 2);
    }

    public static function enqueue(string $job_type, array $payload): void {
        $ts = time() + 5;
        wp_schedule_single_event($ts, self::HOOK, [$job_type, $payload]);
    }

    public static function process_job(string $job_type, array $payload): void {
        try {
            switch ($job_type) {
                case 'hl_post':
                    $endpoint = $payload['endpoint'] ?? '';
                    $data     = $payload['data'] ?? [];
                    HLClient::post($endpoint, $data);
                    \fcwpb_log('info', 'HL POST completed', compact('endpoint'));
                    break;

                case 'hl_event':
                    $tokens = HLClient::get_tokens();
                    $location = $tokens['locationId'] ?? '';

                    $event  = $payload['event'] ?? '';
                    $ts     = gmdate('c', $payload['ts'] ?? time());
                    $data   = $payload['data'] ?? [];

                    $contactPayload = [
                        'locationId' => $location,
                        'customFields' => [
                            "event_{$event}"      => true,
                            "event_{$event}_at"   => $ts,
                            "event_{$event}_data" => wp_json_encode($data),
                        ],
                    ];
                    HLClient::upsert_contact($contactPayload);
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
