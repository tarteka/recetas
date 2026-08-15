<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Service\AdminSessionService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Response;

final class AdminSessionMiddleware
{
    public function __construct(private readonly AdminSessionService $sessions)
    {
    }

    public function __invoke(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler
    ): ResponseInterface {
        $cookies = $request->getCookieParams();
        $token = isset($cookies[AdminSessionService::SESSION_COOKIE])
            ? (string) $cookies[AdminSessionService::SESSION_COOKIE]
            : '';
        $identity = $this->sessions->findIdentity($token);
        if ($identity === null) {
            $response = new Response(401);
            $response->getBody()->write('{"error":"No autorizado"}');
            return $response->withHeader('Content-Type', 'application/json; charset=utf-8');
        }
        return $handler->handle($request->withAttribute('admin_identity', $identity));
    }
}
