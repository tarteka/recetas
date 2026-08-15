<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\RecetaRepository;
use App\Service\ImagenService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use RuntimeException;

final class ImagenController
{
    public function __construct(
        private readonly RecetaRepository $repository,
        private readonly ImagenService $imagenService,
        private readonly string $directorioImagenes = '/datos/imagenes'
    ) {
    }

    public function actualizar(
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args
    ): ResponseInterface {
        $id = filter_var(
            $args['id'] ?? null,
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]]
        );

        if ($id === false) {
            return $this->json(
                $response,
                ['error' => 'Identificador de receta no válido'],
                400
            );
        }

        $contenido = (string) $request->getBody();

        if ($contenido === '') {
            return $this->json(
                $response,
                ['error' => 'No se ha recibido ninguna imagen'],
                422
            );
        }

        try {
            $imagenUrl = $this->imagenService->guardar($contenido);
            $actualizada = $this->repository->actualizarImagen(
                $id,
                $imagenUrl
            );

            if (!$actualizada) {
                return $this->json(
                    $response,
                    ['error' => 'Receta no encontrada'],
                    404
                );
            }

            return $this->json(
                $response,
                ['imagen_url' => $imagenUrl]
            );
        } catch (RuntimeException $exception) {
            return $this->json(
                $response,
                ['error' => $exception->getMessage()],
                422
            );
        }
    }

    public function mostrar(
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args
    ): ResponseInterface {
        $nombre = (string) ($args['nombre'] ?? '');
        $ruta = $this->directorioImagenes . '/' . $nombre;

        if (!is_file($ruta)) {
            return $response->withStatus(404);
        }

        $contenido = file_get_contents($ruta);

        if ($contenido === false) {
            return $response->withStatus(500);
        }

        $response->getBody()->write($contenido);

        return $response
            ->withHeader('Content-Type', 'image/webp');
    }

    private function json(
        ResponseInterface $response,
        array $datos,
        int $status = 200
    ): ResponseInterface {
        $response->getBody()->write(
            json_encode(
                $datos,
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE
            )
        );

        return $response
            ->withStatus($status)
            ->withHeader(
                'Content-Type',
                'application/json; charset=utf-8'
            );
    }
}
