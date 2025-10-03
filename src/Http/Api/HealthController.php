<?php

declare(strict_types=1);

namespace SGJobs\Http\Api;

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

    /**
     * @param WP_REST_Request<array<string, mixed>> $request
     */
    public function check(WP_REST_Request $request): WP_REST_Response
    {
        $errors = [];
        $ok = true;
        $caldavStatus = [];

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
     * @return list<array{name:string, principal:string, execution:string, blocker:string}>
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
}
