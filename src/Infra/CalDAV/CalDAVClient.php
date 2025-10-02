<?php

declare(strict_types=1);

namespace SGJobs\Infra\CalDAV;

use GuzzleHttp\Client;

class CalDAVClient
{
    private Client $client;

    public function __construct(?Client $client = null)
    {
        $options = get_option('sg_jobs_caldav', []);
        $base = $options['base_url'] ?? getenv('CALDAV_BASE_URL') ?: '';
        $user = $options['username'] ?? getenv('CALDAV_USER') ?: '';
        $pass = $options['password'] ?? getenv('CALDAV_PASS') ?: '';
        $this->client = $client ?: new Client([
            'base_uri' => $base,
            'auth' => [$user, $pass],
            'timeout' => 10,
        ]);
    }

    public function request(string $method, string $path, array $options = []): string
    {
        $response = $this->client->request($method, $path, $options);
        return (string) $response->getBody();
    }
}
