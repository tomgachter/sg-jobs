<?php

declare(strict_types=1);

namespace SGJobs\Infra\Bexio;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Psr\Log\LoggerInterface;
use SGJobs\Infra\Logging\Logger;
use Throwable;

class BexioClient
{
    private Client $client;

    private LoggerInterface $logger;

    public function __construct(?Client $client = null, ?LoggerInterface $logger = null)
    {
        $options = get_option('sg_jobs_bexio', []);
        $baseUrl = $options['base_url'] ?? getenv('BEXIO_BASE_URL') ?: '';
        $token = $options['token'] ?? getenv('BEXIO_API_TOKEN') ?: '';
        $this->client = $client ?: new Client([
            'base_uri' => $baseUrl,
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
                'Accept' => 'application/json',
            ],
            'timeout' => 10,
        ]);
        $this->logger = $logger ?: Logger::instance();
    }

    /**
     * @param array<string, mixed> $query
     * @return array<string, mixed>
     * @throws GuzzleException
     */
    public function get(string $path, array $query = []): array
    {
        $attempts = 0;
        $maxAttempts = 5;
        $delay = 0.2;
        while (true) {
            try {
                $response = $this->client->get($path, ['query' => $query]);
                return json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);
            } catch (GuzzleException $exception) {
                $attempts++;
                if ($attempts >= $maxAttempts) {
                    throw $exception;
                }
                $this->logger->warning('Retrying bexio request', ['path' => $path, 'error' => $exception->getMessage(), 'attempt' => $attempts]);
                usleep((int) ($delay * 1e6));
                $delay = min($delay * 2, 3.0);
            } catch (Throwable $error) {
                $this->logger->error('Unexpected bexio error', ['path' => $path, 'error' => $error->getMessage()]);
                throw $error;
            }
        }
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     * @throws GuzzleException
     */
    public function post(string $path, array $payload): array
    {
        $response = $this->client->post($path, ['json' => $payload]);
        return json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);
    }
}
