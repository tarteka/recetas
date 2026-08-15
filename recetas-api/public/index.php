<?php

declare(strict_types=1);

use App\Controller\RecetaController;
use App\Database;
use App\Repository\RecetaRepository;
use App\Middleware\ApiTokenMiddleware;
use Slim\Factory\AppFactory;

require __DIR__ . '/../vendor/autoload.php';

$app = AppFactory::create();

// Convierte errores de enrutado (como un 404) en respuestas HTTP controladas
// en lugar de dejar que Slim muestre una excepción fatal.
$app->addRoutingMiddleware();
$app->addErrorMiddleware(false, true, true);

$repository = new RecetaRepository(
    Database::conectar()
);

$controller = new RecetaController($repository);
$apiTokenMiddleware = new ApiTokenMiddleware();



/**
 * RUTAS
 */

// Comprueba que la API está operativa.
$app->get(
    '/salud',
    fn($request, $response) =>
    $controller->salud($request, $response)
);

// Devuelve todas las recetas.
$app->get(
    '/recetas',
    fn($request, $response) =>
    $controller->listar($request, $response)
);

// Actualiza la imagen de una receta mediante su identificador.
$app->post(
    '/recetas/{id:[0-9]+}/imagen',
    [$controller, 'actualizarImagen']
)->add($apiTokenMiddleware);


// Obtiene una receta completa mediante su identificador.
$app->get('/recetas/{id:[0-9]+}', fn(
    $request,
    $response,
    $args
) => $controller->obtener(
    $request,
    $response,
    $args
));

// Crea una nueva receta.
// Ruta protegida mediante el middleware de autenticación por token.
$app->post(
    '/recetas',
    fn($request, $response) =>
    $controller->crear($request, $response)
)->add($apiTokenMiddleware);



// Sirve las imágenes normalizadas almacenadas en el volumen persistente.
$app->get('/imagenes/{nombre:[a-f0-9]{32}\.webp}', function (
    $request,
    $response,
    $args
) {
    $ruta = '/datos/imagenes/' . $args['nombre'];

    if (!is_file($ruta)) {
        return $response->withStatus(404);
    }

    $contenido = file_get_contents($ruta);

    if ($contenido === false) {
        return $response->withStatus(500);
    }

    $response->getBody()->write($contenido);

    return $response
        ->withHeader('Content-Type', 'image/webp')
        ->withHeader('Cache-Control', 'public, max-age=31536000, immutable');
});

$app->run();
