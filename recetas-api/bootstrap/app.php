<?php

declare(strict_types=1);

use App\Auth\GoogleOidcClient;
use App\Config\AdminAuthConfig;
use App\Controller\AdminAuthController;
use App\Controller\ImagenController;
use App\Controller\RecetaController;
use App\Controller\TaxonomiaController;
use App\Database;
use App\Middleware\AdminCsrfMiddleware;
use App\Middleware\AdminSessionMiddleware;
use App\Middleware\ApiTokenMiddleware;
use App\Repository\AdminSessionRepository;
use App\Repository\RecetaRepository;
use App\Repository\TaxonomiaRepository;
use App\Service\AdminSessionService;
use App\Service\ImagenService;
use App\Service\RecetaValidator;
use Slim\App;
use Slim\Factory\AppFactory;

require __DIR__ . '/../vendor/autoload.php';

/** @var App<\Psr\Container\ContainerInterface|null> $app */
$app = AppFactory::create();
$app->addRoutingMiddleware();
$app->addErrorMiddleware(false, true, true);

$pdo = Database::conectar();
$repository = new RecetaRepository($pdo);
$recetaController = new RecetaController($repository, new RecetaValidator());
$taxonomiaController = new TaxonomiaController(new TaxonomiaRepository($pdo));
$imagenController = new ImagenController(
    $repository,
    new ImagenService()
);
$apiTokenMiddleware = new ApiTokenMiddleware();
$adminConfig = new AdminAuthConfig();
$adminSessions = new AdminSessionService(
    new AdminSessionRepository($pdo),
    $adminConfig
);
$adminAuthController = new AdminAuthController(
    $adminConfig,
    new GoogleOidcClient($adminConfig),
    $adminSessions
);
$adminSessionMiddleware = new AdminSessionMiddleware($adminSessions);
$adminCsrfMiddleware = new AdminCsrfMiddleware($adminConfig);

/**
 * @var callable(
 *     App<\Psr\Container\ContainerInterface|null>,
 *     RecetaController,
 *     TaxonomiaController,
 *     ImagenController,
 *     ApiTokenMiddleware,
 *     AdminAuthController,
 *     AdminSessionMiddleware,
 *     AdminCsrfMiddleware
 * ): void $registrarRutas
 */
$registrarRutas = require __DIR__ . '/../routes/api.php';
$registrarRutas(
    // Slim\App<TContainerInterface> es invariante: PHPStan no unifica dos instancias
    // de App<ContainerInterface|null> aunque sean el mismo tipo.
    // @phpstan-ignore argument.type
    $app,
    $recetaController,
    $taxonomiaController,
    $imagenController,
    $apiTokenMiddleware,
    $adminAuthController,
    $adminSessionMiddleware,
    $adminCsrfMiddleware
);

return $app;
