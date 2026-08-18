<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Auth\AdminIdentity;
use App\Auth\GoogleOidcClient;
use App\Auth\OidcClientInterface;
use App\Config\AdminAuthConfig;
use App\Controller\AdminAuthController;
use App\Middleware\AdminCsrfMiddleware;
use App\Middleware\AdminSessionMiddleware;
use App\Repository\AdminSessionRepository;
use App\Service\AdminSessionService;
use PDO;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use RuntimeException;
use Slim\Psr7\Factory\ResponseFactory;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Psr7\Response;

final class AdminAuthFlowTest extends TestCase
{
    private AdminAuthConfig $config;
    private AdminSessionService $sesiones;
    private AdminAuthController $googleController;
    private AdminAuthController $fakeController;
    private ServerRequestFactory $requests;
    private ResponseFactory $responses;

    protected function setUp(): void
    {
        putenv('GOOGLE_CLIENT_ID=test-client.apps.googleusercontent.com');
        putenv('GOOGLE_CLIENT_SECRET=test-secret');
        putenv('ADMIN_ALLOWED_EMAILS=semosa@gmail.com');
        putenv('ADMIN_SESSION_SECRET=0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef');
        putenv('ADMIN_GOOGLE_REDIRECT_URI=http://localhost:5174/api/admin/auth/google/callback');
        putenv('ADMIN_ALLOWED_ORIGINS=http://localhost:5174');

        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $schema = file_get_contents(__DIR__ . '/../../database/schema.sql');
        self::assertIsString($schema, 'No se pudo leer schema.sql');
        $pdo->exec($schema);

        $this->config = new AdminAuthConfig();
        $sesionesRepositorio = new AdminSessionRepository($pdo);
        $this->sesiones = new AdminSessionService($sesionesRepositorio, $this->config);
        $this->googleController = new AdminAuthController($this->config, new GoogleOidcClient($this->config), $this->sesiones);
        $this->fakeController = new AdminAuthController($this->config, new FakeOidcClient(), $this->sesiones);
        $this->requests = new ServerRequestFactory();
        $this->responses = new ResponseFactory();
    }

    public function test_login_redirige_con_authorization_code_flow_y_scopes_minimos(): void
    {
        $login = $this->googleController->login(
            $this->requests->createServerRequest('GET', 'http://localhost:5174/api/admin/auth/google'),
            $this->responses->createResponse()
        );

        self::assertSame(302, $login->getStatusCode(), 'El login debe redirigir');
        $authorizationUrl = $login->getHeaderLine('Location');
        parse_str((string) parse_url($authorizationUrl, PHP_URL_QUERY), $query);
        self::assertSame('accounts.google.com', parse_url($authorizationUrl, PHP_URL_HOST), 'Host OAuth incorrecto');
        self::assertSame('code', $query['response_type'] ?? null, 'No usa Authorization Code Flow');

        $scopeQuery = $query['scope'] ?? '';
        $scopes = explode(' ', is_string($scopeQuery) ? $scopeQuery : '');
        sort($scopes);
        self::assertSame(['email', 'openid', 'profile'], $scopes, 'Scopes OIDC incorrectos');

        $state = $this->cookieValue($login->getHeaderLine('Set-Cookie'), AdminSessionService::STATE_COOKIE);
        $stateQuery = $query['state'] ?? '';
        self::assertTrue(hash_equals($state, is_string($stateQuery) ? $stateQuery : ''), 'State no asociado a cookie');
    }

    public function test_state_invalido_se_rechaza_y_no_crea_sesion(): void
    {
        $state = $this->iniciarLogin();

        $invalidState = $this->fakeController->callback(
            $this->requests->createServerRequest('GET', 'http://localhost/callback')
                ->withQueryParams(['state' => 'incorrecto', 'code' => 'authorized'])
                ->withCookieParams([AdminSessionService::STATE_COOKIE => $state]),
            $this->responses->createResponse()
        );

        self::assertStringContainsString('invalid_state', $invalidState->getHeaderLine('Location'), 'State inválido aceptado');
    }

