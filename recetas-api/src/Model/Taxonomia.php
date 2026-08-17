<?php

declare(strict_types=1);

namespace App\Model;

use JsonSerializable;

/**
 * Base de una categoría o etiqueta (nombre + slug). Categoria y Etiqueta
 * son idénticas en forma y comportamiento — comparten tabla de patrón,
 * validación y repositorio genérico (App\Repository\TaxonomiaRepository,
 * discriminado en runtime por un parámetro $tipo) — por eso derivan de
 * aquí en vez de duplicarse.
 *
 * desdeFila() no se define aquí: cada subclase añade campos distintos
 * (TaxonomiaAdmin/TaxonomiaConteo tienen constructores más amplios que
 * Categoria/Etiqueta), así que un factory compartido con new static()
 * no sería seguro entre todas ellas.
 */
abstract class Taxonomia implements JsonSerializable
{
    public function __construct(
        public readonly string $nombre,
        public readonly string $slug,
    ) {
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return [
            'nombre' => $this->nombre,
            'slug' => $this->slug,
        ];
    }
}
