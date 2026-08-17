<?php

declare(strict_types=1);

namespace App\Service;

final class Slugger
{
    /**
     * Sustituciones a caracteres ASCII. La tabla de transliteración de
     * iconv("...//TRANSLIT...") depende de la librería del sistema operativo
     * y da resultados distintos en Windows y Linux; esta tabla explícita es
     * determinista en cualquier plataforma. Solo hacen falta minúsculas
     * porque mb_strtolower() ya se aplica antes de sustituir.
     */
    private const TRANSLITERACIONES = [
        'á' => 'a', 'à' => 'a', 'ä' => 'a', 'â' => 'a', 'ã' => 'a', 'å' => 'a',
        'é' => 'e', 'è' => 'e', 'ë' => 'e', 'ê' => 'e',
        'í' => 'i', 'ì' => 'i', 'ï' => 'i', 'î' => 'i',
        'ó' => 'o', 'ò' => 'o', 'ö' => 'o', 'ô' => 'o', 'õ' => 'o',
        'ú' => 'u', 'ù' => 'u', 'ü' => 'u', 'û' => 'u',
        'ñ' => 'n', 'ç' => 'c', 'ý' => 'y', 'ÿ' => 'y', 'ß' => 'ss',
    ];

    /**
     * Normaliza un texto arbitrario para utilizarlo como slug en una URL.
     */
    public static function generar(string $texto): string
    {
        $texto = mb_strtolower(trim($texto), 'UTF-8');
        $texto = strtr($texto, self::TRANSLITERACIONES);
        $texto = preg_replace('/[^a-z0-9]+/', '-', $texto) ?? '';

        return trim($texto, '-');
    }
}
