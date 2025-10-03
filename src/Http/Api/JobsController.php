<?php

declare(strict_types=1);

namespace SGJobs\Http\Api;

use SGJobs\App\JobsService;
use SGJobs\Http\Middleware\Auth;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
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

    /**
     * @param WP_REST_Request<array<string, mixed>> $request
     * @return array{jobs:array<array-key, mixed>, blockers:array<array-key, mixed>}
     */
    public function listJobs(WP_REST_Request $request): array
    {
        // Placeholder board data until board service is implemented
        return [
            'jobs' => [],
            'blockers' => []
        ];
    }

    /**
     * @param WP_REST_Request<array<string, mixed>> $request
     * @return array{job_id:int,public_job_url:string,caldav_event_uid:string}|WP_REST_Response
     */
    public function createJob(WP_REST_Request $request): array|WP_REST_Response
    {
        $payload = $request->get_json_params();
        if (! is_array($payload)) {
            return $this->auth->toRestError(new WP_Error('sg_jobs_invalid_payload', __('Ungültige Anfragedaten.', 'sg-jobs')));
        }
        $result = $this->jobs->createJob($payload, get_current_user_id());
        if ($result instanceof \WP_Error) {
            return $this->auth->toRestError($result);
        }

        return $result;
    }

    /**
     * @param WP_REST_Request<array<string, mixed>> $request
     * @return array<string, mixed>|WP_REST_Response
     */
    public function getJob(WP_REST_Request $request): array|WP_REST_Response
    {
        $jobId = (int) $request['id'];
        $result = $this->jobs->getJobById($jobId);
        if ($result instanceof \WP_Error) {
            return $this->auth->toRestError($result);
        }

        return $result;
    }

    /**
     * @param WP_REST_Request<array<string, mixed>> $request
     * @return array{status:string}|WP_REST_Response
     */
    public function markDone(WP_REST_Request $request): array|WP_REST_Response
    {
        $jobId = (int) $request['id'];
        $payload = $request->get_json_params();
        if (! is_array($payload)) {
            return $this->auth->toRestError(new WP_Error('sg_jobs_invalid_payload', __('Ungültige Anfragedaten.', 'sg-jobs')));
        }
        $comment = (string) ($payload['comment'] ?? '');
        $tokenClaims = $this->auth->currentInstallerClaims();
        $result = $this->jobs->markDone($jobId, $comment, $tokenClaims['sub'] ?? 'installer');
        if ($result instanceof \WP_Error) {
            return $this->auth->toRestError($result);
        }

        return ['status' => 'done'];
    }

    /**
     * @param WP_REST_Request<array<string, mixed>> $request
     * @return array{status:string}|WP_REST_Response
     */
    public function markBillable(WP_REST_Request $request): array|WP_REST_Response
    {
        $jobId = (int) $request['id'];
        $result = $this->jobs->markBillable($jobId, get_current_user_id());
        if ($result instanceof \WP_Error) {
            return $this->auth->toRestError($result);
        }

        return ['status' => 'billable'];
    }
}
