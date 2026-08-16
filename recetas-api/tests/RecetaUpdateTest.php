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

comprobar($repository->cambiarArchivado($id, true), 'No archivó la receta');
comprobar($repository->obtenerPorId($id) === null, 'La receta archivada sigue siendo pública');
$recetaArchivada = $repository->obtenerPorId($id, true);
comprobar(($recetaArchivada['archivada_en'] ?? null) !== null, 'No registró la fecha de archivado');
$activas = $repository->listar(1, 10, null, null, null, 'activas');
$archivadas = $repository->listar(1, 10, null, null, null, 'archivadas');
comprobar(($activas['paginacion']['total'] ?? -1) === 0, 'Incluyó una receta archivada entre las activas');
comprobar(($archivadas['paginacion']['total'] ?? -1) === 1, 'No incluyó la receta en el archivo');

comprobar($repository->cambiarArchivado($id, false), 'No restauró la receta');
comprobar($repository->obtenerPorId($id) !== null, 'La receta restaurada no volvió a ser pública');
$activasRestauradas = $repository->listar(1, 10, null, null, null, 'activas');
comprobar(($activasRestauradas['paginacion']['total'] ?? -1) === 1, 'No devolvió la receta restaurada al listado activo');
$ordenadasTitulo = $repository->listar(1, 10, null, null, null, 'activas', 'titulo', 'ASC');
comprobar(($ordenadasTitulo['datos'][0]['titulo'] ?? null) === 'Receta actualizada', 'No ordenó por título');

$imagenPrueba = '/imagenes/aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa.webp';
comprobar($repository->actualizarImagen($id, $imagenPrueba), 'No asignó la imagen de prueba');
comprobar($repository->imagenEnUso($imagenPrueba), 'No detectó una imagen en uso');
comprobar($repository->actualizarImagen($id, null), 'No eliminó la referencia a la imagen');
comprobar(!$repository->imagenEnUso($imagenPrueba), 'Mantuvo la imagen como utilizada');

$idEliminable = $repository->crear([
    'titulo' => 'Receta eliminable',
    'ingredientes' => [['nombre' => 'Sal', 'texto_original' => 'Sal']],
    'pasos' => [['numero' => 1, 'instruccion' => 'Probar.']],
]);
comprobar(!$repository->eliminarArchivada($idEliminable), 'Eliminó una receta activa');
comprobar($repository->cambiarArchivado($idEliminable, true), 'No archivó la receta eliminable');
comprobar($repository->eliminarArchivada($idEliminable), 'No eliminó la receta archivada');
comprobar($repository->obtenerPorId($idEliminable, true) === null, 'La receta eliminada sigue existiendo');
$relacionesEliminadas = (int) $pdo->query(
    'SELECT COUNT(*) FROM receta_ingredientes WHERE receta_id = ' . $idEliminable
)->fetchColumn();
comprobar($relacionesEliminadas === 0, 'No eliminó en cascada las relaciones de la receta');

fwrite(STDOUT, "RecetaUpdateTest: OK\n");
