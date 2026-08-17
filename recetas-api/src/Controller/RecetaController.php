<?php

declare(strict_types=1);

namespace App\Controller;

use App\Model\RecetaDatos;
use App\Repository\RecetaRepository;
use App\Service\RecetaValidator;
use JsonException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class RecetaController
{
    public function __construct(
        private readonly RecetaRepository $repository,
        private readonly RecetaValidator $validator
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
                $etiqueta,
                'activas',
                'creado_en',
                'DESC'
            )
        );
    }

    public function listarAdmin(
        ServerRequestInterface $request,
        ResponseInterface $response
    ): ResponseInterface {
        $query = $request->getQueryParams();
        $pagina = max(1, (int) ($query['pagina'] ?? 1));
        $porPagina = min(48, max(1, (int) ($query['por_pagina'] ?? 10)));
        $estado = (string) ($query['estado'] ?? 'activas');
        if (!in_array($estado, ['activas', 'archivadas', 'todas'], true)) {
            $estado = 'activas';
        }
        $ordenar = (string) ($query['ordenar'] ?? 'creado_en');
        if (!in_array($ordenar, ['id', 'titulo', 'creado_en'], true)) {
            $ordenar = 'creado_en';
        }
        $direccion = strtoupper((string) ($query['direccion'] ?? 'DESC')) === 'ASC' ? 'ASC' : 'DESC';

        return $this->json(
            $response,
            $this->repository->listar(
                $pagina,
                $porPagina,
                $this->parametroOpcional($query, 'buscar'),
                $this->parametroOpcional($query, 'categoria'),
                $this->parametroOpcional($query, 'etiqueta'),
                $estado,
                $ordenar,
                $direccion
            )
        );
    }

    public function listarCategoriasAdmin(
        ServerRequestInterface $request,
        ResponseInterface $response
    ): ResponseInterface {
        return $this->json($response, $this->repository->listarCategoriasAdmin());
    }

    public function listarEtiquetasAdmin(
        ServerRequestInterface $request,
        ResponseInterface $response
    ): ResponseInterface {
        return $this->json($response, $this->repository->listarEtiquetasAdmin());
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

    /** @param array<string, string> $args */
    public function obtener(
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args
    ): ResponseInterface {
        // Busca la receta por slug y, si no existe, admite el id numérico
        // heredado para no romper enlaces antiguos.
        $identificador = trim((string) ($args['identificador'] ?? ''));

        if ($identificador === '') {
            return $this->json(
                $response,
                ['error' => 'Identificador de receta no válido'],
                400
            );
        }

        $receta = $this->repository->obtenerPorSlug($identificador);

        if ($receta === null && ctype_digit($identificador)) {
            $receta = $this->repository->obtenerPorId((int) $identificador);
        }

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

    /** @param array<string, string> $args */
    public function obtenerAdmin(
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args
    ): ResponseInterface {
        $id = (int) ($args['id'] ?? 0);
        if ($id <= 0) {
            return $this->json($response, ['error' => 'Identificador de receta no válido'], 400);
        }

        $receta = $this->repository->obtenerPorId($id, true);
        return $receta === null
            ? $this->json($response, ['error' => 'Receta no encontrada'], 404)
            : $this->json($response, $receta);
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

        if (!is_array($datos)) {
            return $this->json(
                $response,
                ['error' => 'El cuerpo de la receta debe ser un objeto JSON'],
                422
            );
        }

        $errores = $this->validator->validar($datos);
        if ($errores !== []) {
            return $this->json($response, ['error' => $errores[0], 'errores' => $errores], 422);
        }

        $id = $this->repository->crear(RecetaDatos::fromArray($datos));
        $creada = $this->repository->obtenerPorId($id, true);

        return $this->json(
            $response,
            ['id' => $id, 'slug' => $creada?->slug],
            201
        );
    }
    /**
     * Actualiza una receta completa desde el panel administrativo.
     *
     * @param array<string, string> $args
     */
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

        if (!is_array($datos)) {
            return $this->json($response, ['error' => 'El cuerpo de la receta debe ser un objeto JSON'], 422);
        }

        $errores = $this->validator->validar($datos);
        if ($errores !== []) {
            return $this->json($response, ['error' => $errores[0], 'errores' => $errores], 422);
        }

        if (!$this->repository->actualizar($id, RecetaDatos::fromArray($datos))) {
            return $this->json($response, ['error' => 'Receta no encontrada'], 404);
        }

        return $this->json(
            $response,
            $this->repository->obtenerPorId($id, true)
        );
    }

    /** @param array<string, string> $args */
    public function archivar(
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args
    ): ResponseInterface {
        return $this->cambiarArchivado($response, $args, true);
    }

    /** @param array<string, string> $args */
    public function restaurar(
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args
    ): ResponseInterface {
        return $this->cambiarArchivado($response, $args, false);
    }

    /** @param array<string, string> $args */
    private function cambiarArchivado(
        ResponseInterface $response,
        array $args,
        bool $archivar
    ): ResponseInterface {
        $id = (int) ($args['id'] ?? 0);
        if ($id <= 0) {
            return $this->json($response, ['error' => 'Identificador de receta no válido'], 400);
        }
        if (!$this->repository->cambiarArchivado($id, $archivar)) {
            return $this->json($response, ['error' => 'Receta no encontrada'], 404);
        }

        return $this->json($response, [
            'id' => $id,
            'archivada_en' => $archivar ? gmdate('Y-m-d H:i:s') : null,
        ]);
    }

    /** @param array<string, mixed> $query */
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
