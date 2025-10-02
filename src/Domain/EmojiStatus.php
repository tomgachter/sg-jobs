<?php

declare(strict_types=1);

namespace SGJobs\Domain;

class EmojiStatus
{
    private const MAP = [
        'open' => '\u{1F534}',
        'done' => '\u{2705}',
        'billable' => '\u{1F9FE}',
        'paid' => '\u{1F4B0}',
    ];

    public static function fromStatus(string $status): string
    {
        return self::MAP[$status] ?? self::MAP['open'];
    }
}
