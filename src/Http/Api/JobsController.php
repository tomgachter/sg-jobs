<?php

declare(strict_types=1);

namespace SGJobs\Http\Api;

use SGJobs\App\JobsService;
use SGJobs\Http\Middleware\Auth;
use WP_REST_Request;
use WP_REST_Server;

class JobsController
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
            register_rest_route('sgjobs/v1', '/jobs', [
                'methods' => WP_REST_Server::READABLE,
                'permission_callback' => [$this->auth, 'allowInstallerOrDispo'],
                'callback' => [$this, 'listJobs'],
            ]);

            register_rest_route('sgjobs/v1', '/jobs', [
                'methods' => WP_REST_Server::CREATABLE,
                'permission_callback' => [$this->auth, 'requireDispo'],
                'callback' => [$this, 'createJob'],
            ]);

            register_rest_route('sgjobs/v1', '/jobs/(?P<id>\\d+)', [
                'methods' => WP_REST_Server::READABLE,
                'permission_callback' => [$this->auth, 'allowInstallerOrDispo'],
                'callback' => [$this, 'getJob'],
            ]);

            register_rest_route('sgjobs/v1', '/jobs/(?P<id>\\d+)/done', [
                'methods' => WP_REST_Server::CREATABLE,
                'permission_callback' => [$this->auth, 'requireInstaller'],
                'callback' => [$this, 'markDone'],
            ]);

            register_rest_route('sgjobs/v1', '/jobs/(?P<id>\\d+)/billable', [
                'methods' => WP_REST_Server::CREATABLE,
                'permission_callback' => [$this->auth, 'requireDispo'],
                'callback' => [$this, 'markBillable'],
            ]);
        });
    }

    public function listJobs(\WP_REST_Request $request)
    {
        // Placeholder board data until board service is implemented
        return [
            'jobs' => [],
            'blockers' => []
        ];
    }

    public function createJob(WP_REST_Request $request)
    {
        $payload = $request->get_json_params();
        $result = $this->jobs->createJob($payload, get_current_user_id());
        if ($result instanceof \WP_Error) {
            return $this->auth->toRestError($result);
        }

        return $result;
    }

    public function getJob(WP_REST_Request $request)
    {
        $jobId = (int) $request['id'];
        $result = $this->jobs->getJobById($jobId);
        if ($result instanceof \WP_Error) {
            return $this->auth->toRestError($result);
        }

        return $result;
    }

    public function markDone(WP_REST_Request $request)
    {
        $jobId = (int) $request['id'];
        $comment = $request->get_json_params()['comment'] ?? '';
        $tokenClaims = $this->auth->currentInstallerClaims();
        $result = $this->jobs->markDone($jobId, $comment, $tokenClaims['sub'] ?? 'installer');
        if ($result instanceof \WP_Error) {
            return $this->auth->toRestError($result);
        }

        return ['status' => 'done'];
    }

    public function markBillable(WP_REST_Request $request)
    {
        $jobId = (int) $request['id'];
        $result = $this->jobs->markBillable($jobId, get_current_user_id());
        if ($result instanceof \WP_Error) {
            return $this->auth->toRestError($result);
        }

        return ['status' => 'billable'];
    }
}
