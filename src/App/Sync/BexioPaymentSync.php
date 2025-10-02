<?php

declare(strict_types=1);

namespace SGJobs\App\Sync;

use SGJobs\App\JobsService;
use SGJobs\Infra\Bexio\BexioClient;
use SGJobs\Infra\Bexio\BexioService;
use SGJobs\Infra\Logging\Logger;
use WP_Error;

class BexioPaymentSync
{
    private BexioService $bexio;

    public function __construct(private JobsService $jobs)
    {
        $this->bexio = new BexioService(new BexioClient());
    }

    public function syncPaidInvoices(): bool|WP_Error
    {
        global $wpdb;
        $jobs = $wpdb->get_results("SELECT id FROM {$wpdb->prefix}sg_jobs WHERE status IN ('billable','done')", ARRAY_A);
        foreach ($jobs as $jobRow) {
            $jobId = (int) $jobRow['id'];
            $job = $this->jobs->getJobById($jobId);
            if ($job instanceof WP_Error) {
                continue;
            }
            $jobObject = \SGJobs\Util\Formatting::jobFromRow($job);
            $status = $this->bexio->getInvoicePaymentStatusForJob($jobObject);
            if ($status instanceof WP_Error) {
                Logger::instance()->warning('Payment sync failed', ['job_id' => $jobId, 'error' => $status->get_error_message()]);
                continue;
            }
            if ($status) {
                $this->jobs->markPaid($jobId);
            }
        }

        return true;
    }
}
