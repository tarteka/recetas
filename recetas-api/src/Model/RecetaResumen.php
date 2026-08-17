<?php

declare(strict_types=1);

namespace App\Model;

use JsonSerializable;

/**
 * Fila de listado de recetas (pública o administrativa: mismo método de
 * repositorio, solo cambia el filtro por estado) — sin ingredientes ni
 * pasos, más ligera que el detalle. Para el detalle completo ver Receta;
 * para lo que se recibe al crear/actualizar (lado de escritura) ver
 * RecetaDatos.
 */
final class RecetaResumen implements JsonSerializable
{
    /**
     * @param list<Categoria> $categorias
     * @param list<Etiqueta> $etiquetas
     */
    public function __construct(
        public readonly int $id,
        public readonly string $titulo,
        public readonly ?string $slug,
        public readonly ?string $descripcion,
        public readonly ?string $imagenUrl,
        public readonly ?string $fuenteNombre,
        public readonly ?int $raciones,
        public readonly ?int $tiempoTotalMin,
        public readonly string $creadoEn,
        public readonly ?string $archivadaEn,
        public readonly array $categorias,
        public readonly array $etiquetas,
    ) {
    }

    /**
     * @param array<string, mixed> $fila
     * @param list<Categoria> $categorias
     * @param list<Etiqueta> $etiquetas
     */
    public static function desdeFila(array $fila, array $categorias, array $etiquetas): self
    {
        return new self(
            id: (int) $fila['id'],
            titulo: (string) $fila['titulo'],
            slug: $fila['slug'] === null ? null : (string) $fila['slug'],
            descripcion: $fila['descripcion'] === null ? null : (string) $fila['descripcion'],
            imagenUrl: $fila['imagen_url'] === null ? null : (string) $fila['imagen_url'],
            fuenteNombre: $fila['fuente_nombre'] === null ? null : (string) $fila['fuente_nombre'],
            raciones: $fila['raciones'] === null ? null : (int) $fila['raciones'],
            tiempoTotalMin: $fila['tiempo_total_min'] === null ? null : (int) $fila['tiempo_total_min'],
            creadoEn: (string) $fila['creado_en'],
            archivadaEn: $fila['archivada_en'] === null ? null : (string) $fila['archivada_en'],
            categorias: $categorias,
            etiquetas: $etiquetas,
        );
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return [
            'id' => $this->id,
            'titulo' => $this->titulo,
            'slug' => $this->slug,
            'descripcion' => $this->descripcion,
            'imagen_url' => $this->imagenUrl,
            'fuente_nombre' => $this->fuenteNombre,
            'raciones' => $this->raciones,
            'tiempo_total_min' => $this->tiempoTotalMin,
            'creado_en' => $this->creadoEn,
            'archivada_en' => $this->archivadaEn,
            'categorias' => $this->categorias,
            'etiquetas' => $this->etiquetas,
        ];
    }
}
