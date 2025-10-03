<?php

declare(strict_types=1);

namespace SGJobs\App;

use DateTimeImmutable;
use DateTimeZone;
use SGJobs\Infra\Bexio\BexioClient;
use SGJobs\Infra\Bexio\BexioService;
use SGJobs\Infra\CalDAV\CalDAVClient;
use SGJobs\Infra\CalDAV\CalendarService;
use SGJobs\Infra\Security\JwtService;
use SGJobs\Util\Formatting;
use WP_Error;

class JobsService
{
    private BexioService $bexio;

    private CalendarService $calendar;

    private JwtService $jwt;

    public function __construct()
    {
        $this->bexio = new BexioService(new BexioClient());
        $this->calendar = new CalendarService(new CalDAVClient());
        $this->jwt = new JwtService();
    }

    /**
     * @param array{
     *     delivery_note_nr:string,
     *     team_id:int,
     *     starts_at:string,
     *     ends_at:string,
     *     timezone?:string,
     *     phones?:array<int, string>,
     *     location_city:string,
     *     notes?:string
     * } $payload
     * @return array{job_id:int,public_job_url:string,caldav_event_uid:string}|WP_Error
     */
    public function createJob(array $payload, int $authorId): array|WP_Error
    {
        global $wpdb;
        $deliveryNote = $this->bexio->getDeliveryNoteByNumber($payload['delivery_note_nr']);
        if ($deliveryNote instanceof WP_Error) {
            return $deliveryNote;
        }
        $positions = $this->bexio->getDeliveryNotePositions($deliveryNote['id']);
        if ($positions instanceof WP_Error) {
            return $positions;
        }

        $phones = $deliveryNote['phones'];
        if (empty($phones)) {
            $phones = $payload['phones'] ?? [];
            if (empty($phones)) {
                return new WP_Error('sg_jobs_missing_phone', __('Phone number required when delivery note has none.', 'sg-jobs'));
            }
        }

        $tz = new DateTimeZone($payload['timezone'] ?? 'Europe/Zurich');
        $start = new DateTimeImmutable($payload['starts_at'], $tz);
        $end = new DateTimeImmutable($payload['ends_at'], $tz);
        $jobData = [
            'delivery_note_id' => $deliveryNote['id'],
            'delivery_note_nr' => $deliveryNote['document_nr'],
            'sales_order_nr' => $deliveryNote['sales_order_nr'],
            'team_id' => $payload['team_id'],
            'starts_at' => $start->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s'),
            'ends_at' => $end->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s'),
            'tz' => $tz->getName(),
            'location_city' => sanitize_text_field($payload['location_city']),
            'address_line' => sanitize_text_field(sprintf('%s, %s %s', $deliveryNote['delivery_address']['address_line1'] ?? '', $deliveryNote['delivery_address']['zip'] ?? '', $deliveryNote['delivery_address']['city'] ?? '')),
            'phones_json' => wp_json_encode(array_values($phones)),
            'status' => 'open',
            'customer_name' => sanitize_text_field($deliveryNote['customer_name']),
            'notes' => sanitize_textarea_field($payload['notes'] ?? ''),
            'created_by' => $authorId,
        ];

        $wpdb->insert($wpdb->prefix . 'sg_jobs', $jobData);
        $jobId = (int) $wpdb->insert_id;

        /** @var array<int, array<string, mixed>> $positions */
        foreach ($positions as $index => $position) {
            $wpdb->insert($wpdb->prefix . 'sg_job_positions', [
                'job_id' => $jobId,
                'bexio_position_id' => $position['bexio_position_id'],
                'article_no' => $position['article_no'],
                'title' => $position['title'],
                'description' => $position['description'],
                'qty' => $position['qty'],
                'unit' => $position['unit'],
                'work_type' => 'unknown',
                'sort' => $index,
            ]);
        }

        $token = $this->jwt->createToken(['job_id' => $jobId, 'sub' => 'job:' . $jobId]);
        $publicUrl = home_url('/jobs/' . rawurlencode($token));

        $jobRow = $this->getJobById($jobId);
        if ($jobRow instanceof WP_Error) {
            return $jobRow;
        }
        $jobRow['public_url'] = $publicUrl;
        $jobRow['public_job_url'] = $publicUrl;
        $uid = $this->calendar->createOrUpdateEvent(Formatting::jobFromRow($jobRow));
        if ($uid instanceof WP_Error) {
            return $uid;
        }

        $wpdb->update($wpdb->prefix . 'sg_jobs', [
            'caldav_event_uid' => $uid,
            'job_token_hash' => hash('sha256', $token),
            'public_job_url' => $publicUrl,
        ], ['id' => $jobId]);

        return [
            'job_id' => $jobId,
            'public_job_url' => $publicUrl,
            'caldav_event_uid' => $uid,
        ];
    }

    /**
     * @param array<string, mixed> $meta
     */
    public function markStatus(int $jobId, string $status, array $meta = []): bool|WP_Error
    {
        global $wpdb;
        $jobRow = $this->getJobById($jobId);
        if ($jobRow instanceof WP_Error) {
            return $jobRow;
        }
        $wpdb->update($wpdb->prefix . 'sg_jobs', [
            'status' => $status,
            'updated_by' => $meta['actor'] ?? null,
        ], ['id' => $jobId]);
        $jobRow['status'] = $status;
        $jobRow['public_url'] = $jobRow['public_job_url'] ?? '';
        $job = Formatting::jobFromRow($jobRow);
        $this->calendar->createOrUpdateEvent($job);

        $this->logAudit($jobId, $status, $meta);
        return true;
    }

    public function markBillable(int $jobId, int $actorId): bool|WP_Error
    {
        return $this->markStatus($jobId, 'billable', ['actor' => (string) $actorId]);
    }

    public function markDone(int $jobId, string $comment, string $actor): bool|WP_Error
    {
        global $wpdb;
        $wpdb->update($wpdb->prefix . 'sg_jobs', ['notes' => $comment], ['id' => $jobId]);
        return $this->markStatus($jobId, 'done', ['actor' => $actor, 'comment' => $comment]);
    }

    public function markPaid(int $jobId): bool|WP_Error
    {
        return $this->markStatus($jobId, 'paid', ['actor' => 'system']);
    }

    /**
     * @return array<string, mixed>|WP_Error
     */
    public function getJobById(int $jobId): array|WP_Error
    {
        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . $wpdb->prefix . 'sg_jobs WHERE id = %d', $jobId), ARRAY_A);
        if (! $row) {
            return new WP_Error('sg_jobs_not_found', __('Job not found.', 'sg-jobs'));
        }
        $row['positions'] = $wpdb->get_results($wpdb->prepare('SELECT * FROM ' . $wpdb->prefix . 'sg_job_positions WHERE job_id = %d ORDER BY sort ASC', $jobId), ARRAY_A);
        $phones = json_decode($row['phones_json'] ?? '[]', true);
        $row['phones'] = is_array($phones) ? $phones : [];
        $row['public_url'] = $row['public_job_url'] ?? '';
        unset($row['phones_json'], $row['job_token_hash']);
        return $row;
    }

    /**
     * @param array<string, mixed> $meta
     */
    private function logAudit(int $jobId, string $action, array $meta): void
    {
        global $wpdb;
        $wpdb->insert($wpdb->prefix . 'sg_job_audit', [
            'job_id' => $jobId,
            'actor' => $meta['actor'] ?? 'unknown',
            'action' => $action,
            'payload_json' => wp_json_encode($meta),
        ]);
    }
}
