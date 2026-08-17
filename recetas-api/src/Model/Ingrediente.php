<?php

declare(strict_types=1);

namespace App\Model;

use JsonSerializable;

final class Ingrediente implements JsonSerializable
{
    public function __construct(
        public readonly string $nombre,
        // SQLite REAL siempre llega como float vía PDO en este esquema.
        public readonly ?float $cantidad,
        public readonly ?string $unidad,
        public readonly ?string $notas,
        public readonly string $textoOriginal,
        public readonly int $posicion,
    ) {
    }

    /** @param array<string, mixed> $fila */
    public static function desdeFila(array $fila): self
    {
        return new self(
            nombre: (string) $fila['nombre'],
            cantidad: $fila['cantidad'] === null ? null : (float) $fila['cantidad'],
            unidad: $fila['unidad'] === null ? null : (string) $fila['unidad'],
            notas: $fila['notas'] === null ? null : (string) $fila['notas'],
            textoOriginal: (string) $fila['texto_original'],
            posicion: (int) $fila['posicion'],
        );
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return [
            'nombre' => $this->nombre,
            'cantidad' => $this->cantidad,
            'unidad' => $this->unidad,
            'notas' => $this->notas,
            'texto_original' => $this->textoOriginal,
            'posicion' => $this->posicion,
        ];
    }
}
