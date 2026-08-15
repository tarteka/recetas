<?php

declare(strict_types=1);

namespace App\Service;

use GdImage;
use RuntimeException;

final class ImagenService
{
    private const DIRECTORIO = '/datos/imagenes';

    private const ANCHO = 1200;
    private const ALTO = 800;

    private const CALIDAD_WEBP = 82;

    private const BYTES_MAXIMOS = 10 * 1024 * 1024;

    /**
     * Valida, recorta y almacena una imagen recibida como WebP 1200x800.
     */
    public function guardar(string $contenido): string
    {
        if ($contenido === '') {
            throw new RuntimeException('La imagen está vacía');
        }

        if (strlen($contenido) > self::BYTES_MAXIMOS) {
            throw new RuntimeException(
                'La imagen supera el tamaño máximo permitido'
            );
        }

        $imagen = @imagecreatefromstring($contenido);

        if (!$imagen instanceof GdImage) {
            throw new RuntimeException(
                'El contenido recibido no es una imagen válida'
            );
        }

        // Los objetos GdImage se liberan automáticamente al quedar sin referencias.
        $normalizada = $this->normalizar($imagen);

        return $this->guardarWebp($normalizada);
    }

    /**
     * Elimina únicamente imágenes generadas por este servicio.
     */
    public function eliminar(string $imagenUrl): void
    {
        if (!preg_match('#^/imagenes/([a-f0-9]{32}\.webp)$#', $imagenUrl, $coincidencias)) {
            return;
        }

        $ruta = self::DIRECTORIO . '/' . $coincidencias[1];
        if (is_file($ruta) && !unlink($ruta)) {
            throw new RuntimeException('No se pudo eliminar la imagen anterior');
        }
    }

    /**
     * Genera una imagen 1200x800 mediante redimensionado y recorte centrado.
     */
    private function normalizar(GdImage $origen): GdImage
    {
        $anchoOrigen = imagesx($origen);
        $altoOrigen = imagesy($origen);

        if ($anchoOrigen <= 0 || $altoOrigen <= 0) {
            throw new RuntimeException(
                'Las dimensiones de la imagen no son válidas'
            );
        }

        // Calcula un "cover": llena 1200x800 sin deformar la imagen.
        $escala = max(
            self::ANCHO / $anchoOrigen,
            self::ALTO / $altoOrigen
        );

        $anchoEscalado = (int) ceil($anchoOrigen * $escala);
        $altoEscalado = (int) ceil($altoOrigen * $escala);

        $destino = imagecreatetruecolor(
            self::ANCHO,
            self::ALTO
        );

        if (!$destino instanceof GdImage) {
            throw new RuntimeException(
                'No se pudo crear la imagen de destino'
            );
        }

        // Conserva correctamente posibles transparencias.
        imagealphablending($destino, false);
        imagesavealpha($destino, true);

        $transparente = imagecolorallocatealpha(
            $destino,
            0,
            0,
            0,
            127
        );

        imagefill($destino, 0, 0, $transparente);

        $x = (int) floor(
            (self::ANCHO - $anchoEscalado) / 2
        );

        $y = (int) floor(
            (self::ALTO - $altoEscalado) / 2
        );

        $resultado = imagecopyresampled(
            $destino,
            $origen,
            $x,
            $y,
            0,
            0,
            $anchoEscalado,
            $altoEscalado,
            $anchoOrigen,
            $altoOrigen
        );

        if (!$resultado) {
            throw new RuntimeException(
                'No se pudo normalizar la imagen'
            );
        }

        return $destino;
    }

    /**
     * Guarda la imagen normalizada con un nombre criptográficamente aleatorio.
     */
    private function guardarWebp(GdImage $imagen): string
    {
        if (
            !is_dir(self::DIRECTORIO)
            && !mkdir(self::DIRECTORIO, 0755, true)
            && !is_dir(self::DIRECTORIO)
        ) {
            throw new RuntimeException(
                'No se pudo crear el directorio de imágenes'
            );
        }

        $nombre = bin2hex(random_bytes(16)) . '.webp';
        $ruta = self::DIRECTORIO . '/' . $nombre;

        if (!imagewebp($imagen, $ruta, self::CALIDAD_WEBP)) {
            throw new RuntimeException(
                'No se pudo generar la imagen WebP'
            );
        }

        if (!is_file($ruta) || filesize($ruta) === 0) {
            @unlink($ruta);

            throw new RuntimeException(
                'No se pudo almacenar correctamente la imagen'
            );
        }

        return '/imagenes/' . $nombre;
    }
}
