<?php

declare(strict_types=1);

use App\Controller\AdminAuthController;
use App\Middleware\AdminCsrfMiddleware;
use App\Middleware\AdminSessionMiddleware;
use Slim\App;

return static function (
    App $app,
    AdminAuthController $adminAuthController,
    AdminSessionMiddleware $adminSessionMiddleware,
    AdminCsrfMiddleware $adminCsrfMiddleware
): void {
    $app->get('/admin/auth/google', [$adminAuthController, 'login']);
    $app->get('/admin/auth/google/callback', [$adminAuthController, 'callback']);
    $app->get('/admin/me', [$adminAuthController, 'me'])
        ->add($adminSessionMiddleware);

    $app->post('/admin/logout', [$adminAuthController, 'logout'])
        ->add($adminCsrfMiddleware)
        ->add($adminSessionMiddleware);
};
