<?php

declare(strict_types=1);

namespace App\Model;

use JsonSerializable;

final class Paso implements JsonSerializable
{
    public function __construct(
        public readonly int $numero,
        public readonly string $instruccion,
        public readonly ?string $imagenUrl,
    ) {
    }

    /** @param array<string, mixed> $fila */
    public static function desdeFila(array $fila): self
    {
        return new self(
            numero: (int) $fila['numero'],
            instruccion: (string) $fila['instruccion'],
            imagenUrl: $fila['imagen_url'] === null ? null : (string) $fila['imagen_url'],
        );
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return [
            'numero' => $this->numero,
            'instruccion' => $this->instruccion,
            'imagen_url' => $this->imagenUrl,
        ];
    }
}
