<?php
/**
 * Plugin Name: SG Jobs
 * Description: Integrates bexio delivery notes with a scheduling board, CalDAV sync and job sheets for installers.
 * Requires at least: 6.5
 * Requires PHP: 8.2
 * Version: 0.1.0
 * Author: SG Operations
 * Text Domain: sg-jobs
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

$autoload = __DIR__ . '/vendor/autoload.php';

if (! file_exists($autoload)) {
    $notice = static function (): void {
        echo '<div class="notice notice-error"><p>';
        echo esc_html__(
            'SG Jobs requires Composer dependencies. Run "composer install --no-dev" before activating the plugin, '
            . 'or upload a release package that already contains the vendor directory.',
            'sg-jobs'
        );
        echo '</p></div>';
    };

    if (function_exists('add_action')) {
        add_action('admin_notices', $notice);
        add_action('network_admin_notices', $notice);
    }

    return;
}

require_once $autoload;

if (! defined('SGJOBS_MAIN_FILE')) {
    define('SGJOBS_MAIN_FILE', __FILE__);
}

if (! defined('SGJOBS_VERSION')) {
    define('SGJOBS_VERSION', '0.1.0');
}

if (! defined('SGJOBS_PLUGIN_FILE')) {
    define('SGJOBS_PLUGIN_FILE', SGJOBS_MAIN_FILE);
}

if (! function_exists('sgj_normalize_team_path')) {
    function sgj_normalize_team_path(?string $value): string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return '';
        }

        return rtrim($value, '/') . '/';
    }
}

if (! function_exists('sgj_normalize_teams_data')) {
    function sgj_normalize_teams_data(mixed $teams): array
    {
        if (is_string($teams)) {
            $decoded = json_decode($teams, true);
            $teams = is_array($decoded) ? $decoded : [];
        }

        if (! is_array($teams)) {
            return [];
        }

        $normalized = [];
        foreach ($teams as $team) {
            if (! is_array($team)) {
                $team = [];
            }

            $name = sanitize_text_field((string) ($team['name'] ?? ''));
            $principal = sanitize_text_field((string) ($team['principal'] ?? ($team['caldav_principal'] ?? '')));
            $execution = sanitize_text_field((string) ($team['execution'] ?? ($team['execution_path'] ?? ($team['calendar'] ?? ($team['exec'] ?? '')))));
            $blocker = sanitize_text_field((string) ($team['blocker'] ?? ($team['blocker_path'] ?? '')));

            $principal = sgj_normalize_team_path($principal);
            $execution = sgj_normalize_team_path($execution);
            $blocker = sgj_normalize_team_path($blocker);

            if ($name === '' && $principal === '' && $execution === '' && $blocker === '') {
                continue;
            }

            $normalized[] = [
                'name' => $name,
                'principal' => $principal,
                'execution' => $execution,
                'blocker' => $blocker,
            ];
        }

        return array_values($normalized);
    }
}

if (! function_exists('sgj_get_normalized_teams')) {
    function sgj_get_normalized_teams(): array
    {
        $raw = get_option('sg_jobs_teams', []);
        $normalized = sgj_normalize_teams_data($raw);

        $rawComparable = is_array($raw) ? array_values($raw) : [];
        if (serialize($normalized) !== serialize($rawComparable)) {
            update_option('sg_jobs_teams', $normalized, false);
        }

        return $normalized;
    }
}

if (! function_exists('sgj_resolve_asset')) {
    /**
     * @return array{js:string,css:array<int,string>}
     */
    function sgj_resolve_asset(string $entry): array
    {
        $basePath = plugin_dir_path(SGJOBS_MAIN_FILE) . 'dist/';
        $baseUrl = plugin_dir_url(SGJOBS_MAIN_FILE) . 'dist/';
        $manifestFile = $basePath . 'manifest.json';

        static $manifest = null;
        if ($manifest === null) {
            $manifest = [];
            if (file_exists($manifestFile)) {
                $json = json_decode((string) file_get_contents($manifestFile), true);
                if (is_array($json)) {
                    $manifest = $json;
                }
            }
        }

        if (isset($manifest[$entry]) && is_array($manifest[$entry])) {
            $info = $manifest[$entry];
            $css = [];
            if (! empty($info['css']) && is_array($info['css'])) {
                foreach ($info['css'] as $style) {
                    $css[] = $baseUrl . ltrim((string) $style, '/');
                }
            }

            return [
                'js' => $baseUrl . ltrim((string) $info['file'], '/'),
                'css' => $css,
            ];
        }

        $asset = [
            'js' => $baseUrl . $entry,
            'css' => [],
        ];

        $entryBase = preg_replace('/\.js$/', '', $entry);
        $directCss = $basePath . $entryBase . '.css';
        if ($entryBase && file_exists($directCss)) {
            $asset['css'][] = $baseUrl . $entryBase . '.css';
        }

        $nestedCssPath = $basePath . $entryBase . '/' . $entryBase . '.css';
        if ($entryBase && file_exists($nestedCssPath)) {
            $asset['css'][] = $baseUrl . $entryBase . '/' . $entryBase . '.css';
        }

        return $asset;
    }
}

