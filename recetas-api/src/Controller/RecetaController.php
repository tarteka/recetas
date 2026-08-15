<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\RecetaRepository;
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
     * Devuelve una página de recetas aplicando búsqueda y filtros opcionales.
     */
    public function listar(
        ServerRequestInterface $request,
        ResponseInterface $response
    ): ResponseInterface {
        $query = $request->getQueryParams();
        $pagina = max(1, (int) ($query['pagina'] ?? 1));
        $porPagina = min(48, max(1, (int) ($query['por_pagina'] ?? 9)));
        $buscar = $this->parametroOpcional($query, 'buscar');
        $categoria = $this->parametroOpcional($query, 'categoria');
        $etiqueta = $this->parametroOpcional($query, 'etiqueta');

        return $this->json(
            $response,
            $this->repository->listar(
                $pagina,
                $porPagina,
                $buscar,
                $categoria,
                $etiqueta
            )
        );
    }

    public function listarCategorias(
        ServerRequestInterface $request,
        ResponseInterface $response
    ): ResponseInterface {
        return $this->json(
            $response,
            $this->repository->listarCategorias()
        );
    }

    public function listarEtiquetas(
        ServerRequestInterface $request,
        ResponseInterface $response
    ): ResponseInterface {
        return $this->json(
            $response,
            $this->repository->listarEtiquetas()
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
    /** Actualiza una receta completa desde el panel administrativo. */
    public function actualizar(
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args
    ): ResponseInterface {
        $id = (int) ($args['id'] ?? 0);

        if ($id <= 0) {
            return $this->json($response, ['error' => 'Identificador de receta no válido'], 400);
        }

        try {
            $datos = json_decode(
                (string) $request->getBody(),
                true,
                flags: JSON_THROW_ON_ERROR
            );
        } catch (JsonException) {
            return $this->json($response, ['error' => 'JSON no válido'], 400);
        }

        if (
            !is_array($datos)
            || trim((string) ($datos['titulo'] ?? '')) === ''
            || !is_array($datos['ingredientes'] ?? null)
            || $datos['ingredientes'] === []
            || !is_array($datos['pasos'] ?? null)
            || $datos['pasos'] === []
        ) {
            return $this->json($response, ['error' => 'Datos de receta incompletos'], 422);
        }

        if (!$this->repository->actualizar($id, $datos)) {
            return $this->json($response, ['error' => 'Receta no encontrada'], 404);
        }

        return $this->json(
            $response,
            $this->repository->obtenerPorId($id)
        );
    }

    private function parametroOpcional(array $query, string $nombre): ?string
    {
        $valor = trim((string) ($query[$nombre] ?? ''));

        return $valor === '' ? null : $valor;
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

}
