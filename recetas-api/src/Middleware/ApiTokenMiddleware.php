<?php

declare(strict_types=1);

namespace App\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Response;

final class ApiTokenMiddleware
{
    public function __invoke(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler
    ): ResponseInterface {
        // Obtiene el secreto configurado en el entorno del contenedor.
        $tokenEsperado = getenv('RECETAS_API_TOKEN');

        if ($tokenEsperado === false || $tokenEsperado === '') {
            return $this->noAutorizado();
        }

        $authorization = $request->getHeaderLine('Authorization');

        if (!str_starts_with($authorization, 'Bearer ')) {
            return $this->noAutorizado();
        }

        $tokenRecibido = substr(
            $authorization,
            strlen('Bearer ')
        );

        // Compara los tokens evitando comparaciones vulnerables a timing attacks.
        if (!hash_equals($tokenEsperado, $tokenRecibido)) {
            return $this->noAutorizado();
        }

        return $handler->handle($request);
    }

    private function noAutorizado(): ResponseInterface
    {
        // Devuelve una respuesta uniforme sin revelar detalles del fallo.
        $response = new Response();

        $response->getBody()->write(
            json_encode(
                ['error' => 'No autorizado'],
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE
            )
        );

        return $response
            ->withStatus(401)
            ->withHeader(
                'Content-Type',
                'application/json; charset=utf-8'
            );
    }
}