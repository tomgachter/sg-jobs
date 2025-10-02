<?php

declare(strict_types=1);

namespace SGJobs\Domain\ValueObjects;

use DateTimeImmutable;
use DateTimeZone;

class TimeRange
{
    public function __construct(private DateTimeImmutable $start, private DateTimeImmutable $end)
    {
    }

    public function start(): DateTimeImmutable
    {
        return $this->start;
    }

    public function end(): DateTimeImmutable
    {
        return $this->end;
    }

    public function toUtc(string $timezone): self
    {
        $tz = new DateTimeZone($timezone);
        return new self(
            $this->start->setTimezone($tz)->setTimezone(new DateTimeZone('UTC')),
            $this->end->setTimezone($tz)->setTimezone(new DateTimeZone('UTC'))
        );
    }
}