    public function test_state_valido_con_identidad_autorizada_crea_sesion_httponly_lax(): void
    {
        $state = $this->iniciarLogin();

        $authorized = $this->fakeController->callback(
            $this->requests->createServerRequest('GET', 'http://localhost/callback')
                ->withQueryParams(['state' => $state, 'code' => 'authorized'])
                ->withCookieParams([AdminSessionService::STATE_COOKIE => $state]),
            $this->responses->createResponse()
        );

        self::assertSame(302, $authorized->getStatusCode(), 'Callback autorizado no redirige');
        self::assertSame('/admin/', $authorized->getHeaderLine('Location'), 'Destino tras login incorrecto');

        $sessionHeader = implode(', ', $authorized->getHeader('Set-Cookie'));
        self::assertStringContainsString('HttpOnly', $sessionHeader, 'Cookie sin HttpOnly');
        self::assertStringContainsString('SameSite=Lax', $sessionHeader, 'Cookie sin SameSite=Lax');
        self::assertStringNotContainsString('Secure', $sessionHeader, 'Cookie local no debería ser Secure');
        self::assertStringNotContainsString('test-secret', $sessionHeader, 'Secreto expuesto en cookie');
    }

    public function test_cookie_de_sesion_es_secure_con_url_publica(): void
    {
        putenv('ADMIN_GOOGLE_REDIRECT_URI=https://recetas.proyectozero.org/api/admin/auth/google/callback');
        $config = new AdminAuthConfig();
        $googleController = new AdminAuthController($config, new GoogleOidcClient($config), $this->sesiones);
        $fakeController = new AdminAuthController($config, new FakeOidcClient(), $this->sesiones);

        $login = $googleController->login(
            $this->requests->createServerRequest('GET', 'https://recetas.proyectozero.org/api/admin/auth/google'),
            $this->responses->createResponse()
        );
        $state = $this->cookieValue($login->getHeaderLine('Set-Cookie'), AdminSessionService::STATE_COOKIE);

        $callback = $fakeController->callback(
            $this->requests->createServerRequest('GET', 'https://recetas.proyectozero.org/api/admin/auth/google/callback')
                ->withQueryParams(['state' => $state, 'code' => 'authorized'])
                ->withCookieParams([AdminSessionService::STATE_COOKIE => $state]),
            $this->responses->createResponse()
        );

        self::assertStringContainsString(
            'Secure',
            implode(', ', $callback->getHeader('Set-Cookie')),
            'Cookie pública sin Secure'
        );

        putenv('ADMIN_GOOGLE_REDIRECT_URI=http://localhost:5174/api/admin/auth/google/callback');
    }

    public function test_middleware_resuelve_sesion_y_me_devuelve_identidad_minima(): void
    {
        $sessionToken = $this->autorizar();

        $sessionMiddleware = new AdminSessionMiddleware($this->sesiones);
        $controller = $this->fakeController;
        $responses = $this->responses;
        $meHandler = new class($controller, $responses) implements RequestHandlerInterface {
            public function __construct(
                private readonly AdminAuthController $controller,
                private readonly ResponseFactory $responses
            ) {
            }

            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                return $this->controller->me($request, $this->responses->createResponse());
            }
        };

        $me = $sessionMiddleware(
            $this->requests->createServerRequest('GET', 'http://localhost/admin/me')
                ->withCookieParams([AdminSessionService::SESSION_COOKIE => $sessionToken]),
            $meHandler
        );

