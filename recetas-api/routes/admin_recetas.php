<?php

declare(strict_types=1);

use App\Controller\ImagenController;
use App\Controller\RecetaController;
use App\Middleware\AdminCsrfMiddleware;
use App\Middleware\AdminSessionMiddleware;
use Slim\App;

return static function (
    App $app,
    RecetaController $recetaController,
    ImagenController $imagenController,
    AdminSessionMiddleware $adminSessionMiddleware,
    AdminCsrfMiddleware $adminCsrfMiddleware
): void {
    $app->get('/admin/recetas', [$recetaController, 'listarAdmin'])
        ->add($adminSessionMiddleware);
    $app->post('/admin/recetas', [$recetaController, 'crear'])
        ->add($adminCsrfMiddleware)
        ->add($adminSessionMiddleware);
    $app->get('/admin/recetas/{id:[0-9]+}', [$recetaController, 'obtenerAdmin'])
        ->add($adminSessionMiddleware);
    $app->put('/admin/recetas/{id:[0-9]+}', [$recetaController, 'actualizar'])
        ->add($adminCsrfMiddleware)
        ->add($adminSessionMiddleware);
    $app->post('/admin/recetas/{id:[0-9]+}/imagen', [$imagenController, 'actualizar'])
        ->add($adminCsrfMiddleware)
        ->add($adminSessionMiddleware);
    $app->delete('/admin/recetas/{id:[0-9]+}/imagen', [$imagenController, 'eliminar'])
        ->add($adminCsrfMiddleware)
        ->add($adminSessionMiddleware);
    $app->delete('/admin/recetas/{id:[0-9]+}', [$recetaController, 'archivar'])
        ->add($adminCsrfMiddleware)
        ->add($adminSessionMiddleware);
    $app->post('/admin/recetas/{id:[0-9]+}/restaurar', [$recetaController, 'restaurar'])
        ->add($adminCsrfMiddleware)
        ->add($adminSessionMiddleware);
    $app->delete('/admin/recetas/{id:[0-9]+}/definitiva', [$imagenController, 'eliminarReceta'])
        ->add($adminCsrfMiddleware)
        ->add($adminSessionMiddleware);
};
