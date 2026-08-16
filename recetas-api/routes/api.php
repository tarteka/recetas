<?php

declare(strict_types=1);

use App\Controller\AdminAuthController;
use App\Controller\ImagenController;
use App\Controller\RecetaController;
use App\Controller\TaxonomiaController;
use App\Middleware\AdminCsrfMiddleware;
use App\Middleware\AdminSessionMiddleware;
use App\Middleware\ApiTokenMiddleware;
use Slim\App;

return static function (
    App $app,
    RecetaController $recetaController,
    TaxonomiaController $taxonomiaController,
    ImagenController $imagenController,
    ApiTokenMiddleware $apiTokenMiddleware,
    AdminAuthController $adminAuthController,
    AdminSessionMiddleware $adminSessionMiddleware,
    AdminCsrfMiddleware $adminCsrfMiddleware
): void {
    (require __DIR__ . '/public.php')(
        $app,
        $recetaController,
        $imagenController,
        $apiTokenMiddleware
    );

    (require __DIR__ . '/admin_auth.php')(
        $app,
        $adminAuthController,
        $adminSessionMiddleware,
        $adminCsrfMiddleware
    );

    (require __DIR__ . '/admin_taxonomia.php')(
        $app,
        $taxonomiaController,
        $adminSessionMiddleware,
        $adminCsrfMiddleware
    );

    (require __DIR__ . '/admin_recetas.php')(
        $app,
        $recetaController,
        $imagenController,
        $adminSessionMiddleware,
        $adminCsrfMiddleware
    );
};
