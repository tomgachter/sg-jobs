<?php

declare(strict_types=1);

namespace SGJobs\Http\Api;

use SGJobs\Infra\Bexio\BexioClient;
use SGJobs\Infra\Bexio\BexioService;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

class HealthController
{
    public function registerRoutes(): void
    {
        add_action('rest_api_init', function (): void {
            register_rest_route('sgjobs/v1', '/health', [
                'methods' => WP_REST_Server::READABLE,
                'permission_callback' => [$this, 'canCheckHealth'],
                'callback' => [$this, 'check'],
            ]);
        });
    }

    public function canCheckHealth(): bool
    {
        return current_user_can('manage_options');
    }

    public function check(WP_REST_Request $request): WP_REST_Response
    {
        $errors = [];
        $ok = true;
        $caldavStatus = [];

        $bexioStatus = $this->checkBexioConfiguration();
        if (! $bexioStatus['configured']) {
            $ok = false;
            $errors[] = __('Bexio Zugangsdaten sind unvollständig.', 'sg-jobs');
        } elseif ($bexioStatus['error'] !== null) {
            $ok = false;
            $errors[] = $bexioStatus['error'];
        }

        $queueStatus = $this->inspectQueue();
        if ($queueStatus['error'] !== null) {
            $ok = false;
            $errors[] = $queueStatus['error'];
        }

        $cronStatus = $this->inspectPaymentSyncCron();
        if (! $cronStatus['scheduled']) {
            $ok = false;
            $errors[] = __('Cron-Event für Zahlungsabgleich ist nicht geplant.', 'sg-jobs');
        } elseif ($cronStatus['overdue']) {
            $ok = false;
            $errors[] = __('Cron-Event für Zahlungsabgleich ist überfällig.', 'sg-jobs');
        }

        $secret = $this->getConfiguredSecret();
        if ($secret === '') {
            $ok = false;
            $errors[] = __('JWT secret ist nicht gesetzt.', 'sg-jobs');
        }

        $caldav = get_option('sg_jobs_caldav', []);
        $baseUrl = is_array($caldav) ? (string) ($caldav['base_url'] ?? '') : '';
        $username = is_array($caldav) ? (string) ($caldav['username'] ?? '') : '';
        $password = is_array($caldav) ? (string) ($caldav['password'] ?? '') : '';

        if ($baseUrl === '' || $username === '' || $password === '') {
            $ok = false;
            $errors[] = __('CalDAV Zugangsdaten sind unvollständig.', 'sg-jobs');
        }

        $teams = $this->loadTeams();
        if ($teams === []) {
            $ok = false;
            $errors[] = __('Keine Teams konfiguriert.', 'sg-jobs');
        }

        if ($baseUrl !== '' && $username !== '' && $password !== '' && $teams !== []) {
            $authHeader = 'Basic ' . base64_encode($username . ':' . $password);
            foreach ($teams as $team) {
                $teamName = $team['name'] !== '' ? $team['name'] : $team['principal'];
                $caldavStatus[$teamName] = [];

                foreach (['execution', 'blocker'] as $type) {
                    $path = $team[$type];
                    if ($path === '') {
                        continue;
                    }

                    $url = $this->resolveCalendarUrl($baseUrl, $path);
                    $response = wp_remote_request($url, [
                        'method' => 'PROPFIND',
                        'headers' => [
                            'Authorization' => $authHeader,
                            'Depth' => '0',
                        ],
                        'body' => '',
                        'timeout' => 5,
                    ]);

                    if ($response instanceof WP_Error) {
                        $ok = false;
                        $message = $response->get_error_message();
                        $caldavStatus[$teamName][$type] = $message;
                        $errors[] = sprintf(
                            /* translators: 1: team name, 2: calendar type, 3: error message */
                            __('CalDAV-Check für %1$s (%2$s) fehlgeschlagen: %3$s', 'sg-jobs'),
                            $teamName,
                            $type,
                            $message
                        );

                        continue;
                    }

                    $statusCode = (int) wp_remote_retrieve_response_code($response);
                    $caldavStatus[$teamName][$type] = $statusCode;
                    if ($statusCode < 200 || $statusCode >= 300) {
                        $ok = false;
                        $errors[] = sprintf(
                            /* translators: 1: team name, 2: calendar type, 3: HTTP status code */
                            __('CalDAV-Check für %1$s (%2$s) lieferte Status %3$d.', 'sg-jobs'),
                            $teamName,
                            $type,
                            $statusCode
                        );
                    }
                }
            }
        }

        return new WP_REST_Response([
            'ok' => $ok,
            'caldav' => $caldavStatus,
            'bexio' => $bexioStatus,
            'queue' => $queueStatus,
            'cron' => $cronStatus,
            'errors' => $errors,
        ]);
    }

    private function getConfiguredSecret(): string
    {
        $secret = get_option('sg_jobs_jwt_secret', '');
        if (is_string($secret) && trim($secret) !== '') {
            return trim($secret);
        }

        $options = get_option('sg_jobs_jwt', []);
        if (is_array($options) && isset($options['secret'])) {
            $value = trim((string) $options['secret']);
            if ($value !== '') {
                return $value;
            }
        }

        $env = getenv('JWT_SECRET');
        if (is_string($env)) {
            $value = trim($env);
            if ($value !== '') {
                return $value;
            }
        }

        return '';
    }

