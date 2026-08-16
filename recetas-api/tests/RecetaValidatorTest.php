<?php

declare(strict_types=1);

use App\Service\RecetaValidator;

require __DIR__ . '/../vendor/autoload.php';

$validator = new RecetaValidator();
$valida = [
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

if ($validator->validar($valida) !== []) {
    throw new RuntimeException('Rechazó una receta válida');
}

$invalida = [
    'titulo' => '   ',
    'raciones' => 0,
    'tiempo_preparacion_min' => 20,
    'tiempo_total_min' => 10,
    'fuente_url' => 'url rota',
    'ingredientes' => [['nombre' => '', 'cantidad' => 0]],
    'pasos' => [['instruccion' => '']],
];
$errores = $validator->validar($invalida);
foreach (['título', 'ingrediente', 'cantidad', 'paso', 'raciones', 'tiempo total', 'URL'] as $texto) {
    if (!str_contains(implode(' | ', $errores), $texto)) {
        throw new RuntimeException('Falta validar: ' . $texto);
    }
}

fwrite(STDOUT, "RecetaValidatorTest: OK\n");
