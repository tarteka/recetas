<?php

declare(strict_types=1);

namespace App\Controller;

use App\Auth\AdminIdentity;
use App\Auth\OidcClientInterface;
use App\Config\AdminAuthConfig;
use App\Service\AdminSessionService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Throwable;

final class AdminAuthController
{
    public function __construct(
        private readonly AdminAuthConfig $config,
        private readonly OidcClientInterface $oidc,
        private readonly AdminSessionService $sessions
    ) {
    }

    public function login(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        if (!$this->config->isGoogleConfigured()) {
            return $this->json($response, ['error' => 'Autenticación administrativa no configurada'], 503);
        }
        $state = $this->sessions->createState();
        $url = $this->oidc->authorizationUrl($state, $this->config->redirectUri());
        return $response
            ->withStatus(302)
            ->withHeader('Location', $url)
            ->withHeader('Set-Cookie', $this->stateCookie($state));
    }

    public function callback(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        if (!$this->config->isGoogleConfigured()) {
            return $this->json($response, ['error' => 'Autenticación administrativa no configurada'], 503);
        }
        $query = $request->getQueryParams();
        $cookies = $request->getCookieParams();
        $state = isset($query['state']) ? (string) $query['state'] : '';
        $expectedState = isset($cookies[AdminSessionService::STATE_COOKIE])
            ? (string) $cookies[AdminSessionService::STATE_COOKIE]
            : '';
        $code = isset($query['code']) ? (string) $query['code'] : '';

        if (
            $state === '' || $expectedState === '' || !hash_equals($expectedState, $state)
            || !$this->sessions->consumeState($state)
        ) {
            return $this->callbackRedirect($response, 'invalid_state');
        }
        if ($code === '' || isset($query['error'])) {
            return $this->callbackRedirect($response, 'authentication_failed');
        }

        try {
            $identity = $this->oidc->exchangeCode($code, $this->config->redirectUri());
        } catch (Throwable $exception) {
            error_log('Fallo OIDC administrativo: ' . $exception::class);
            return $this->callbackRedirect($response, 'authentication_failed');
        }

        if (!$this->config->isEmailAllowed($identity->email)) {
            return $this->callbackRedirect($response, 'access_denied');
        }

        $token = $this->sessions->createSession($identity);
        return $response
            ->withStatus(302)
            ->withHeader('Location', '/admin/')
            ->withHeader('Set-Cookie', $this->sessionCookie($token))
            ->withAddedHeader('Set-Cookie', $this->clearStateCookie());
    }

    public function me(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $identity = $request->getAttribute('admin_identity');
        if (!$identity instanceof AdminIdentity) {
            return $this->json($response, ['error' => 'No autorizado'], 401);
        }
        return $this->json($response, $identity->toArray());
    }

    public function logout(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $cookies = $request->getCookieParams();
        $token = isset($cookies[AdminSessionService::SESSION_COOKIE])
            ? (string) $cookies[AdminSessionService::SESSION_COOKIE]
            : '';
        $this->sessions->deleteSession($token);
        return $response->withStatus(204)->withHeader('Set-Cookie', $this->clearSessionCookie());
    }

    private function callbackRedirect(ResponseInterface $response, string $error): ResponseInterface
    {
        return $response
            ->withStatus(302)
            ->withHeader('Location', '/admin/login?error=' . rawurlencode($error))
            ->withHeader('Set-Cookie', $this->clearStateCookie());
    }

    private function sessionCookie(string $token): string
    {
        return $this->cookie(
            AdminSessionService::SESSION_COOKIE,
            $token,
            '/',
            AdminSessionService::SESSION_TTL
        );
    }

    private function stateCookie(string $state): string
    {
        return $this->cookie(
            AdminSessionService::STATE_COOKIE,
            $state,
            '/api/admin/auth/google/callback',
            AdminSessionService::STATE_TTL
        );
    }

    private function clearSessionCookie(): string
    {
        return $this->expiredCookie(AdminSessionService::SESSION_COOKIE, '/');
    }

    private function clearStateCookie(): string
    {
        return $this->expiredCookie(
            AdminSessionService::STATE_COOKIE,
            '/api/admin/auth/google/callback'
        );
    }

    private function cookie(string $name, string $value, string $path, int $maxAge): string
    {
        $cookie = sprintf(
            '%s=%s; Path=%s; Max-Age=%d; Expires=%s; HttpOnly; SameSite=Lax',
            $name,
            rawurlencode($value),
            $path,
            $maxAge,
            gmdate('D, d M Y H:i:s T', time() + $maxAge)
        );
        return $this->config->secureCookies() ? $cookie . '; Secure' : $cookie;
    }

    private function expiredCookie(string $name, string $path): string
    {
        $cookie = sprintf(
            '%s=; Path=%s; Max-Age=0; Expires=Thu, 01 Jan 1970 00:00:00 GMT; HttpOnly; SameSite=Lax',
            $name,
            $path
        );
        return $this->config->secureCookies() ? $cookie . '; Secure' : $cookie;
    }

    private function json(ResponseInterface $response, array $data, int $status = 200): ResponseInterface
    {
        $response->getBody()->write(json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
        return $response
            ->withStatus($status)
            ->withHeader('Content-Type', 'application/json; charset=utf-8');
    }
}
