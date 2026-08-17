<?php

declare(strict_types=1);

namespace App\Auth;

use App\Config\AdminAuthConfig;
use Google\Client;
use RuntimeException;

final class GoogleOidcClient implements OidcClientInterface
{
    public function __construct(private readonly AdminAuthConfig $config)
    {
    }

    public function authorizationUrl(string $state, string $redirectUri): string
    {
        $client = $this->client($redirectUri);
        $client->setState($state);
        return $client->createAuthUrl();
    }

    public function exchangeCode(string $code, string $redirectUri): AdminIdentity
    {
        $client = $this->client($redirectUri);
        $tokens = $client->fetchAccessTokenWithAuthCode($code);
        if (isset($tokens['error']) || !isset($tokens['id_token']) || !is_string($tokens['id_token'])) {
            throw new RuntimeException('Google no devolvió un ID token válido');
        }

        $claims = $client->verifyIdToken($tokens['id_token']);
        if (!is_array($claims)) {
            throw new RuntimeException('El ID token no pudo verificarse');
        }

        $issuer = $claims['iss'] ?? null;
        $audience = $claims['aud'] ?? null;
        $expiresAt = $claims['exp'] ?? null;
        $email = $claims['email'] ?? null;
        $verified = $claims['email_verified'] ?? false;
        $subject = $claims['sub'] ?? null;

        if (!in_array($issuer, ['accounts.google.com', 'https://accounts.google.com'], true)) {
            throw new RuntimeException('Issuer OIDC no válido');
        }
        if (!is_string($audience) || !hash_equals($this->config->googleClientId(), $audience)) {
            throw new RuntimeException('Audience OIDC no válida');
        }
        if (!is_numeric($expiresAt) || (int) $expiresAt <= time()) {
            throw new RuntimeException('ID token expirado');
        }
        if (!is_string($email) || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new RuntimeException('Email OIDC no válido');
        }
        if ($verified !== true && $verified !== 'true') {
            throw new RuntimeException('El email de Google no está verificado');
        }
        if (!is_string($subject) || $subject === '') {
            throw new RuntimeException('Subject OIDC no válido');
        }

        return new AdminIdentity(
            $subject,
            strtolower($email),
            isset($claims['name']) && is_string($claims['name']) ? $claims['name'] : null,
            isset($claims['picture']) && is_string($claims['picture']) ? $claims['picture'] : null
        );
    }

    private function client(string $redirectUri): Client
    {
        $client = new Client();
        $client->setClientId($this->config->googleClientId());
        $client->setClientSecret($this->config->googleClientSecret());
        $client->setRedirectUri($redirectUri);
        $client->setScopes(['openid', 'email', 'profile']);
        $client->setAccessType('online');
        $client->setPrompt('select_account');
        return $client;
    }
}
