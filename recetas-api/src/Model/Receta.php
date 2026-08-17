<?php

declare(strict_types=1);

namespace App\Model;

use JsonSerializable;

/**
 * Detalle completo de una receta. Usado tanto por el detalle público
 * (GET /recetas/{id|slug}) como por el administrativo (GET
 * /admin/recetas/{id}) — mismo método de repositorio, sin divergencia
 * de forma entre ambos.
 *
 * Lado de LECTURA (se hidrata desde una fila de SQLite vía desdeFila()).
 * No confundir con RecetaDatos (lo que se recibe al crear/actualizar) ni
 * con RecetaResumen (la fila, más ligera, que usan los listados).
 */
final class Receta implements JsonSerializable
{
    /**
     * @param list<Ingrediente> $ingredientes
     * @param list<Paso> $pasos
     * @param list<Categoria> $categorias
     * @param list<Etiqueta> $etiquetas
     */
    public function __construct(
        public readonly int $id,
        public readonly string $titulo,
        public readonly ?string $slug,
        public readonly ?string $descripcion,
        public readonly ?string $fuenteUrl,
        public readonly ?string $fuenteNombre,
        public readonly ?string $imagenUrl,
        public readonly ?int $raciones,
        public readonly ?int $tiempoPreparacionMin,
        public readonly ?int $tiempoCoccionMin,
        public readonly ?int $tiempoTotalMin,
        public readonly string $creadoEn,
        public readonly ?string $actualizadoEn,
        public readonly ?string $archivadaEn,
        public readonly array $ingredientes,
        public readonly array $pasos,
        public readonly array $categorias,
        public readonly array $etiquetas,
    ) {
    }

    /**
     * @param array<string, mixed> $fila
     * @param list<Ingrediente> $ingredientes
     * @param list<Paso> $pasos
     * @param list<Categoria> $categorias
     * @param list<Etiqueta> $etiquetas
     */
    public static function desdeFila(
        array $fila,
        array $ingredientes,
        array $pasos,
        array $categorias,
        array $etiquetas
    ): self {
        return new self(
            id: (int) $fila['id'],
            titulo: (string) $fila['titulo'],
            slug: $fila['slug'] === null ? null : (string) $fila['slug'],
            descripcion: $fila['descripcion'] === null ? null : (string) $fila['descripcion'],
            fuenteUrl: $fila['fuente_url'] === null ? null : (string) $fila['fuente_url'],
            fuenteNombre: $fila['fuente_nombre'] === null ? null : (string) $fila['fuente_nombre'],
            imagenUrl: $fila['imagen_url'] === null ? null : (string) $fila['imagen_url'],
            raciones: $fila['raciones'] === null ? null : (int) $fila['raciones'],
            tiempoPreparacionMin: $fila['tiempo_preparacion_min'] === null ? null : (int) $fila['tiempo_preparacion_min'],
            tiempoCoccionMin: $fila['tiempo_coccion_min'] === null ? null : (int) $fila['tiempo_coccion_min'],
            tiempoTotalMin: $fila['tiempo_total_min'] === null ? null : (int) $fila['tiempo_total_min'],
            creadoEn: (string) $fila['creado_en'],
            actualizadoEn: $fila['actualizado_en'] === null ? null : (string) $fila['actualizado_en'],
            archivadaEn: $fila['archivada_en'] === null ? null : (string) $fila['archivada_en'],
            ingredientes: $ingredientes,
            pasos: $pasos,
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
            'fuente_url' => $this->fuenteUrl,
            'fuente_nombre' => $this->fuenteNombre,
            'imagen_url' => $this->imagenUrl,
            'raciones' => $this->raciones,
            'tiempo_preparacion_min' => $this->tiempoPreparacionMin,
            'tiempo_coccion_min' => $this->tiempoCoccionMin,
            'tiempo_total_min' => $this->tiempoTotalMin,
            'creado_en' => $this->creadoEn,
            'actualizado_en' => $this->actualizadoEn,
            'archivada_en' => $this->archivadaEn,
            'ingredientes' => $this->ingredientes,
            'pasos' => $this->pasos,
            'categorias' => $this->categorias,
            'etiquetas' => $this->etiquetas,
        ];
    }
}