    /**
     * @return array<int, array{name:string, principal:string, execution:string, blocker:string}>
     */
    private function loadTeams(): array
    {
        $teams = get_option('sg_jobs_teams', []);
        if (is_string($teams)) {
            $decoded = json_decode($teams, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $teams = $decoded;
            }
        }

        if (! is_array($teams)) {
            return [];
        }

        $normalized = [];
        foreach ($teams as $team) {
            if (! is_array($team)) {
                continue;
            }

            $normalized[] = [
                'name' => isset($team['name']) ? (string) $team['name'] : '',
                'principal' => isset($team['principal']) ? (string) $team['principal'] : (isset($team['caldav_principal']) ? (string) $team['caldav_principal'] : ''),
                'execution' => isset($team['execution']) ? (string) $team['execution'] : (isset($team['execution_path']) ? (string) $team['execution_path'] : (isset($team['calendar']) ? (string) $team['calendar'] : (isset($team['exec']) ? (string) $team['exec'] : ''))),
                'blocker' => isset($team['blocker']) ? (string) $team['blocker'] : (isset($team['blocker_path']) ? (string) $team['blocker_path'] : ''),
            ];
        }

        return $normalized;
    }

    private function resolveCalendarUrl(string $baseUrl, string $path): string
    {
        if ($path === '') {
            return $baseUrl;
        }

        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        return rtrim($baseUrl, '/') . '/' . ltrim($path, '/');
    }

    /**
     * @return array{configured:bool, ok:bool, error:?string}
     */
    private function checkBexioConfiguration(): array
    {
        $options = get_option('sg_jobs_bexio', []);
        $baseUrl = '';
        $token = '';
        if (is_array($options)) {
            $baseUrl = trim((string) ($options['base_url'] ?? ''));
            $token = trim((string) ($options['token'] ?? ''));
        }

        if ($baseUrl === '') {
            $baseUrl = trim((string) (getenv('BEXIO_BASE_URL') ?: ''));
        }

        if ($token === '') {
            $token = trim((string) (getenv('BEXIO_API_TOKEN') ?: ''));
        }

        $configured = $baseUrl !== '' && $token !== '';

        if (! $configured) {
            return [
                'configured' => false,
                'ok' => false,
                'error' => null,
            ];
        }

        $service = new BexioService(new BexioClient());
        $result = $service->ping();

        if ($result instanceof WP_Error) {
            return [
                'configured' => true,
                'ok' => false,
                'error' => sprintf(
                    /* translators: %s: error message */
                    __('Bexio API nicht erreichbar: %s', 'sg-jobs'),
                    $result->get_error_message()
                ),
            ];
        }

        return [
            'configured' => true,
            'ok' => true,
            'error' => null,
        ];
    }

    /**
     * @return array{supported:bool, ok:bool, error:?string, backlog:array<string,int>}
     */
    private function inspectQueue(): array
    {
        if (! class_exists('ActionScheduler') || ! class_exists('ActionScheduler_Store')) {
            return [
                'supported' => false,
                'error' => __('Action Scheduler ist nicht verfügbar.', 'sg-jobs'),
                'ok' => false,
                'backlog' => [],
            ];
        }

        try {
            $store = \ActionScheduler::store();
        } catch (\Throwable $exception) {
            return [
                'supported' => true,
                'ok' => false,
                'error' => sprintf(
                    /* translators: %s: error message */
                    __('Action Scheduler konnte nicht initialisiert werden: %s', 'sg-jobs'),
                    $exception->getMessage()
                ),
                'backlog' => [],
            ];
        }

        $statuses = [
            'pending' => \ActionScheduler_Store::STATUS_PENDING,
            'in_progress' => \ActionScheduler_Store::STATUS_IN_PROGRESS,
            'failed' => \ActionScheduler_Store::STATUS_FAILED,
        ];

        $backlog = [];

        try {
            foreach ($statuses as $key => $status) {
                $backlog[$key] = (int) $store->query_actions([
                    'group' => 'sg-jobs',
                    'status' => $status,
                ], 'count');
            }
        } catch (\Throwable $exception) {
            return [
                'supported' => true,
                'ok' => false,
                'error' => sprintf(
                    /* translators: %s: error message */
                    __('Action Scheduler Abfrage fehlgeschlagen: %s', 'sg-jobs'),
                    $exception->getMessage()
                ),
                'backlog' => [],
            ];
        }

        $error = null;
        if (($backlog['failed'] ?? 0) > 0) {
            $error = sprintf(
                /* translators: %d: number of failed actions */
                __('%d fehlgeschlagene Queue-Jobs vorhanden.', 'sg-jobs'),
                $backlog['failed']
            );
        }

        return [
            'supported' => true,
            'ok' => $error === null,
            'error' => $error,
            'backlog' => $backlog,
        ];
    }

    /**
     * @return array{scheduled:bool, overdue:bool, next_run:?int, schedule:?string}
     */
    private function inspectPaymentSyncCron(): array
    {
        $event = wp_get_scheduled_event('sg_jobs_bexio_payment_sync');
        if (! $event) {
            return [
                'scheduled' => false,
                'overdue' => false,
                'next_run' => null,
                'schedule' => null,
            ];
        }

        /** @var int $timestamp */
        $timestamp = (int) $event->timestamp;

        $scheduleRaw = $event->schedule ?? null;
        $schedule = is_string($scheduleRaw) ? $scheduleRaw : null;
        $now = time();
        $tolerance = (defined('MINUTE_IN_SECONDS') ? (int) MINUTE_IN_SECONDS : 60) * 5;
        $overdue = $timestamp + $tolerance < $now;

        return [
            'scheduled' => true,
            'overdue' => $overdue,
            'next_run' => $timestamp,
            'schedule' => $schedule,
        ];
    }
}
