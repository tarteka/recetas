<?php

declare(strict_types=1);

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
use Psr\Http\Message\ResponseInterface;
use Slim\Factory\AppFactory;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Psr7\Factory\StreamFactory;

require __DIR__ . '/../vendor/autoload.php';

function comprobarHttp(bool $condicion, string $mensaje): void
{
    if (!$condicion) {
        throw new RuntimeException($mensaje);
    }
}

function jsonRespuesta(ResponseInterface $respuesta): array
{
    $datos = json_decode((string) $respuesta->getBody(), true, flags: JSON_THROW_ON_ERROR);
    comprobarHttp(is_array($datos), 'La respuesta JSON no es un objeto o lista');
    return $datos;
}

putenv('RECETAS_API_TOKEN=token-http-pruebas');
putenv('GOOGLE_CLIENT_ID=cliente-pruebas');
putenv('GOOGLE_CLIENT_SECRET=secreto-google-pruebas');
putenv('ADMIN_ALLOWED_EMAILS=semosa@gmail.com');
putenv('ADMIN_SESSION_SECRET=0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef');
putenv('ADMIN_GOOGLE_REDIRECT_URI=http://localhost/api/admin/auth/google/callback');
putenv('ADMIN_ALLOWED_ORIGINS=http://localhost');

$directorioImagenes = sys_get_temp_dir() . '/recetas-http-' . bin2hex(random_bytes(8));
comprobarHttp(mkdir($directorioImagenes, 0700), 'No se creó el directorio temporal');

