<?php

declare(strict_types=1);

namespace App\Model;

/**
 * Categoría o etiqueta con id: el recurso real de administración detrás de
 * /admin/categorias y /admin/etiquetas (App\Repository\TaxonomiaRepository).
 * Genérica a propósito — ese controlador/repositorio no distinguen
 * categoría de etiqueta hasta un parámetro $tipo en tiempo de ejecución,
 * así que forzar Categoria/Etiqueta aquí exigiría una fábrica sin ninguna
 * ganancia real. No confundir con TaxonomiaDatos (payload de escritura) ni
 * con TaxonomiaConteo (agregado público, sin id).
 */
final class TaxonomiaAdmin extends Taxonomia
{
    public function __construct(
        public readonly int $id,
        string $nombre,
        string $slug,
        public readonly int $totalRecetas,
    ) {
        parent::__construct($nombre, $slug);
    }

    /** @param array<string, mixed> $fila */
    public static function desdeFila(array $fila): self
    {
        return new self(
            id: (int) $fila['id'],
            nombre: (string) $fila['nombre'],
            slug: (string) $fila['slug'],
            totalRecetas: (int) $fila['total_recetas'],
        );
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return [
            'id' => $this->id,
            'nombre' => $this->nombre,
            'slug' => $this->slug,
            'total_recetas' => $this->totalRecetas,
        ];
    }
}
