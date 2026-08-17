<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Auth\AdminIdentity;
use App\Auth\OidcClientInterface;
use App\Config\AdminAuthConfig;
use App\Controller\AdminAuthController;
use App\Controller\ImagenController;
use App\Controller\RecetaController;
use App\Controller\TaxonomiaController;
use App\Middleware\AdminCsrfMiddleware;
use App\Middleware\AdminSessionMiddleware;
use App\Middleware\ApiTokenMiddleware;
use App\Repository\AdminSessionRepository;
use App\Repository\RecetaRepository;
use App\Repository\TaxonomiaRepository;
use App\Service\AdminSessionService;
use App\Service\ImagenService;
use App\Service\RecetaValidator;
use PDO;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use RuntimeException;
use Slim\App;
use Slim\Factory\AppFactory;
use Slim\Psr7\Factory\ResponseFactory;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Psr7\Factory\StreamFactory;

/**
 * Construye la aplicación Slim real (mismas rutas y middleware que producción)
 * contra SQLite en memoria, para probar los flujos HTTP de extremo a extremo.
 */
abstract class SlimAppTestCase extends TestCase
{
    protected const API_TOKEN = 'token-http-pruebas';

    protected PDO $pdo;
    protected App $app;
    protected RecetaRepository $recetas;
    protected AdminSessionService $sesiones;
    protected string $directorioImagenes;

    private ServerRequestFactory $requests;
    private StreamFactory $streams;
    private ResponseFactory $responses;

    protected function setUp(): void
    {
        putenv('RECETAS_API_TOKEN=' . self::API_TOKEN);
        putenv('GOOGLE_CLIENT_ID=cliente-pruebas');
        putenv('GOOGLE_CLIENT_SECRET=secreto-google-pruebas');
        putenv('ADMIN_ALLOWED_EMAILS=semosa@gmail.com');
        putenv('ADMIN_SESSION_SECRET=0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef');
        putenv('ADMIN_GOOGLE_REDIRECT_URI=http://localhost/api/admin/auth/google/callback');
        putenv('ADMIN_ALLOWED_ORIGINS=http://localhost');

        $this->directorioImagenes = sys_get_temp_dir() . '/recetas-tests-' . bin2hex(random_bytes(8));
        self::assertTrue(mkdir($this->directorioImagenes, 0700), 'No se creó el directorio temporal de imágenes');

        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $pdo->exec('PRAGMA foreign_keys = ON');
        $schema = file_get_contents(__DIR__ . '/../../database/schema.sql');
        self::assertIsString($schema, 'No se pudo leer schema.sql');
        $pdo->exec($schema);
        $this->pdo = $pdo;

        $config = new AdminAuthConfig();
        $this->recetas = new RecetaRepository($pdo);
        $sesionesRepositorio = new AdminSessionRepository($pdo);
        $this->sesiones = new AdminSessionService($sesionesRepositorio, $config);

        $imagenService = new ImagenService($this->directorioImagenes);
        $oidc = new class implements OidcClientInterface {
            public function authorizationUrl(string $state, string $redirectUri): string
            {
                return 'https://accounts.example/authorize';
            }

            public function exchangeCode(string $code, string $redirectUri): AdminIdentity
            {
                throw new RuntimeException('OIDC no se usa en esta prueba');
            }
        };

        $recetaController = new RecetaController($this->recetas, new RecetaValidator());
        $taxonomiaController = new TaxonomiaController(new TaxonomiaRepository($pdo));
        $imagenController = new ImagenController($this->recetas, $imagenService, $this->directorioImagenes);
        $authController = new AdminAuthController($config, $oidc, $this->sesiones);
        $sessionMiddleware = new AdminSessionMiddleware($this->sesiones);
        $csrfMiddleware = new AdminCsrfMiddleware($config);
        $tokenMiddleware = new ApiTokenMiddleware();

        $this->app = AppFactory::create();
        $this->app->addRoutingMiddleware();
        $this->app->addErrorMiddleware(false, true, true);
        $registrarRutas = require __DIR__ . '/../../routes/api.php';
        $registrarRutas(
            $this->app,
            $recetaController,
            $taxonomiaController,
            $imagenController,
            $tokenMiddleware,
            $authController,
            $sessionMiddleware,
            $csrfMiddleware
        );

        $this->requests = new ServerRequestFactory();
        $this->streams = new StreamFactory();
        $this->responses = new ResponseFactory();
    }

    protected function tearDown(): void
    {
        if (is_dir($this->directorioImagenes)) {
            foreach (glob($this->directorioImagenes . '/*') ?: [] as $archivo) {
                if (is_file($archivo)) {
                    unlink($archivo);
                }
            }
            rmdir($this->directorioImagenes);
        }
    }

    /** Crea una sesión administrativa válida y devuelve su token de cookie. */
    protected function crearSesionAdmin(string $email = 'semosa@gmail.com'): string
    {
        return $this->sesiones->createSession(new AdminIdentity('http-admin', $email, 'Administrador', null));
    }

    protected function crearRequest(
        string $metodo,
        string $ruta,
        ?string $contenido = null,
        ?string $sesion = null,
        ?string $origen = 'http://localhost',
        ?string $bearerToken = null
    ): ServerRequestInterface {
        $request = $this->requests->createServerRequest($metodo, 'http://localhost' . $ruta);

        if ($sesion !== null) {
            $request = $request->withCookieParams([AdminSessionService::SESSION_COOKIE => $sesion]);
        }
        if ($origen !== null) {
            $request = $request->withHeader('Origin', $origen);
        }
        if ($bearerToken !== null) {
            $request = $request->withHeader('Authorization', 'Bearer ' . $bearerToken);
        }
        if ($contenido !== null) {
            $request = $request
                ->withHeader('Content-Type', 'application/json')
                ->withBody($this->streams->createStream($contenido));
        }

        return $request;
    }

    protected function crearRequestBinaria(
        string $metodo,
        string $ruta,
        string $contenido,
        string $contentType,
        ?string $sesion = null,
        ?string $origen = 'http://localhost',
        ?string $bearerToken = null
    ): ServerRequestInterface {
        $request = $this->crearRequest($metodo, $ruta, null, $sesion, $origen, $bearerToken);

        return $request
            ->withHeader('Content-Type', $contentType)
            ->withBody($this->streams->createStream($contenido));
    }

    protected function crearResponse(): ResponseInterface
    {
        return $this->responses->createResponse();
    }

    /** @return array<string, mixed> */
    protected function jsonRespuesta(ResponseInterface $respuesta): array
    {
        $datos = json_decode((string) $respuesta->getBody(), true, flags: JSON_THROW_ON_ERROR);
        self::assertIsArray($datos, 'La respuesta JSON no es un objeto o lista');

        return $datos;
    }
}
