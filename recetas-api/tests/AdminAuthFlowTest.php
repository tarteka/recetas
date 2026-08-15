<?php

declare(strict_types=1);

use App\Auth\AdminIdentity;
use App\Auth\GoogleOidcClient;
use App\Auth\OidcClientInterface;
use App\Config\AdminAuthConfig;
use App\Controller\AdminAuthController;
use App\Middleware\AdminCsrfMiddleware;
use App\Middleware\AdminSessionMiddleware;
use App\Repository\AdminSessionRepository;
use App\Service\AdminSessionService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Factory\ResponseFactory;
use Slim\Psr7\Factory\ServerRequestFactory;

require __DIR__ . '/../vendor/autoload.php';

function assertTrue(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function cookieValue(string $setCookie, string $name): string
{
    assertTrue(
        preg_match('/(?:^|, )' . preg_quote($name, '/') . '=([^;]*)/', $setCookie, $matches) === 1,
        'No se encontró la cookie ' . $name
    );
    return rawurldecode($matches[1]);
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

putenv('GOOGLE_CLIENT_ID=test-client.apps.googleusercontent.com');
putenv('GOOGLE_CLIENT_SECRET=test-secret');
putenv('ADMIN_ALLOWED_EMAILS=semosa@gmail.com');
putenv('ADMIN_SESSION_SECRET=0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef');
putenv('ADMIN_GOOGLE_REDIRECT_URI=http://localhost:5174/api/admin/auth/google/callback');
putenv('ADMIN_ALLOWED_ORIGINS=http://localhost:5174');

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
$schema = file_get_contents(__DIR__ . '/../database/schema.sql');
assertTrue(is_string($schema), 'No se pudo leer schema.sql');
$pdo->exec($schema);

$config = new AdminAuthConfig();
$repository = new AdminSessionRepository($pdo);
$sessions = new AdminSessionService($repository, $config);
$responses = new ResponseFactory();
$requests = new ServerRequestFactory();

// El cliente oficial genera Authorization Code Flow con los scopes mínimos.
$googleController = new AdminAuthController($config, new GoogleOidcClient($config), $sessions);
$login = $googleController->login(
    $requests->createServerRequest('GET', 'http://localhost:5174/api/admin/auth/google'),
    $responses->createResponse()
);
assertTrue($login->getStatusCode() === 302, 'El login debe redirigir');
$authorizationUrl = $login->getHeaderLine('Location');
$authorizationQuery = [];
parse_str((string) parse_url($authorizationUrl, PHP_URL_QUERY), $authorizationQuery);
assertTrue(parse_url($authorizationUrl, PHP_URL_HOST) === 'accounts.google.com', 'Host OAuth incorrecto');
assertTrue(($authorizationQuery['response_type'] ?? null) === 'code', 'No usa Authorization Code Flow');
$scopes = explode(' ', (string) ($authorizationQuery['scope'] ?? ''));
sort($scopes);
assertTrue($scopes === ['email', 'openid', 'profile'], 'Scopes OIDC incorrectos');

$state = cookieValue($login->getHeaderLine('Set-Cookie'), AdminSessionService::STATE_COOKIE);
assertTrue(hash_equals($state, (string) ($authorizationQuery['state'] ?? '')), 'State no asociado a cookie');

$fakeController = new AdminAuthController($config, new FakeOidcClient(), $sessions);

// Un state distinto se rechaza y no crea sesión.
$invalidState = $fakeController->callback(
    $requests->createServerRequest('GET', 'http://localhost/callback')
        ->withQueryParams(['state' => 'incorrecto', 'code' => 'authorized'])
        ->withCookieParams([AdminSessionService::STATE_COOKIE => $state]),
    $responses->createResponse()
);
assertTrue(str_contains($invalidState->getHeaderLine('Location'), 'invalid_state'), 'State inválido aceptado');

// El state válido sigue disponible y una identidad allowlisted crea sesión.
$authorized = $fakeController->callback(
    $requests->createServerRequest('GET', 'http://localhost/callback')
        ->withQueryParams(['state' => $state, 'code' => 'authorized'])
        ->withCookieParams([AdminSessionService::STATE_COOKIE => $state]),
    $responses->createResponse()
);
assertTrue($authorized->getStatusCode() === 302, 'Callback autorizado no redirige');
assertTrue($authorized->getHeaderLine('Location') === '/admin/', 'Destino tras login incorrecto');
$sessionCookieHeaders = $authorized->getHeader('Set-Cookie');
$sessionHeader = implode(', ', $sessionCookieHeaders);
$sessionToken = cookieValue($sessionHeader, AdminSessionService::SESSION_COOKIE);
assertTrue(str_contains($sessionHeader, 'HttpOnly'), 'Cookie sin HttpOnly');
assertTrue(str_contains($sessionHeader, 'SameSite=Lax'), 'Cookie sin SameSite=Lax');
assertTrue(!str_contains($sessionHeader, 'Secure'), 'Cookie local no debería ser Secure');
assertTrue(!str_contains($sessionHeader, 'test-secret'), 'Secreto expuesto en cookie');

// Con la URL pública, la cookie de sesión siempre lleva Secure.
putenv('ADMIN_GOOGLE_REDIRECT_URI=https://recetas.proyectozero.org/api/admin/auth/google/callback');
$secureLogin = $googleController->login(
    $requests->createServerRequest('GET', 'https://recetas.proyectozero.org/api/admin/auth/google'),
    $responses->createResponse()
);
$secureState = cookieValue($secureLogin->getHeaderLine('Set-Cookie'), AdminSessionService::STATE_COOKIE);
$secureCallback = $fakeController->callback(
    $requests->createServerRequest('GET', 'https://recetas.proyectozero.org/api/admin/auth/google/callback')
        ->withQueryParams(['state' => $secureState, 'code' => 'authorized'])
        ->withCookieParams([AdminSessionService::STATE_COOKIE => $secureState]),
    $responses->createResponse()
);
assertTrue(
    str_contains(implode(', ', $secureCallback->getHeader('Set-Cookie')), 'Secure'),
    'Cookie pública sin Secure'
);
putenv('ADMIN_GOOGLE_REDIRECT_URI=http://localhost:5174/api/admin/auth/google/callback');

// El middleware resuelve la sesión y /me devuelve la identidad mínima.
$sessionMiddleware = new AdminSessionMiddleware($sessions);
$meHandler = new class($fakeController, $responses) implements RequestHandlerInterface {
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
    $requests->createServerRequest('GET', 'http://localhost/admin/me')
        ->withCookieParams([AdminSessionService::SESSION_COOKIE => $sessionToken]),
    $meHandler
);
assertTrue($me->getStatusCode() === 200, '/admin/me no reconoce la sesión');
$identity = json_decode((string) $me->getBody(), true, flags: JSON_THROW_ON_ERROR);
assertTrue(($identity['email'] ?? null) === 'semosa@gmail.com', '/admin/me devolvió otro email');

// CSRF rechaza otros orígenes y acepta el origen configurado.
$csrf = new AdminCsrfMiddleware($config);
$okHandler = new class implements RequestHandlerInterface {
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        return new Slim\Psr7\Response(204);
    }
};
$forbidden = $csrf(
    $requests->createServerRequest('POST', 'http://localhost/admin/logout')
        ->withHeader('Origin', 'https://evil.example'),
    $okHandler
);
assertTrue($forbidden->getStatusCode() === 403, 'CSRF aceptó un origen externo');
$allowed = $csrf(
    $requests->createServerRequest('POST', 'http://localhost/admin/logout')
        ->withHeader('Origin', 'http://localhost:5174'),
    $okHandler
);
assertTrue($allowed->getStatusCode() === 204, 'CSRF rechazó el origen permitido');

// Una cuenta fuera de la allowlist nunca crea una sesión.
$secondLogin = $googleController->login(
    $requests->createServerRequest('GET', 'http://localhost/login'),
    $responses->createResponse()
);
$secondState = cookieValue($secondLogin->getHeaderLine('Set-Cookie'), AdminSessionService::STATE_COOKIE);
$unauthorized = $fakeController->callback(
    $requests->createServerRequest('GET', 'http://localhost/callback')
        ->withQueryParams(['state' => $secondState, 'code' => 'unauthorized'])
        ->withCookieParams([AdminSessionService::STATE_COOKIE => $secondState]),
    $responses->createResponse()
);
assertTrue(str_contains($unauthorized->getHeaderLine('Location'), 'access_denied'), 'Allowlist no aplicada');
assertTrue(!str_contains(implode(', ', $unauthorized->getHeader('Set-Cookie')), AdminSessionService::SESSION_COOKIE), 'Se creó sesión no autorizada');

// Logout revoca la sesión en SQLite y elimina la cookie.
$logout = $fakeController->logout(
    $requests->createServerRequest('POST', 'http://localhost/admin/logout')
        ->withCookieParams([AdminSessionService::SESSION_COOKIE => $sessionToken]),
    $responses->createResponse()
);
assertTrue($logout->getStatusCode() === 204, 'Logout incorrecto');
assertTrue(str_contains($logout->getHeaderLine('Set-Cookie'), 'Max-Age=0'), 'Logout no elimina cookie');
assertTrue($sessions->findIdentity($sessionToken) === null, 'Logout no revocó la sesión');

fwrite(STDOUT, "AdminAuthFlowTest: OK\n");
