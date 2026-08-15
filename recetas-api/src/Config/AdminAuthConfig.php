<?php

declare(strict_types=1);

namespace App\Config;

use RuntimeException;

final class AdminAuthConfig
{
    public function googleClientId(): string
    {
        return $this->required('GOOGLE_CLIENT_ID');
    }

    public function googleClientSecret(): string
    {
        return $this->required('GOOGLE_CLIENT_SECRET');
    }

    public function redirectUri(): string
    {
        $uri = $this->required('ADMIN_GOOGLE_REDIRECT_URI');
        if (filter_var($uri, FILTER_VALIDATE_URL) === false) {
            throw new RuntimeException('ADMIN_GOOGLE_REDIRECT_URI no es válida');
        }
        return $uri;
    }

    public function sessionSecret(): string
    {
        $secret = $this->required('ADMIN_SESSION_SECRET');
        if (strlen($secret) < 32) {
            throw new RuntimeException('ADMIN_SESSION_SECRET debe tener al menos 32 caracteres');
        }
        return $secret;
    }

    /** @return list<string> */
    public function allowedEmails(): array
    {
        return $this->csv('ADMIN_ALLOWED_EMAILS');
    }

    /** @return list<string> */
    public function allowedOrigins(): array
    {
        return $this->csv('ADMIN_ALLOWED_ORIGINS');
    }

    public function isGoogleConfigured(): bool
    {
        return $this->has('GOOGLE_CLIENT_ID')
            && $this->has('GOOGLE_CLIENT_SECRET')
            && $this->has('ADMIN_GOOGLE_REDIRECT_URI')
            && $this->isSessionConfigured()
            && $this->allowedEmails() !== [];
    }

    public function isSessionConfigured(): bool
    {
        $secret = getenv('ADMIN_SESSION_SECRET');
        return is_string($secret) && strlen($secret) >= 32;
    }

    public function isEmailAllowed(string $email): bool
    {
        return in_array(strtolower(trim($email)), $this->allowedEmails(), true);
    }

    public function secureCookies(): bool
    {
        if (!$this->has('ADMIN_GOOGLE_REDIRECT_URI')) {
            return true;
        }
        return parse_url($this->redirectUri(), PHP_URL_SCHEME) === 'https';
    }

    private function has(string $name): bool
    {
        $value = getenv($name);
        return is_string($value) && trim($value) !== '';
    }

    private function required(string $name): string
    {
        $value = getenv($name);
        if (!is_string($value) || trim($value) === '') {
            throw new RuntimeException($name . ' no está configurada');
        }
        return trim($value);
    }

    /** @return list<string> */
    private function csv(string $name): array
    {
        $value = getenv($name);
        if (!is_string($value)) {
            return [];
        }
        $values = array_map(
            static fn(string $item): string => strtolower(trim($item)),
            explode(',', $value)
        );
        return array_values(array_filter($values, static fn(string $item): bool => $item !== ''));
    }
}
