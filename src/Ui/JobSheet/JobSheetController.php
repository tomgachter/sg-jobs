<?php

declare(strict_types=1);

namespace SGJobs\Ui\JobSheet;

class JobSheetController
{
    public function register(): void
    {
        add_shortcode('sg_jobs_mine', [$this, 'renderList']);
        add_action('init', [$this, 'registerRewrites']);
        add_filter('query_vars', static function (array $vars): array {
            $vars[] = 'sgjobs_job_token';

            return $vars;
        });
        add_action('template_redirect', [$this, 'renderJob']);
    }

    public function registerRewrites(): void
    {
        add_rewrite_tag('%sgjobs_job_token%', '([^&]+)');
        add_rewrite_rule('jobs/([^/]+)/?$', 'index.php?sgjobs_job_token=$matches[1]', 'top');
    }

    public function renderList(): string
    {
        wp_enqueue_script('sg-jobs-sheet', plugins_url('dist/jobsheet.js', SGJOBS_PLUGIN_FILE), [], '0.1.0', true);
        wp_enqueue_style('sg-jobs-sheet', plugins_url('dist/jobsheet.css', SGJOBS_PLUGIN_FILE), [], '0.1.0');
        return '<div id="sg-jobs-sheet"></div>';
    }

    public function renderJob(): void
    {
        $token = get_query_var('sgjobs_job_token');
        if (! $token) {
            return;
        }
        status_header(200);
        nocache_headers();
        readfile(plugin_dir_path(SGJOBS_PLUGIN_FILE) . 'public/jobs/index.php');
        exit;
    }
}
