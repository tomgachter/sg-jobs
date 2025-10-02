<?php

declare(strict_types=1);

namespace SGJobs\Ui\Board;

class BoardController
{
    public function register(): void
    {
        add_shortcode('sg_jobs_board', [$this, 'render']);
    }

    public function render(): string
    {
        if (! current_user_can('sgjobs_manage')) {
            return esc_html__('You do not have permission to view the board.', 'sg-jobs');
        }
        wp_enqueue_script('sg-jobs-board', plugins_url('dist/board.js', SGJOBS_PLUGIN_FILE), [], '0.1.0', true);
        wp_enqueue_style('sg-jobs-board', plugins_url('dist/board.css', SGJOBS_PLUGIN_FILE), [], '0.1.0');

        return '<div id="sg-jobs-board"></div>';
    }
}