try {
    $pdo = new PDO('sqlite::memory:');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->exec('PRAGMA foreign_keys = ON');
    $schema = file_get_contents(__DIR__ . '/../database/schema.sql');
    comprobarHttp(is_string($schema), 'No se pudo leer schema.sql');
    $pdo->exec($schema);

    $config = new AdminAuthConfig();
    $recetas = new RecetaRepository($pdo);
    $sesionesRepositorio = new AdminSessionRepository($pdo);
    $sesiones = new AdminSessionService($sesionesRepositorio, $config);
    $identidad = new AdminIdentity('http-admin', 'semosa@gmail.com', 'Administrador', null);
    $tokenSesion = $sesiones->createSession($identidad);
    $imagenService = new ImagenService($directorioImagenes);

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

    $recetaController = new RecetaController($recetas, new RecetaValidator());
    $taxonomiaController = new TaxonomiaController(new TaxonomiaRepository($pdo));
    $imagenController = new ImagenController($recetas, $imagenService, $directorioImagenes);
    $authController = new AdminAuthController($config, $oidc, $sesiones);
    $sessionMiddleware = new AdminSessionMiddleware($sesiones);
    $csrfMiddleware = new AdminCsrfMiddleware($config);
    $tokenMiddleware = new ApiTokenMiddleware();

    $app = AppFactory::create();
    $app->addRoutingMiddleware();
    $app->addErrorMiddleware(false, true, true);
    $registrarRutas = require __DIR__ . '/../routes/api.php';
    $registrarRutas(
        $app,
        $recetaController,
        $taxonomiaController,
        $imagenController,
        $tokenMiddleware,
        $authController,
        $sessionMiddleware,
        $csrfMiddleware
    );

    $requests = new ServerRequestFactory();
    $streams = new StreamFactory();
    $crearRequest = static function (
        string $metodo,
        string $ruta,
        ?string $contenido = null,
        bool $sesion = true,
        ?string $origen = 'http://localhost'
    ) use ($requests, $streams, $tokenSesion) {
        $request = $requests->createServerRequest($metodo, 'http://localhost' . $ruta);
        if ($sesion) {
            $request = $request->withCookieParams([AdminSessionService::SESSION_COOKIE => $tokenSesion]);
        }
        if ($origen !== null) {
            $request = $request->withHeader('Origin', $origen);
        }
        if ($contenido !== null) {
            $request = $request
                ->withHeader('Content-Type', 'application/json')
                ->withBody($streams->createStream($contenido));
        }
        return $request;
    };

    // Autenticación y CSRF atraviesan la pila real de Slim.
    $sinSesion = $app->handle($crearRequest('GET', '/admin/recetas', null, false, null));
    comprobarHttp($sinSesion->getStatusCode() === 401, 'El listado admin aceptó una petición sin sesión');
    $sinOrigen = $app->handle($crearRequest('POST', '/admin/recetas', '{}', true, null));
    comprobarHttp($sinOrigen->getStatusCode() === 403, 'Una escritura sin Origin superó CSRF');
    $origenExterno = $app->handle($crearRequest('POST', '/admin/recetas', '{}', true, 'https://evil.example'));
    comprobarHttp($origenExterno->getStatusCode() === 403, 'Una escritura con origen externo superó CSRF');

    // CRUD administrativo de taxonomías, incluidos duplicados y términos libres.
    $categoriaCreada = $app->handle($crearRequest('POST', '/admin/categorias', json_encode(['nombre' => 'Categoría temporal'], JSON_THROW_ON_ERROR)));
    comprobarHttp($categoriaCreada->getStatusCode() === 201, 'No se creó una categoría administrativa');
    $categoriaId = (int) (jsonRespuesta($categoriaCreada)['id'] ?? 0);
    comprobarHttp($categoriaId > 0, 'La categoría creada no devolvió ID');
    comprobarHttp($app->handle($crearRequest('POST', '/admin/categorias', json_encode(['nombre' => 'Categoría temporal'], JSON_THROW_ON_ERROR)))->getStatusCode() === 409, 'Se permitió una categoría duplicada');
    $categoriaActualizada = $app->handle($crearRequest('PUT', '/admin/categorias/' . $categoriaId, json_encode(['nombre' => 'Categoría renombrada'], JSON_THROW_ON_ERROR)));
    comprobarHttp($categoriaActualizada->getStatusCode() === 200, 'No se actualizó la categoría');
    comprobarHttp((jsonRespuesta($categoriaActualizada)['slug'] ?? null) === 'categoria-renombrada', 'No se regeneró el slug de la categoría');
    $listadoCategorias = jsonRespuesta($app->handle($crearRequest('GET', '/admin/categorias')));
    comprobarHttp(($listadoCategorias['paginacion']['total'] ?? 0) === 1, 'El listado administrativo no incluye categorías sin recetas');
    comprobarHttp($app->handle($crearRequest('DELETE', '/admin/categorias/' . $categoriaId))->getStatusCode() === 204, 'No se eliminó una categoría sin uso');

    $etiquetaCreada = $app->handle($crearRequest('POST', '/admin/etiquetas', json_encode(['nombre' => 'Etiqueta temporal'], JSON_THROW_ON_ERROR)));
    comprobarHttp($etiquetaCreada->getStatusCode() === 201, 'No se creó una etiqueta administrativa');
    $etiquetaId = (int) (jsonRespuesta($etiquetaCreada)['id'] ?? 0);
    comprobarHttp($app->handle($crearRequest('DELETE', '/admin/etiquetas/' . $etiquetaId))->getStatusCode() === 204, 'No se eliminó una etiqueta sin uso');

    // La validación devuelve 422 y no filtra datos internos.
    $invalida = $app->handle($crearRequest('POST', '/admin/recetas', json_encode([
        'titulo' => ' ', 'ingredientes' => [], 'pasos' => [],
    ], JSON_THROW_ON_ERROR)));
    comprobarHttp($invalida->getStatusCode() === 422, 'La API aceptó una receta inválida');
    $cuerpoInvalido = (string) $invalida->getBody();
    comprobarHttp(!str_contains($cuerpoInvalido, '/var/www') && !str_contains($cuerpoInvalido, 'SQLSTATE') && !str_contains($cuerpoInvalido, 'Stack trace'), 'La validación expuso datos internos');

    // Ciclo completo de una receta temporal.
    $datos = [
        'titulo' => 'Receta HTTP temporal',
        'descripcion' => 'Creada exclusivamente en memoria.',
        'raciones' => 2,
        'tiempo_preparacion_min' => 10,
        'tiempo_coccion_min' => 20,
        'tiempo_total_min' => 30,
        'fuente_url' => 'https://example.com/receta',
        'ingredientes' => [['nombre' => 'Patata', 'cantidad' => 2, 'unidad' => 'unidades', 'texto_original' => '2 patatas']],
        'pasos' => [['numero' => 1, 'instruccion' => 'Cocer las patatas.']],
        'categorias' => [['nombre' => 'Pruebas']],
        'etiquetas' => [['nombre' => 'Temporal']],
    ];
    $creada = $app->handle($crearRequest('POST', '/admin/recetas', json_encode($datos, JSON_THROW_ON_ERROR)));
    comprobarHttp($creada->getStatusCode() === 201, 'No se creó la receta HTTP');
    $id = (int) (jsonRespuesta($creada)['id'] ?? 0);
    comprobarHttp($id > 0, 'La creación no devolvió un ID');
    $slug = (string) (jsonRespuesta($creada)['slug'] ?? '');
    comprobarHttp($slug === 'receta-http-temporal', 'La creación no devolvió el slug derivado del título');

    $categoriaEnUsoId = (int) $pdo->query("SELECT id FROM categorias WHERE nombre = 'Pruebas'")->fetchColumn();
    comprobarHttp($app->handle($crearRequest('DELETE', '/admin/categorias/' . $categoriaEnUsoId))->getStatusCode() === 409, 'Se eliminó una categoría asociada a una receta');

    $publica = $app->handle($crearRequest('GET', '/recetas/' . $id, null, false, null));
    comprobarHttp($publica->getStatusCode() === 200, 'La receta creada no es pública por id');
    $publicaPorSlug = $app->handle($crearRequest('GET', '/recetas/' . $slug, null, false, null));
    comprobarHttp($publicaPorSlug->getStatusCode() === 200, 'La receta creada no es pública por slug');
    comprobarHttp((jsonRespuesta($publicaPorSlug)['id'] ?? null) === $id, 'La búsqueda por slug devolvió una receta distinta');

    $datos['titulo'] = 'Receta HTTP actualizada';
    $actualizada = $app->handle($crearRequest('PUT', '/admin/recetas/' . $id, json_encode($datos, JSON_THROW_ON_ERROR)));
    comprobarHttp($actualizada->getStatusCode() === 200, 'No se actualizó la receta HTTP');
    comprobarHttp((jsonRespuesta($actualizada)['titulo'] ?? null) === 'Receta HTTP actualizada', 'La actualización no persistió');

    // Subida inválida y normalización real a WebP.
    $imagenInvalida = $crearRequest('POST', '/admin/recetas/' . $id . '/imagen', null)
        ->withHeader('Content-Type', 'image/jpeg')
        ->withBody($streams->createStream('no-es-una-imagen'));
    comprobarHttp($app->handle($imagenInvalida)->getStatusCode() === 422, 'Aceptó contenido que no era imagen');

    $origen = imagecreatetruecolor(320, 240);
    comprobarHttp($origen instanceof GdImage, 'No se pudo crear la imagen de prueba');
    imagefill($origen, 0, 0, imagecolorallocate($origen, 90, 130, 80));
    ob_start();
    imagejpeg($origen, null, 85);
    $jpeg = ob_get_clean();
    comprobarHttp(is_string($jpeg), 'No se generó el JPEG de prueba');
    $subidaRequest = $crearRequest('POST', '/admin/recetas/' . $id . '/imagen', null)
        ->withHeader('Content-Type', 'image/jpeg')
        ->withBody($streams->createStream($jpeg));
    $subida = $app->handle($subidaRequest);
    comprobarHttp($subida->getStatusCode() === 200, 'No se subió la imagen válida');
    $imagenUrl = (string) (jsonRespuesta($subida)['imagen_url'] ?? '');
    $imagenRuta = $directorioImagenes . '/' . basename($imagenUrl);
    $imagenInfo = getimagesize($imagenRuta);
    comprobarHttp(($imagenInfo[0] ?? 0) === 1200 && ($imagenInfo[1] ?? 0) === 800, 'La imagen no se normalizó a 1200x800');
    comprobarHttp(($imagenInfo['mime'] ?? '') === 'image/webp', 'La imagen no se convirtió a WebP');

    $archivada = $app->handle($crearRequest('DELETE', '/admin/recetas/' . $id));
    comprobarHttp($archivada->getStatusCode() === 200, 'No se archivó la receta');
    comprobarHttp($app->handle($crearRequest('GET', '/recetas/' . $id, null, false, null))->getStatusCode() === 404, 'La receta archivada sigue siendo pública');
    comprobarHttp($app->handle($crearRequest('GET', '/admin/recetas/' . $id))->getStatusCode() === 200, 'Administración no puede consultar la receta archivada');

    $restaurada = $app->handle($crearRequest('POST', '/admin/recetas/' . $id . '/restaurar'));
    comprobarHttp($restaurada->getStatusCode() === 200, 'No se restauró la receta');
    comprobarHttp($app->handle($crearRequest('GET', '/recetas/' . $id, null, false, null))->getStatusCode() === 200, 'La receta restaurada no volvió a ser pública');

    // Una receta activa nunca se elimina definitivamente.
    comprobarHttp($app->handle($crearRequest('DELETE', '/admin/recetas/' . $id . '/definitiva'))->getStatusCode() === 409, 'Se eliminó definitivamente una receta activa');
    $app->handle($crearRequest('DELETE', '/admin/recetas/' . $id));
    $eliminada = $app->handle($crearRequest('DELETE', '/admin/recetas/' . $id . '/definitiva'));
    comprobarHttp($eliminada->getStatusCode() === 200, 'No se eliminó definitivamente la receta archivada');
    comprobarHttp($recetas->obtenerPorId($id, true) === null, 'La receta eliminada sigue en SQLite');
    comprobarHttp(!is_file($imagenRuta), 'La imagen de la receta eliminada quedó huérfana');
    $relaciones = (int) $pdo->query('SELECT COUNT(*) FROM receta_ingredientes WHERE receta_id = ' . $id)->fetchColumn();
    comprobarHttp($relaciones === 0, 'Las relaciones no se eliminaron en cascada');

    // Sesión caducada y logout real.
    $tokenCaducado = 'sesion-caducada';
    $sesionesRepositorio->createSession(
        hash_hmac('sha256', $tokenCaducado, $config->sessionSecret()),
        $identidad,
        time() - 100,
        time() - 10
    );
    $requestCaducada = $requests->createServerRequest('GET', 'http://localhost/admin/recetas')
        ->withCookieParams([AdminSessionService::SESSION_COOKIE => $tokenCaducado]);
    comprobarHttp($app->handle($requestCaducada)->getStatusCode() === 401, 'Una sesión caducada sigue siendo válida');

    $logout = $app->handle($crearRequest('POST', '/admin/logout'));
    comprobarHttp($logout->getStatusCode() === 204, 'El logout HTTP falló');
    comprobarHttp($app->handle($crearRequest('GET', '/admin/recetas'))->getStatusCode() === 401, 'La sesión sigue activa tras logout');

    fwrite(STDOUT, "AdminHttpFlowTest: OK\n");
} finally {
    if (is_dir($directorioImagenes)) {
        foreach (glob($directorioImagenes . '/*') ?: [] as $archivo) {
            if (is_file($archivo)) {
                unlink($archivo);
            }
        }
        rmdir($directorioImagenes);
    }
}
