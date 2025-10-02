<?php

declare(strict_types=1);

namespace SGJobs\Http\Api;

use SGJobs\App\JobsService;
use SGJobs\Http\Middleware\Auth;
use WP_REST_Request;
use WP_REST_Server;

class MagicLinkController
{
    private JobsService $jobs;

    private Auth $auth;

    public function __construct()
    {
        $this->jobs = new JobsService();
        $this->auth = new Auth();
    }

    public function registerRoutes(): void
    {
        add_action('rest_api_init', function (): void {
            register_rest_route('sgjobs/v1', '/my-jobs', [
                'methods' => WP_REST_Server::READABLE,
                'permission_callback' => [$this->auth, 'requireInstaller'],
                'callback' => [$this, 'listJobs'],
            ]);

            register_rest_route('sgjobs/v1', '/jobs/by-token/(?P<token>[^/]+)', [
                'methods' => WP_REST_Server::READABLE,
                'permission_callback' => '__return_true',
                'callback' => [$this, 'jobByToken'],
            ]);
        });
    }

    public function listJobs(WP_REST_Request $request)
    {
        // Simplified response placeholder
        return [
            'jobs' => [],
        ];
    }

    public function jobByToken(WP_REST_Request $request)
    {
        $token = $request['token'];
        $claims = $this->auth->validateInstallerToken($token);
        if ($claims instanceof \WP_Error) {
            return $this->auth->toRestError($claims);
        }
        $jobId = (int) ($claims['job_id'] ?? 0);
        if (! $jobId) {
            return $this->auth->toRestError(new \WP_Error('sg_jobs_invalid_token', __('Token enthält keine Job-ID.', 'sg-jobs')));
        }
        $job = $this->jobs->getJobById($jobId);
        if ($job instanceof \WP_Error) {
            return $this->auth->toRestError($job);
        }
        return $job;
    }
}
