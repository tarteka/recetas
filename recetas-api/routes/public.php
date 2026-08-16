<?php

declare(strict_types=1);

use App\Controller\ImagenController;
use App\Controller\RecetaController;
use App\Middleware\ApiTokenMiddleware;
use Slim\App;

return static function (
    App $app,
    RecetaController $recetaController,
    ImagenController $imagenController,
    ApiTokenMiddleware $apiTokenMiddleware
): void {
    $app->get('/salud', [$recetaController, 'salud']);

    $app->get('/recetas', [$recetaController, 'listar']);
    $app->get('/recetas/{id:[0-9]+}', [$recetaController, 'obtener']);
    $app->post('/recetas', [$recetaController, 'crear'])
        ->add($apiTokenMiddleware);

    $app->get('/categorias', [$recetaController, 'listarCategorias']);
    $app->get('/etiquetas', [$recetaController, 'listarEtiquetas']);

    $app->post(
        '/recetas/{id:[0-9]+}/imagen',
        [$imagenController, 'actualizar']
    )->add($apiTokenMiddleware);

    $app->get(
        '/imagenes/{nombre:[a-f0-9]{32}\.webp}',
        [$imagenController, 'mostrar']
    );
};
