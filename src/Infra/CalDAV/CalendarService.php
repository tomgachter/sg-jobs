<?php

declare(strict_types=1);

namespace SGJobs\Infra\CalDAV;

use DateTimeImmutable;
use SGJobs\Domain\Entity\Job;
use SGJobs\Util\Formatting;
use WP_Error;

class CalendarService
{
    public function __construct(private CalDAVClient $client)
    {
    }

    public function createOrUpdateEvent(Job $job): string|WP_Error
    {
        $uid = $job->caldavEventUid() ?: $this->generateUid($job);
        $timeRange = $job->timeRangeUtc();
        $summary = Formatting::formatTitle($job);
        $description = Formatting::formatDescription($job);
        $location = Formatting::formatLocation($job);
        $ics = $this->buildVevent($uid, $timeRange->start(), $timeRange->end(), $summary, $description, $location, $job->publicUrl());

        try {
            $path = $job->team()->calendarPath() . $uid . '.ics';
            $this->client->request('PUT', $path, ['body' => $ics, 'headers' => ['Content-Type' => 'text/calendar']]);
            return $uid;
        } catch (\Throwable $exception) {
            return new WP_Error('sg_jobs_caldav', $exception->getMessage());
        }
    }

    public function updateTitleEmoji(Job $job, string $status): bool|WP_Error
    {
        $summary = Formatting::formatTitle($job->withStatus($status));
        $body = sprintf("BEGIN:VNOTE\nSUMMARY:%s\nEND:VNOTE", $summary);
        try {
            $this->client->request('PUT', $job->team()->calendarPath() . $job->caldavEventUid() . '.summary', ['body' => $body]);
            return true;
        } catch (\Throwable $exception) {
            return new WP_Error('sg_jobs_caldav', $exception->getMessage());
        }
    }

    public function updateDescriptionBlock(Job $job): bool|WP_Error
    {
        $description = Formatting::formatDescription($job);
        $body = sprintf("BEGIN:VNOTE\nBODY:%s\nEND:VNOTE", $description);
        try {
            $this->client->request('PUT', $job->team()->calendarPath() . $job->caldavEventUid() . '.description', ['body' => $body]);
            return true;
        } catch (\Throwable $exception) {
            return new WP_Error('sg_jobs_caldav', $exception->getMessage());
        }
    }

    private function buildVevent(string $uid, DateTimeImmutable $start, DateTimeImmutable $end, string $summary, string $description, string $location, string $url): string
    {
        $template = "BEGIN:VCALENDAR\nVERSION:2.0\nPRODID:-//SG Jobs//Plugin//EN\nBEGIN:VEVENT\nUID:%s\nDTSTAMP:%s\nDTSTART:%s\nDTEND:%s\nSUMMARY:%s\nDESCRIPTION:%s\nLOCATION:%s\nURL:%s\nEND:VEVENT\nEND:VCALENDAR";
        return sprintf(
            $template,
            $uid,
            $start->format('Ymd\THis\Z'),
            $start->format('Ymd\THis\Z'),
            $end->format('Ymd\THis\Z'),
            $this->escape($summary),
            $this->escape($description),
            $this->escape($location),
            $this->escape($url)
        );
    }

    private function escape(string $value): string
    {
        return str_replace(["\n", ',', ';'], ['\\n', '\\,', '\\;'], $value);
    }

    private function generateUid(Job $job): string
    {
        return sprintf('sgjobs-%d-%s', $job->id(), uniqid('evt', true));
    }
}
