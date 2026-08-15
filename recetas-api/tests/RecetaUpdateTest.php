<?php

declare(strict_types=1);

use App\Repository\RecetaRepository;

require __DIR__ . '/../vendor/autoload.php';

function comprobar(bool $condicion, string $mensaje): void
{
    if (!$condicion) {
        throw new RuntimeException($mensaje);
    }
}

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
$schema = file_get_contents(__DIR__ . '/../database/schema.sql');
comprobar(is_string($schema), 'No se pudo cargar el esquema');
$pdo->exec($schema);

$repository = new RecetaRepository($pdo);
$id = $repository->crear([
    'titulo' => 'Receta original',
    'ingredientes' => [[
        'nombre' => 'Patata',
        'cantidad' => 2,
        'unidad' => 'unidades',
        'texto_original' => '2 unidades de patata',
    ]],
    'pasos' => [[
        'numero' => 1,
        'instruccion' => 'Cocer.',
    ]],
]);

$actualizada = $repository->actualizar($id, [
    'titulo' => 'Receta actualizada',
    'descripcion' => 'Descripción nueva',
    'raciones' => 4,
    'ingredientes' => [[
        'nombre' => 'Boniato',
        'cantidad' => 3,
        'unidad' => 'unidades',
        'notas' => 'medianos',
        'texto_original' => '3 unidades de boniato medianos',
    ]],
    'pasos' => [
        ['numero' => 1, 'instruccion' => 'Pelar.'],
        ['numero' => 2, 'instruccion' => 'Hornear.'],
    ],
    'categorias' => [['nombre' => 'Platos principales']],
    'etiquetas' => [['nombre' => 'Horno']],
]);

comprobar($actualizada, 'No se actualizó la receta existente');
$receta = $repository->obtenerPorId($id);
comprobar(($receta['titulo'] ?? null) === 'Receta actualizada', 'No actualizó la cabecera');
comprobar(count($receta['ingredientes'] ?? []) === 1, 'No reemplazó ingredientes');
comprobar(($receta['ingredientes'][0]['nombre'] ?? null) === 'Boniato', 'Conservó el ingrediente anterior');
comprobar(count($receta['pasos'] ?? []) === 2, 'No reemplazó los pasos');
comprobar(($receta['categorias'][0]['nombre'] ?? null) === 'Platos principales', 'No guardó categorías estructuradas');
comprobar(($receta['etiquetas'][0]['nombre'] ?? null) === 'Horno', 'No guardó etiquetas estructuradas');
comprobar($repository->actualizar(999, [
    'titulo' => 'Inexistente',
    'ingredientes' => [],
    'pasos' => [],
]) === false, 'Aceptó un identificador inexistente');

try {
    $repository->actualizar($id, [
        'titulo' => 'No debe persistir',
        'ingredientes' => [[
            'nombre' => 'Boniato',
            'texto_original' => 'Boniato',
        ]],
        'pasos' => [
            ['numero' => 1, 'instruccion' => 'Uno'],
            ['numero' => 1, 'instruccion' => 'Duplicado'],
        ],
    ]);
    throw new RuntimeException('La actualización inválida no falló');
} catch (PDOException) {
    $recetaTrasError = $repository->obtenerPorId($id);
    comprobar(($recetaTrasError['titulo'] ?? null) === 'Receta actualizada', 'La transacción no hizo rollback');
    comprobar(count($recetaTrasError['pasos'] ?? []) === 2, 'El rollback dejó relaciones incompletas');
}

fwrite(STDOUT, "RecetaUpdateTest: OK\n");

