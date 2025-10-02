<?php

declare(strict_types=1);

namespace SGJobs\Infra\Queue;

class QueueService
{
    public function dispatch(string $hook, array $args = [], int $delay = 0): void
    {
        if ($delay > 0) {
            as_schedule_single_action(time() + $delay, $hook, $args, 'sg-jobs');
        } else {
            as_enqueue_async_action($hook, $args, 'sg-jobs');
        }
    }
}
