<?php

declare(strict_types=1);

namespace SGJobs\Ui\Board;

class BoardController
{
    public function register(): void
    {
        add_shortcode('sg_jobs_board', [$this, 'renderBoard']);
        add_action('wp_enqueue_scripts', [$this, 'registerBoardAssets']);
    }

    public function registerBoardAssets(): void
    {
        $mainFile = defined('SGJOBS_MAIN_FILE') ? SGJOBS_MAIN_FILE : (defined('SGJOBS_PLUGIN_FILE') ? SGJOBS_PLUGIN_FILE : __FILE__);
        $version = defined('SGJOBS_VERSION') ? SGJOBS_VERSION : 'dev';

        $baseUrl = plugin_dir_url($mainFile) . 'dist/';
        $basePath = plugin_dir_path($mainFile) . 'dist/';

        wp_register_script(
            'sgjobs-board',
            $baseUrl . 'board.js',
            [],
            $version,
            true
        );

        $stylesheetPath = $basePath . 'board/board.css';
        if (file_exists($stylesheetPath)) {
            wp_register_style(
                'sgjobs-board',
                $baseUrl . 'board/board.css',
                [],
                $version
            );
        }
    }

    public function renderBoard(): string
    {
        $teams = get_option('sg_jobs_teams', []);
        if (is_string($teams)) {
            $decoded = json_decode($teams, true);
            $teams = is_array($decoded) ? $decoded : [];
        }

        if (! is_array($teams)) {
            $teams = [];
        }

        foreach ($teams as &$team) {
            if (! is_array($team)) {
                $team = [];
            }

            if (! isset($team['execution']) && isset($team['calendar'])) {
                $team['execution'] = $team['calendar'];
            }

            if (! isset($team['principal']) && isset($team['caldav_principal'])) {
                $team['principal'] = $team['caldav_principal'];
            }

            foreach (['execution', 'blocker'] as $key) {
                if (! empty($team[$key]) && is_string($team[$key])) {
                    $team[$key] = rtrim($team[$key], '/') . '/';
                }
            }

            if (! empty($team['principal']) && is_string($team['principal'])) {
                $team['principal'] = rtrim($team['principal'], '/') . '/';
            }

            foreach (['execution', 'principal', 'blocker'] as $key) {
                if (! array_key_exists($key, $team)) {
                    $team[$key] = null;
                }
            }
        }
        unset($team);

        wp_enqueue_script('sgjobs-board');
        if (wp_style_is('sgjobs-board', 'registered')) {
            wp_enqueue_style('sgjobs-board');
        }

        wp_localize_script('sgjobs-board', 'SGJOBS_BOARD', [
            'teams' => $teams,
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'baseUrl' => home_url('/'),
            'version' => defined('SGJOBS_VERSION') ? SGJOBS_VERSION : 'dev',
        ]);

        return '<div id="sgjobs-board-root"></div>';
    }
}
