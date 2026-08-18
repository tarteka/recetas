<?php

declare(strict_types=1);

namespace App\Model;

use InvalidArgumentException;

/**
 * Representación tipada e inmutable del payload de una receta, construida
 * únicamente a partir de datos que App\Service\RecetaValidator ya ha
 * validado. fromArray() solo comprueba presencia estructural (claves que
 * el repositorio usa sin fallback); el contenido (no vacíos, rangos,
 * formato de URL...) sigue siendo responsabilidad exclusiva del validador.
 *
 * Esta es la única clase de este trío que representa el lado de
 * ESCRITURA (el JSON que llega en POST/PUT /recetas). Para el lado de
 * LECTURA ver Receta (detalle completo) y RecetaResumen (fila de listado).
 *
 * @phpstan-type IngredienteDatos array{
 *     nombre: string,
 *     cantidad?: int|float|string|null,
 *     unidad?: string|null,
 *     notas?: string|null,
 *     texto_original: string,
 * }
 * @phpstan-type PasoDatos array{
 *     numero?: int|string|null,
 *     instruccion: string,
 *     imagen_url?: string|null,
 * }
 */
final class RecetaDatos
{
    /**
     * @param list<IngredienteDatos> $ingredientes
     * @param list<PasoDatos> $pasos
     * @param list<string> $categorias
     * @param list<string> $etiquetas
     */
    private function __construct(
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
        public readonly array $ingredientes,
        public readonly array $pasos,
        public readonly array $categorias,
        public readonly array $etiquetas,
    ) {
    }

    /** @param array<string, mixed> $datos */
    public static function fromArray(array $datos): self
    {
        if (!array_key_exists('titulo', $datos)) {
            throw new InvalidArgumentException('Falta el título de la receta');
        }

        // listaDeArrays() solo verifica en runtime que cada elemento trae las
        // claves requeridas; PHPStan no puede deducir la forma exacta a partir
        // de esa comprobación, así que se asume aquí mismo, justo donde se
        // acaba de comprobar.
        /** @var list<IngredienteDatos> $ingredientes */
        $ingredientes = self::listaDeArrays($datos, 'ingredientes', ['nombre', 'texto_original']);
        /** @var list<PasoDatos> $pasos */
        $pasos = self::listaDeArrays($datos, 'pasos', ['instruccion']);

        return new self(
            titulo: trim((string) $datos['titulo']),
            slug: self::textoPassthrough($datos['slug'] ?? null),
            descripcion: self::textoPassthrough($datos['descripcion'] ?? null),
            fuenteUrl: self::textoPassthrough($datos['fuente_url'] ?? null),
            fuenteNombre: self::textoPassthrough($datos['fuente_nombre'] ?? null),
            imagenUrl: self::textoPassthrough($datos['imagen_url'] ?? null),
            raciones: self::enteroOpcional($datos['raciones'] ?? null),
            tiempoPreparacionMin: self::enteroOpcional($datos['tiempo_preparacion_min'] ?? null),
            tiempoCoccionMin: self::enteroOpcional($datos['tiempo_coccion_min'] ?? null),
            tiempoTotalMin: self::enteroOpcional($datos['tiempo_total_min'] ?? null),
            ingredientes: $ingredientes,
            pasos: $pasos,
            categorias: self::listaDeTerminos($datos['categorias'] ?? []),
            etiquetas: self::listaDeTerminos($datos['etiquetas'] ?? []),
        );
    }

    /**
     * Comprueba que la clave, si está presente, es una lista de arrays que
     * traen las claves indicadas (las que el repositorio usa sin fallback).
     *
     * @param array<string, mixed> $datos
     * @param list<string> $clavesRequeridas
     * @return list<array<string, mixed>>
     */
    private static function listaDeArrays(array $datos, string $clave, array $clavesRequeridas): array
    {
        $valor = $datos[$clave] ?? [];
        if (!is_array($valor)) {
            throw new InvalidArgumentException("«{$clave}» debe ser una lista");
        }

        $lista = [];
        foreach (array_values($valor) as $indice => $item) {
            if (!is_array($item)) {
                throw new InvalidArgumentException("El elemento {$indice} de «{$clave}» debe ser un objeto");
            }
            foreach ($clavesRequeridas as $requerida) {
                if (!array_key_exists($requerida, $item)) {
                    throw new InvalidArgumentException("El elemento {$indice} de «{$clave}» no tiene «{$requerida}»");
                }
            }
            $lista[] = $item;
        }

        return $lista;
    }

    /**
     * Normaliza categorías/etiquetas a una lista de nombres: cada elemento
     * puede llegar como texto plano (así lo envía OpenClaw) o como un
     * objeto {nombre} (lo que envía el panel admin).
     *
     * @return list<string>
     */
    private static function listaDeTerminos(mixed $valor): array
    {
        if (!is_array($valor)) {
            return [];
        }

        $terminos = [];
        foreach ($valor as $item) {
            $nombre = trim((string) (is_array($item) ? ($item['nombre'] ?? '') : $item));
            if ($nombre !== '') {
                $terminos[] = $nombre;
            }
        }

        return $terminos;
    }

    private static function textoPassthrough(mixed $valor): ?string
    {
        return $valor === null ? null : (string) $valor;
    }

    private static function enteroOpcional(mixed $valor): ?int
    {
        return $valor === null || $valor === '' ? null : (int) $valor;
    }
}