        self::assertSame(200, $me->getStatusCode(), '/admin/me no reconoce la sesión');
        $identity = json_decode((string) $me->getBody(), true, flags: JSON_THROW_ON_ERROR);
        self::assertSame('semosa@gmail.com', $identity['email'] ?? null, '/admin/me devolvió otro email');
    }

    public function test_csrf_rechaza_origen_externo_y_acepta_origen_configurado(): void
    {
        $csrf = new AdminCsrfMiddleware($this->config);
        $okHandler = new class implements RequestHandlerInterface {
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                return new Response(204);
            }
        };

        $forbidden = $csrf(
            $this->requests->createServerRequest('POST', 'http://localhost/admin/logout')
                ->withHeader('Origin', 'https://evil.example'),
            $okHandler
        );
        self::assertSame(403, $forbidden->getStatusCode(), 'CSRF aceptó un origen externo');

        $allowed = $csrf(
            $this->requests->createServerRequest('POST', 'http://localhost/admin/logout')
                ->withHeader('Origin', 'http://localhost:5174'),
            $okHandler
        );
        self::assertSame(204, $allowed->getStatusCode(), 'CSRF rechazó el origen permitido');
    }

    public function test_cuenta_fuera_de_allowlist_nunca_crea_sesion(): void
    {
        $login = $this->googleController->login(
            $this->requests->createServerRequest('GET', 'http://localhost/login'),
            $this->responses->createResponse()
        );
        $state = $this->cookieValue($login->getHeaderLine('Set-Cookie'), AdminSessionService::STATE_COOKIE);

        $unauthorized = $this->fakeController->callback(
            $this->requests->createServerRequest('GET', 'http://localhost/callback')
                ->withQueryParams(['state' => $state, 'code' => 'unauthorized'])
                ->withCookieParams([AdminSessionService::STATE_COOKIE => $state]),
            $this->responses->createResponse()
        );

        self::assertStringContainsString('access_denied', $unauthorized->getHeaderLine('Location'), 'Allowlist no aplicada');
        self::assertStringNotContainsString(
            AdminSessionService::SESSION_COOKIE,
            implode(', ', $unauthorized->getHeader('Set-Cookie')),
            'Se creó sesión no autorizada'
        );
    }

    public function test_logout_revoca_sesion_en_sqlite_y_elimina_cookie(): void
    {
        $sessionToken = $this->autorizar();

        $logout = $this->fakeController->logout(
            $this->requests->createServerRequest('POST', 'http://localhost/admin/logout')
                ->withCookieParams([AdminSessionService::SESSION_COOKIE => $sessionToken]),
            $this->responses->createResponse()
        );

        self::assertSame(204, $logout->getStatusCode(), 'Logout incorrecto');
        self::assertStringContainsString('Max-Age=0', $logout->getHeaderLine('Set-Cookie'), 'Logout no elimina cookie');
        self::assertNull($this->sesiones->findIdentity($sessionToken), 'Logout no revocó la sesión');
    }

    private function iniciarLogin(): string
    {
        $login = $this->googleController->login(
            $this->requests->createServerRequest('GET', 'http://localhost:5174/api/admin/auth/google'),
            $this->responses->createResponse()
        );

        return $this->cookieValue($login->getHeaderLine('Set-Cookie'), AdminSessionService::STATE_COOKIE);
    }

    private function autorizar(): string
    {
        $state = $this->iniciarLogin();

        $authorized = $this->fakeController->callback(
            $this->requests->createServerRequest('GET', 'http://localhost/callback')
                ->withQueryParams(['state' => $state, 'code' => 'authorized'])
                ->withCookieParams([AdminSessionService::STATE_COOKIE => $state]),
            $this->responses->createResponse()
        );

        return $this->cookieValue(
            implode(', ', $authorized->getHeader('Set-Cookie')),
            AdminSessionService::SESSION_COOKIE
        );
    }

    private function cookieValue(string $setCookie, string $name): string
    {
        self::assertSame(
            1,
            preg_match('/(?:^|, )' . preg_quote($name, '/') . '=([^;]*)/', $setCookie, $matches),
            'No se encontró la cookie ' . $name
        );

        return rawurldecode($matches[1]);
    }
}

final class FakeOidcClient implements OidcClientInterface
{
    public function authorizationUrl(string $state, string $redirectUri): string
    {
        return 'https://accounts.google.test/auth?' . http_build_query([
            'state' => $state,
            'redirect_uri' => $redirectUri,
        ]);
    }

    public function exchangeCode(string $code, string $redirectUri): AdminIdentity
    {
        return match ($code) {
            'authorized' => new AdminIdentity('google-sub-1', 'semosa@gmail.com', 'Sergio', null),
            'unauthorized' => new AdminIdentity('google-sub-2', 'otro@gmail.com', 'Otra persona', null),
            default => throw new RuntimeException('Código de prueba no válido'),
        };
    }
}
