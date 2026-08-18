<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Service\RecetaValidator;
use PHPUnit\Framework\TestCase;

final class RecetaValidatorTest extends TestCase
{
    private RecetaValidator $validator;

    protected function setUp(): void
    {
        $this->validator = new RecetaValidator();
    }

    public function test_acepta_una_receta_valida(): void
    {
        $receta = [
            'titulo' => 'Sopa',
            'raciones' => 2,
            'tiempo_preparacion_min' => 10,
            'tiempo_coccion_min' => 20,
            'tiempo_total_min' => 30,
            'fuente_url' => 'https://example.com/receta',
            'imagen_url' => '/imagenes/aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa.webp',
            'ingredientes' => [['nombre' => 'Agua', 'cantidad' => 1]],
            'pasos' => [['instruccion' => 'Cocer.']],
        ];

        self::assertSame([], $this->validator->validar($receta));
    }

    public function test_rechaza_una_receta_con_multiples_campos_invalidos(): void
    {
        $receta = [
            'titulo' => '   ',
            'raciones' => 0,
            'tiempo_preparacion_min' => 20,
            'tiempo_total_min' => 10,
            'fuente_url' => 'url rota',
            'ingredientes' => [['nombre' => '', 'cantidad' => 0]],
            'pasos' => [['instruccion' => '']],
        ];

        $errores = $this->validator->validar($receta);
        $mensaje = implode(' | ', $errores);

        foreach (['título', 'ingrediente', 'cantidad', 'paso', 'raciones', 'tiempo total', 'URL'] as $texto) {
            self::assertStringContainsString($texto, $mensaje, "Falta validar: {$texto}");
        }
    }
}
