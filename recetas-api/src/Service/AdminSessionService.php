<?php

declare(strict_types=1);

namespace App\Service;

use App\Auth\AdminIdentity;
use App\Config\AdminAuthConfig;
use App\Repository\AdminSessionRepository;

final class AdminSessionService
{
    public const SESSION_COOKIE = 'recetas_admin_session';
    public const STATE_COOKIE = 'recetas_admin_oauth_state';
    public const SESSION_TTL = 43200;
    public const STATE_TTL = 600;

    public function __construct(
        private readonly AdminSessionRepository $repository,
        private readonly AdminAuthConfig $config
    ) {
    }

    public function createState(): string
    {
        $state = $this->randomToken();
        $this->repository->storeState($this->hash($state), time() + self::STATE_TTL);
        return $state;
    }

    public function consumeState(string $state): bool
    {
        return $this->repository->consumeState($this->hash($state), time());
    }

    public function createSession(AdminIdentity $identity): string
    {
        $token = $this->randomToken();
        $now = time();
        $this->repository->createSession(
            $this->hash($token),
            $identity,
            $now,
            $now + self::SESSION_TTL
        );
        return $token;
    }

    public function findIdentity(string $token): ?AdminIdentity
    {
        if ($token === '' || !$this->config->isSessionConfigured()) {
            return null;
        }
        $identity = $this->repository->findValidSession($this->hash($token), time());
        if ($identity !== null && !$this->config->isEmailAllowed($identity->email)) {
            $this->deleteSession($token);
            return null;
        }
        return $identity;
    }

    public function deleteSession(string $token): void
    {
        if ($token !== '' && $this->config->isSessionConfigured()) {
            $this->repository->deleteSession($this->hash($token));
        }
    }

    private function hash(string $value): string
    {
        return hash_hmac('sha256', $value, $this->config->sessionSecret());
    }

    private function randomToken(): string
    {
        return rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
    }
}
