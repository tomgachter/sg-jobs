<?php

declare(strict_types=1);

namespace SGJobs\App\Sync;

use SGJobs\Infra\CalDAV\CalDAVClient;
use SGJobs\Infra\Logging\Logger;

class CalDavWatcher
{
    public function __construct(private CalDAVClient $client)
    {
    }

    public function pollForChanges(): void
    {
        Logger::instance()->info('CalDAV watcher executed', [
            'client' => get_class($this->client),
        ]);
    }
}
