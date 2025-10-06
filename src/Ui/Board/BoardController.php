<?php

declare(strict_types=1);

namespace SGJobs\Ui\Board;

use function sgj_enqueue_style_prefix;
use function sgj_get_normalized_teams;

class BoardController
{
    public function register(): void
    {
        add_shortcode('sg_jobs_board', [$this, 'renderBoard']);
    }

    public function renderBoard(): string
    {
        $teams = sgj_get_normalized_teams();

        wp_enqueue_script('sgjobs-board');
        sgj_enqueue_style_prefix('sgjobs-board-css-');

        wp_localize_script('sgjobs-board', 'SGJOBS_BOARD', [
            'teams' => $teams,
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'baseUrl' => home_url('/'),
            'version' => defined('SGJOBS_VERSION') ? SGJOBS_VERSION : 'dev',
        ]);

        return '<div id="sg-jobs-board"></div>';
    }
}
