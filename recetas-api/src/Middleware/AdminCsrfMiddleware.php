<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Config\AdminAuthConfig;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Response;

final class AdminCsrfMiddleware
{
    public function __construct(private readonly AdminAuthConfig $config)
    {
    }

    public function __invoke(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler
    ): ResponseInterface {
        if (in_array(strtoupper($request->getMethod()), ['GET', 'HEAD', 'OPTIONS'], true)) {
            return $handler->handle($request);
        }

        $origin = rtrim($request->getHeaderLine('Origin'), '/');
        if ($origin === '' || !in_array(strtolower($origin), $this->config->allowedOrigins(), true)) {
            $response = new Response(403);
            $response->getBody()->write('{"error":"Origen no permitido"}');
            return $response->withHeader('Content-Type', 'application/json; charset=utf-8');
        }
        return $handler->handle($request);
    }
}
