<?php

declare(strict_types=1);

namespace SGJobs\Infra\WebDAV;

class NextcloudStorage
{
    public function createSignedUploadUrl(string $path, int $expiresIn): string
    {
        $base = rtrim(getenv('CALDAV_BASE_URL') ?: '', '/');
        $token = hash_hmac('sha256', $path . time(), wp_salt('auth'));
        return sprintf('%s/uploads%s?token=%s&expires=%d', $base, $path, $token, time() + $expiresIn);
    }
}
