<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\RecetaRepository;
use App\Service\ImagenService;
use JsonException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class RecetaController
{
    public function __construct(
        private readonly RecetaRepository $repository
    ) {
    }

    /**
     * Comprueba que la API está operativa.
     */
    public function salud(
        ServerRequestInterface $request,
        ResponseInterface $response
    ): ResponseInterface {
        return $this->json(
            $response,
            ['estado' => 'ok']
        );
    }

    /**
     * Devuelve todas las recetas.
     */
    public function listar(
        ServerRequestInterface $request,
        ResponseInterface $response
    ): ResponseInterface {
        $recetas = $this->repository->listar();

        return $this->json(
            $response,
            $recetas
        );
    }

    public function obtener(
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args
    ): ResponseInterface {
        // Busca la receta utilizando el identificador recibido en la ruta.
        $id = (int) ($args['id'] ?? 0);

        if ($id <= 0) {
            return $this->json(
                $response,
                ['error' => 'Identificador de receta no válido'],
                400
            );
        }

        $receta = $this->repository->obtenerPorId($id);

        if ($receta === null) {
            return $this->json(
                $response,
                ['error' => 'Receta no encontrada'],
                404
            );
        }

        return $this->json(
            $response,
            $receta
        );
    }

    /**
     * Crea una receta a partir del JSON recibido.
     */
    public function crear(
        ServerRequestInterface $request,
        ResponseInterface $response
    ): ResponseInterface {
        try {
            $datos = json_decode(
                (string) $request->getBody(),
                true,
                flags: JSON_THROW_ON_ERROR
            );
        } catch (JsonException) {
            return $this->json(
                $response,
                ['error' => 'JSON no válido'],
                400
            );
        }

        if (
            !is_array($datos)
            || empty($datos['titulo'])
            || empty($datos['ingredientes'])
            || empty($datos['pasos'])
        ) {
            return $this->json(
                $response,
                ['error' => 'Datos de receta incompletos'],
                422
            );
        }

        $id = $this->repository->crear($datos);

        return $this->json(
            $response,
            ['id' => $id],
            201
        );
    }

    /**
     * Construye una respuesta JSON consistente para toda la API.
     */
    private function json(
        ResponseInterface $response,
        mixed $datos,
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

    /**
     * Recibe, normaliza y asigna una imagen a una receta.
     */
    public function actualizarImagen(
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
            // ImagenService valida, recorta y convierte la imagen a WebP.
            $imagenService = new ImagenService();
            $imagenUrl = $imagenService->guardar($contenido);

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
        } catch (\RuntimeException $exception) {
            return $this->json(
                $response,
                ['error' => $exception->getMessage()],
                422
            );
        }
    }
}