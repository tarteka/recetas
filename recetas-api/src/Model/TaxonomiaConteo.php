<?php

declare(strict_types=1);

namespace App\Model;

/**
 * Categoría o etiqueta con su recuento de recetas, sin id — para
 * navegación pública y el autocompletado del formulario de receta en el
 * panel admin (App\Repository\RecetaRepository::listarCategorias() y
 * similares). Genérica por el mismo motivo que TaxonomiaAdmin. Para el
 * recurso real de administración (con id) ver TaxonomiaAdmin.
 */
final class TaxonomiaConteo extends Taxonomia
{
    public function __construct(
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
            nombre: (string) $fila['nombre'],
            slug: (string) $fila['slug'],
            totalRecetas: (int) $fila['total_recetas'],
        );
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return [
            'nombre' => $this->nombre,
            'slug' => $this->slug,
            'total_recetas' => $this->totalRecetas,
        ];
    }
}
