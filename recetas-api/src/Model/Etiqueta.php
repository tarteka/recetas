<?php

declare(strict_types=1);

namespace App\Model;

/** Etiqueta tal y como aparece embebida dentro de una receta. */
final class Etiqueta extends Taxonomia
{
    /** @param array<string, mixed> $fila */
    public static function desdeFila(array $fila): self
    {
        return new self(
            nombre: (string) $fila['nombre'],
            slug: (string) $fila['slug'],
        );
    }
}
