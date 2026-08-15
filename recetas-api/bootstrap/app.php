<?php

declare(strict_types=1);

use App\Controller\ImagenController;
use App\Controller\RecetaController;
use App\Database;
use App\Middleware\ApiTokenMiddleware;
use App\Repository\RecetaRepository;
use App\Service\ImagenService;
use Slim\App;
use Slim\Factory\AppFactory;

require __DIR__ . '/../vendor/autoload.php';

$app = AppFactory::create();
$app->addRoutingMiddleware();
$app->addErrorMiddleware(false, true, true);

$repository = new RecetaRepository(Database::conectar());
$recetaController = new RecetaController($repository);
$imagenController = new ImagenController(
    $repository,
    new ImagenService()
);
$apiTokenMiddleware = new ApiTokenMiddleware();

/**
 * @var callable(
 *     App,
 *     RecetaController,
 *     ImagenController,
 *     ApiTokenMiddleware
 * ): void $registrarRutas
 */
$registrarRutas = require __DIR__ . '/../routes/api.php';
$registrarRutas(
    $app,
    $recetaController,
    $imagenController,
    $apiTokenMiddleware
);

return $app;
