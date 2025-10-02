<?php

declare(strict_types=1);

namespace SGJobs\Infra\Security;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use WP_Error;

class JwtService
{
    private string $secret;

    private int $expiryDays;

    public function __construct()
    {
        $options = get_option('sg_jobs_jwt', []);
        $this->secret = $options['secret'] ?? getenv('JWT_SECRET') ?? '';
        $this->expiryDays = (int) ($options['expiry_days'] ?? getenv('JWT_EXPIRE_DAYS') ?? 14);
    }

    public function createToken(array $claims): string
    {
        $payload = array_merge($claims, [
            'iat' => time(),
            'exp' => time() + ($this->expiryDays * DAY_IN_SECONDS),
            'nonce' => wp_generate_password(12, false),
        ]);

        return JWT::encode($payload, $this->secret, 'HS256');
    }

    public function validateToken(string $token): array|WP_Error
    {
        try {
            return (array) JWT::decode($token, new Key($this->secret, 'HS256'));
        } catch (\Throwable $exception) {
            return new WP_Error('sg_jobs_invalid_token', $exception->getMessage());
        }
    }
}
