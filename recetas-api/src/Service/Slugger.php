<?php

declare(strict_types=1);

namespace App\Service;

final class Slugger
{
    /**
     * Normaliza un texto arbitrario para utilizarlo como slug en una URL.
     */
    public static function generar(string $texto): string
    {
        $texto = mb_strtolower(trim($texto), 'UTF-8');

        $texto = iconv(
            'UTF-8',
            'ASCII//TRANSLIT//IGNORE',
            $texto
        ) ?: $texto;

        $texto = preg_replace('/[^a-z0-9]+/', '-', $texto) ?? '';

        return trim($texto, '-');
    }
}
