<?php

declare(strict_types=1);

namespace SGJobs\Util;

use DateTimeImmutable;
use DateTimeZone;
use SGJobs\Domain\EmojiStatus;
use SGJobs\Domain\Entity\Job;
use SGJobs\Domain\Entity\JobPosition;
use SGJobs\Domain\Entity\Team;
use SGJobs\Domain\ValueObjects\Address;
use SGJobs\Domain\ValueObjects\PhoneList;
use SGJobs\Domain\ValueObjects\TimeRange;

class Formatting
{
    public static function formatTitle(Job $job): string
    {
        return sprintf('%s %s | %s | %s', $job->statusEmoji(), $job->address()->city(), $job->deliveryNoteNumber(), $job->customerName());
    }

    public static function formatLocation(Job $job): string
    {
        return $job->address()->city();
    }

    public static function formatDescription(Job $job): string
    {
        $phones = implode('; ', $job->phones()->toArray());
        $notes = $job->notes() ?: '-';
        return sprintf(
            "LieferscheinNr: %s\nAuftragNr: %s\nKunde: %s\nTelefon: %s\nAdresse: %s\nHinweise: %s\nJob-URL: %s\nStatus: %s",
            $job->deliveryNoteNumber(),
            $job->salesOrderNumber() ?: '-',
            $job->customerName(),
            $phones ?: '-',
            $job->address()->formatted(),
            $notes,
            $job->publicUrl(),
            $job->status()
        );
    }

    public static function jobFromRow(array $row): Job
    {
        global $wpdb;
        $teamRow = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . $wpdb->prefix . 'sg_teams WHERE id = %d', (int) $row['team_id']), ARRAY_A);
        $team = new Team((int) $row['team_id'], $teamRow['name'] ?? 'Team', $teamRow['caldav_principal'] ?? '', $teamRow['team_calendar_path'] ?? '', $teamRow['blocker_calendar_path'] ?? '');
        $start = new DateTimeImmutable($row['starts_at'], new DateTimeZone('UTC'));
        $end = new DateTimeImmutable($row['ends_at'], new DateTimeZone('UTC'));
        $phonesArray = json_decode($row['phones_json'] ?? '[]', true);
        if (!is_array($phonesArray)) {
            $phonesArray = [];
        }
        $phones = new PhoneList($phonesArray);
        $timeRange = new TimeRange($start, $end);
        $address = new Address($row['address_line'] ?? '', '', $row['location_city'], 'CH');
        $positions = array_map(static function (array $pos): JobPosition {
            return new JobPosition($pos['bexio_position_id'], $pos['article_no'], $pos['title'], $pos['description'], (float) $pos['qty'], $pos['unit'], $pos['work_type'], (int) $pos['sort']);
        }, $row['positions'] ?? []);

        return new Job(
            (int) $row['id'],
            (int) $row['delivery_note_id'],
            $row['delivery_note_nr'],
            $row['sales_order_nr'],
            $team,
            $timeRange,
            $address,
            $phones,
            $row['customer_name'] ?? '',
            $row['status'],
            $row['tz'],
            $row['caldav_event_uid'] ?? null,
            $row['public_url'] ?? '',
            $positions,
            $row['notes'] ?? null
        );
    }
}
