<?php

declare(strict_types=1);

if (! defined('ABSPATH')) {
    define('ABSPATH', '/var/www/html/');
}

if (! defined('ARRAY_A')) {
    define('ARRAY_A', 'ARRAY_A');
}

if (! defined('ARRAY_N')) {
    define('ARRAY_N', 'ARRAY_N');
}

if (! defined('OBJECT')) {
    define('OBJECT', 'OBJECT');
}

if (! defined('DAY_IN_SECONDS')) {
    define('DAY_IN_SECONDS', 86400);
}

if (! defined('SGJOBS_PLUGIN_FILE')) {
    define('SGJOBS_PLUGIN_FILE', __FILE__);
}

if (! function_exists('as_schedule_single_action')) {
    /**
     * @param array<string, mixed> $args
     */
    function as_schedule_single_action(int $timestamp, string $hook, array $args = [], string $group = ''): int
    {
        return 0;
    }
}

if (! function_exists('as_enqueue_async_action')) {
    /**
     * @param array<string, mixed> $args
     */
    function as_enqueue_async_action(string $hook, array $args = [], string $group = ''): int
    {
        return 0;
    }
}
