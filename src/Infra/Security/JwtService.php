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

    public function __construct(?string $secret = null, ?int $expiryDays = null)
    {
        $secret = is_string($secret) ? trim($secret) : '';

        if ($secret === '') {
            $optionSecret = get_option('sg_jobs_jwt_secret', '');
            if (is_string($optionSecret)) {
                $secret = trim($optionSecret);
            }
        }

        if ($secret === '') {
            $options = get_option('sg_jobs_jwt', []);
            if (is_array($options) && isset($options['secret'])) {
                $secret = trim((string) $options['secret']);
            }
        }

        if ($secret === '') {
            $envSecret = getenv('JWT_SECRET');
            if (is_string($envSecret)) {
                $secret = trim($envSecret);
            }
        }

        if ($secret === '') {
            wp_die(
                'SG Jobs: JWT Secret ist nicht konfiguriert. Bitte unter <strong>Einstellungen → SG Jobs</strong> ein langes Secret setzen.'
            );
        }

        $this->secret = $secret;

        if (! is_int($expiryDays) || $expiryDays <= 0) {
            $optionExpiry = get_option('sg_jobs_jwt_expire_days', null);
            if (is_numeric($optionExpiry)) {
                $expiryDays = (int) $optionExpiry;
            }
        }

        if (! is_int($expiryDays) || $expiryDays <= 0) {
            $options = get_option('sg_jobs_jwt', []);
            if (is_array($options) && isset($options['expiry_days'])) {
                $expiryDays = (int) $options['expiry_days'];
            }
        }

        if (! is_int($expiryDays) || $expiryDays <= 0) {
            $envExpiry = getenv('JWT_EXPIRE_DAYS');
            if (is_numeric($envExpiry)) {
                $expiryDays = (int) $envExpiry;
            }
        }

        $this->expiryDays = ($expiryDays && $expiryDays > 0) ? $expiryDays : 14;
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
