<?php

declare(strict_types=1);

use App\Controller\TaxonomiaController;
use App\Middleware\AdminCsrfMiddleware;
use App\Middleware\AdminSessionMiddleware;
use Slim\App;

return static function (
    App $app,
    TaxonomiaController $taxonomiaController,
    AdminSessionMiddleware $adminSessionMiddleware,
    AdminCsrfMiddleware $adminCsrfMiddleware
): void {
    $app->get('/admin/{tipo:categorias|etiquetas}', [$taxonomiaController, 'listar'])
        ->add($adminSessionMiddleware);
    $app->post('/admin/{tipo:categorias|etiquetas}', [$taxonomiaController, 'crear'])
        ->add($adminCsrfMiddleware)
        ->add($adminSessionMiddleware);
    $app->get('/admin/{tipo:categorias|etiquetas}/{id:[0-9]+}', [$taxonomiaController, 'obtener'])
        ->add($adminSessionMiddleware);
    $app->put('/admin/{tipo:categorias|etiquetas}/{id:[0-9]+}', [$taxonomiaController, 'actualizar'])
        ->add($adminCsrfMiddleware)
        ->add($adminSessionMiddleware);
    $app->delete('/admin/{tipo:categorias|etiquetas}/{id:[0-9]+}', [$taxonomiaController, 'eliminar'])
        ->add($adminCsrfMiddleware)
        ->add($adminSessionMiddleware);
};
