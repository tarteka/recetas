<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\TaxonomiaRepository;
use JsonException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class TaxonomiaController
{
    public function __construct(private readonly TaxonomiaRepository $repository)
    {
    }

    public function listar(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $query = $request->getQueryParams();
        $pagina = max(1, (int) ($query['pagina'] ?? 1));
        $porPagina = min(50, max(1, (int) ($query['por_pagina'] ?? 15)));
        $buscar = trim((string) ($query['buscar'] ?? '')) ?: null;
        $orden = in_array(($query['ordenar'] ?? ''), ['id', 'nombre', 'total_recetas'], true) ? (string) $query['ordenar'] : 'nombre';
        $direccion = strtoupper((string) ($query['direccion'] ?? 'ASC')) === 'DESC' ? 'DESC' : 'ASC';
        return $this->json($response, $this->repository->listar($this->tipo($args), $pagina, $porPagina, $buscar, $orden, $direccion));
    }

    public function obtener(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $registro = $this->repository->obtener($this->tipo($args), (int) ($args['id'] ?? 0));
        return $registro === null ? $this->json($response, ['error' => 'Término no encontrado'], 404) : $this->json($response, $registro);
    }

    public function crear(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $datos = $this->datos($request);
        if ($datos instanceof ResponseInterface) return $datos;
        [$nombre, $slug] = $datos;
        $tipo = $this->tipo($args);
        if ($this->repository->existeDuplicado($tipo, $nombre, $slug)) {
            return $this->json($response, ['error' => 'Ya existe un término con ese nombre o slug'], 409);
        }
        return $this->json($response, $this->repository->crear($tipo, $nombre, $slug), 201);
    }

    public function actualizar(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $id = (int) ($args['id'] ?? 0);
        $datos = $this->datos($request);
        if ($datos instanceof ResponseInterface) return $datos;
        [$nombre, $slug] = $datos;
        $tipo = $this->tipo($args);
        if ($this->repository->obtener($tipo, $id) === null) return $this->json($response, ['error' => 'Término no encontrado'], 404);
        if ($this->repository->existeDuplicado($tipo, $nombre, $slug, $id)) {
            return $this->json($response, ['error' => 'Ya existe un término con ese nombre o slug'], 409);
        }
        return $this->json($response, $this->repository->actualizar($tipo, $id, $nombre, $slug));
    }

    public function eliminar(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $resultado = $this->repository->eliminar($this->tipo($args), (int) ($args['id'] ?? 0));
        if ($resultado === 'no_encontrada') return $this->json($response, ['error' => 'Término no encontrado'], 404);
        if ($resultado === 'en_uso') return $this->json($response, ['error' => 'No se puede eliminar porque está asociado a una o más recetas'], 409);
        return $response->withStatus(204);
    }

    private function datos(ServerRequestInterface $request): array|ResponseInterface
    {
        try {
            $datos = json_decode((string) $request->getBody(), true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return $this->json(new \Slim\Psr7\Response(), ['error' => 'JSON no válido'], 400);
        }
        $nombre = trim((string) ($datos['nombre'] ?? ''));
        if ($nombre === '' || mb_strlen($nombre) > 80) {
            return $this->json(new \Slim\Psr7\Response(), ['error' => 'El nombre es obligatorio y no puede superar 80 caracteres'], 422);
        }
        $slug = trim((string) ($datos['slug'] ?? ''));
        $slug = $slug === '' ? $this->slug($nombre) : $this->slug($slug);
        if ($slug === '') return $this->json(new \Slim\Psr7\Response(), ['error' => 'No se pudo generar un slug válido'], 422);
        return [$nombre, $slug];
    }

    private function slug(string $texto): string
    {
        $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $texto) ?: $texto;
        return trim((string) preg_replace('/[^a-z0-9]+/', '-', strtolower($ascii)), '-');
    }

    private function tipo(array $args): string
    {
        return ($args['tipo'] ?? '') === 'categorias' ? 'categorias' : 'etiquetas';
    }

    private function json(ResponseInterface $response, mixed $datos, int $status = 200): ResponseInterface
    {
        $response->getBody()->write(json_encode($datos, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
        return $response->withStatus($status)->withHeader('Content-Type', 'application/json; charset=utf-8');
    }
}
