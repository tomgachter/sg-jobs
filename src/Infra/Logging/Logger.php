<?php

declare(strict_types=1);

namespace SGJobs\Infra\Logging;

use Psr\Log\AbstractLogger;
use Stringable;

class Logger extends AbstractLogger
{
    private static ?self $instance = null;

    public static function instance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * @param string $level
     * @param string|Stringable $message
     * @param array<string, mixed> $context
     */
    public function log($level, $message, array $context = []): void
    {
        $entry = sprintf('[SG Jobs][%s] %s %s', strtoupper((string) $level), $message, wp_json_encode($context));
        error_log($entry);
    }
}