if (! function_exists('sgj_register_front_assets')) {
    function sgj_register_front_assets(): void
    {
        $board = sgj_resolve_asset('board.js');
        if (! wp_script_is('sgjobs-board', 'registered')) {
            wp_register_script('sgjobs-board', $board['js'], [], SGJOBS_VERSION, true);
        }
        foreach ($board['css'] as $index => $href) {
            $handle = 'sgjobs-board-css-' . $index;
            if (! wp_style_is($handle, 'registered')) {
                wp_register_style($handle, $href, [], SGJOBS_VERSION);
            }
        }

        $sheet = sgj_resolve_asset('jobsheet.js');
        if (! wp_script_is('sgjobs-jobsheet', 'registered')) {
            wp_register_script('sgjobs-jobsheet', $sheet['js'], [], SGJOBS_VERSION, true);
        }
        foreach ($sheet['css'] as $index => $href) {
            $handle = 'sgjobs-jobsheet-css-' . $index;
            if (! wp_style_is($handle, 'registered')) {
                wp_register_style($handle, $href, [], SGJOBS_VERSION);
            }
        }
    }

    add_action('wp_enqueue_scripts', 'sgj_register_front_assets');
}

if (! function_exists('sgj_enqueue_style_prefix')) {
    function sgj_enqueue_style_prefix(string $prefix): void
    {
        $styles = wp_styles();
        if (! $styles) {
            return;
        }

        foreach ($styles->registered as $handle => $style) {
            if (str_starts_with($handle, $prefix)) {
                wp_enqueue_style($handle);
            }
        }
    }
}

if (! function_exists('sgj_mark_module_scripts')) {
    function sgj_mark_module_scripts(string $tag, string $handle, string $src): string
    {
        if (in_array($handle, ['sgjobs-board', 'sgjobs-jobsheet'], true) && ! str_contains($tag, 'type="module"')) {
            $tag = str_replace('<script ', '<script type="module" ', $tag);
        }

        return $tag;
    }

    add_filter('script_loader_tag', 'sgj_mark_module_scripts', 10, 3);
}

add_action('init', static function (): void {
    add_shortcode('sg_jobs_ping', static fn (): string => '<div style="padding:8px;background:#e8f5e9;border:1px solid #2e7d32">SG-Jobs Shortcode OK</div>');

    add_shortcode('sg_jobs_health', static function (): string {
        $status = [
            'jwt' => trim((string) get_option('sg_jobs_jwt_secret', '')) !== '',
            'teams' => count(sgj_get_normalized_teams()) > 0,
        ];

        return '<pre>' . esc_html(print_r($status, true)) . '</pre>';
    });
});

use SGJobs\Bootstrap;

$bootstrap = Bootstrap::instance();
$bootstrap->init();
