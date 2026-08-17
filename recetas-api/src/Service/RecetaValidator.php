<?php

declare(strict_types=1);

namespace App\Service;

final class RecetaValidator
{
    /**
     * Valida el JSON recibido sin asumir ninguna forma: es la puerta de
     * entrada de datos externos (admin o el token de OpenClaw), aún no
     * garantiza la forma de RecetaDatos.
     *
     * @param array<string, mixed> $datos
     * @return list<string>
     */
    public function validar(array $datos): array
    {
        $errores = [];

        if (trim((string) ($datos['titulo'] ?? '')) === '') {
            $errores[] = 'El título es obligatorio';
        }

        $ingredientes = $datos['ingredientes'] ?? null;
        if (!is_array($ingredientes) || $ingredientes === []) {
            $errores[] = 'Añade al menos un ingrediente';
        } else {
            foreach ($ingredientes as $indice => $ingrediente) {
                if (!is_array($ingrediente)) {
                    $errores[] = 'El ingrediente ' . ($indice + 1) . ' no tiene nombre';
                    continue;
                }
                if (trim((string) ($ingrediente['nombre'] ?? '')) === '') {
                    $errores[] = 'El ingrediente ' . ($indice + 1) . ' no tiene nombre';
                }
                $cantidad = $ingrediente['cantidad'] ?? null;
                if ($cantidad !== null && $cantidad !== '' && (!is_numeric($cantidad) || (float) $cantidad <= 0)) {
                    $errores[] = 'La cantidad del ingrediente ' . ($indice + 1) . ' debe ser mayor que 0';
                }
            }
        }

        $pasos = $datos['pasos'] ?? null;
        if (!is_array($pasos) || $pasos === []) {
            $errores[] = 'Añade al menos un paso de elaboración';
        } else {
            foreach ($pasos as $indice => $paso) {
                if (!is_array($paso) || trim((string) ($paso['instruccion'] ?? '')) === '') {
                    $errores[] = 'El paso ' . ($indice + 1) . ' no tiene una instrucción';
                }
            }
        }

        $this->validarEntero($datos, 'raciones', 1, 'Las raciones', $errores);
        foreach (['tiempo_preparacion_min', 'tiempo_coccion_min', 'tiempo_total_min'] as $campo) {
            $this->validarEntero($datos, $campo, 0, 'Los tiempos', $errores);
        }

        $total = $this->numeroOpcional($datos['tiempo_total_min'] ?? null);
        $preparacion = $this->numeroOpcional($datos['tiempo_preparacion_min'] ?? null);
        $coccion = $this->numeroOpcional($datos['tiempo_coccion_min'] ?? null);
        if ($total !== null && (($preparacion !== null && $total < $preparacion) || ($coccion !== null && $total < $coccion))) {
            $errores[] = 'El tiempo total no puede ser menor que preparación o cocción';
        }

        $fuenteUrl = trim((string) ($datos['fuente_url'] ?? ''));
        if ($fuenteUrl !== '' && filter_var($fuenteUrl, FILTER_VALIDATE_URL) === false) {
            $errores[] = 'La URL de la fuente no es válida';
        }

        $imagenUrl = trim((string) ($datos['imagen_url'] ?? ''));
        $imagenGestionada = preg_match('#^/imagenes/[a-f0-9]{32}\.webp$#', $imagenUrl) === 1;
        if ($imagenUrl !== '' && !$imagenGestionada && filter_var($imagenUrl, FILTER_VALIDATE_URL) === false) {
            $errores[] = 'La URL de la imagen no es válida';
        }

        return array_values(array_unique($errores));
    }

    /**
     * @param array<string, mixed> $datos
     * @param list<string> $errores
     */
    private function validarEntero(array $datos, string $campo, int $minimo, string $etiqueta, array &$errores): void
    {
        $valor = $datos[$campo] ?? null;
        if ($valor === null || $valor === '') {
            return;
        }
        if (filter_var($valor, FILTER_VALIDATE_INT) === false || (int) $valor < $minimo) {
            $errores[] = $etiqueta . ' deben indicarse con números enteros no inferiores a ' . $minimo;
        }
    }

    private function numeroOpcional(mixed $valor): ?float
    {
        return $valor === null || $valor === '' || !is_numeric($valor) ? null : (float) $valor;
    }
}
