<?php

declare(strict_types=1);

namespace Tests\Feature;

/**
 * Cubre el endpoint público POST /recetas protegido por ApiTokenMiddleware,
 * que es el que usa el flujo de OpenClaw (openclaw/recetario/scripts/guardar-receta.sh)
 * para guardar una receta extraída de una URL vía Telegram.
 */
final class OpenClawRecetaFlowTest extends SlimAppTestCase
{
    public function test_rechaza_peticiones_sin_cabecera_de_autorizacion(): void
    {
        $respuesta = $this->app->handle($this->crearRequest(
            'POST',
            '/recetas',
            json_encode($this->recetaOpenClaw(), JSON_THROW_ON_ERROR)
        ));

        self::assertSame(401, $respuesta->getStatusCode(), 'Aceptó guardar una receta sin token');
    }

    public function test_rechaza_peticiones_con_token_incorrecto(): void
    {
        $respuesta = $this->app->handle($this->crearRequest(
            'POST',
            '/recetas',
            json_encode($this->recetaOpenClaw(), JSON_THROW_ON_ERROR),
            null,
            null,
            'token-incorrecto'
        ));

        self::assertSame(401, $respuesta->getStatusCode(), 'Aceptó un token distinto al configurado');
    }

    public function test_guarda_una_receta_valida_enviada_con_el_token_correcto(): void
    {
        $respuesta = $this->app->handle($this->crearRequest(
            'POST',
            '/recetas',
            json_encode($this->recetaOpenClaw(), JSON_THROW_ON_ERROR),
            null,
            null,
            self::API_TOKEN
        ));

        self::assertSame(201, $respuesta->getStatusCode(), 'No se guardó una receta válida enviada por OpenClaw');
        $cuerpo = $this->jsonRespuesta($respuesta);
        $id = (int) ($cuerpo['id'] ?? 0);
        self::assertGreaterThan(0, $id, 'La creación no devolvió un ID');
        self::assertSame('tarta-de-manzana-de-la-abuela', $cuerpo['slug'] ?? null, 'No devolvió el slug derivado del título');

        $persistida = $this->recetas->obtenerPorId($id);
        self::assertNotNull($persistida, 'La receta no quedó persistida en SQLite');
        self::assertSame('https://example.com/tarta-de-manzana', $persistida['fuente_url'] ?? null, 'No guardó la URL de origen');
        self::assertCount(2, $persistida['ingredientes'] ?? [], 'No guardó todos los ingredientes parseados');
        self::assertCount(2, $persistida['pasos'] ?? [], 'No guardó todos los pasos parseados');
    }

    public function test_rechaza_una_receta_invalida_con_422_sin_filtrar_datos_internos(): void
    {
        $datos = $this->recetaOpenClaw();
        $datos['titulo'] = '   ';
        $datos['ingredientes'] = [];

        $respuesta = $this->app->handle($this->crearRequest(
            'POST',
            '/recetas',
            json_encode($datos, JSON_THROW_ON_ERROR),
            null,
            null,
            self::API_TOKEN
        ));

        self::assertSame(422, $respuesta->getStatusCode(), 'La API aceptó una receta inválida procedente de OpenClaw');
        $cuerpo = (string) $respuesta->getBody();
        self::assertStringNotContainsString('/var/www', $cuerpo, 'La validación expuso rutas internas');
        self::assertStringNotContainsString('SQLSTATE', $cuerpo, 'La validación expuso detalles de SQL');
    }

    public function test_rechaza_json_invalido_con_400(): void
    {
        $respuesta = $this->app->handle($this->crearRequest(
            'POST',
            '/recetas',
            '{esto no es json',
            null,
            null,
            self::API_TOKEN
        ));

        self::assertSame(400, $respuesta->getStatusCode(), 'No rechazó un cuerpo JSON mal formado');
    }

    /** @return array<string, mixed> */
    private function recetaOpenClaw(): array
    {
        return [
            'titulo' => 'Tarta de manzana de la abuela',
            'descripcion' => 'Receta extraída automáticamente desde una URL compartida por Telegram.',
            'fuente_url' => 'https://example.com/tarta-de-manzana',
            'fuente_nombre' => 'example.com',
            'raciones' => 8,
            'tiempo_preparacion_min' => 20,
            'tiempo_coccion_min' => 45,
            'tiempo_total_min' => 65,
            'ingredientes' => [
                ['nombre' => 'Manzana', 'cantidad' => 4, 'unidad' => 'unidades', 'texto_original' => '4 manzanas'],
                ['nombre' => 'Harina', 'cantidad' => 250, 'unidad' => 'g', 'texto_original' => '250 g de harina'],
            ],
            'pasos' => [
                ['numero' => 1, 'instruccion' => 'Pelar y laminar las manzanas.'],
                ['numero' => 2, 'instruccion' => 'Hornear a 180 grados durante 45 minutos.'],
            ],
        ];
    }
}
