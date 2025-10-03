<?php

declare(strict_types=1);

namespace SGJobs\Http\Middleware;

use SGJobs\Infra\Security\JwtService;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

class Auth
{
    /**
     * @var array<string, mixed>|null
     */
    private ?array $installerClaims = null;

    private JwtService $jwt;

    public function __construct()
    {
        $this->jwt = new JwtService();
    }

    public function requireDispo(): bool
    {
        return current_user_can('sgjobs_manage');
    }

    public function allowInstallerOrDispo(): bool
    {
        return $this->requireDispo() || $this->requireInstaller();
    }

    public function requireInstaller(): bool
    {
        $header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        if (! str_starts_with($header, 'Bearer ')) {
            return false;
        }
        $token = substr($header, 7);
        $claims = $this->jwt->validateToken($token);
        if ($claims instanceof WP_Error) {
            return false;
        }
        $this->installerClaims = $claims;
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function currentInstallerClaims(): array
    {
        return $this->installerClaims ?? [];
    }

    /**
     * @return array<string, mixed>|WP_Error
     */
    public function validateInstallerToken(string $token): array|WP_Error
    {
        return $this->jwt->validateToken($token);
    }

    public function toRestError(WP_Error $error): WP_REST_Response
    {
        return new WP_REST_Response([
            'error' => [
                'code' => $error->get_error_code(),
                'message' => $error->get_error_message(),
            ],
        ], (int) ($error->get_error_data() ?: 400));
    }
}
