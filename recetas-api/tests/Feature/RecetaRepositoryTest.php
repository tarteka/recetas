<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Model\RecetaDatos;
use App\Repository\RecetaRepository;
use PDO;
use PDOException;
use PHPUnit\Framework\TestCase;

final class RecetaRepositoryTest extends TestCase
{
    private PDO $pdo;
    private RecetaRepository $recetas;

    protected function setUp(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $schema = file_get_contents(__DIR__ . '/../../database/schema.sql');
        self::assertIsString($schema, 'No se pudo cargar el esquema');
        $pdo->exec($schema);

        $this->pdo = $pdo;
        $this->recetas = new RecetaRepository($pdo);
    }

    public function test_actualizar_reemplaza_cabecera_ingredientes_pasos_y_taxonomia(): void
    {
        $id = $this->recetas->crear(RecetaDatos::fromArray([
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
        ]));

        $actualizada = $this->recetas->actualizar($id, RecetaDatos::fromArray([
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
        ]));

        self::assertTrue($actualizada, 'No se actualizó la receta existente');

        $receta = $this->recetas->obtenerPorId($id);
        self::assertNotNull($receta, 'No se recuperó la receta actualizada');
        self::assertSame('Receta actualizada', $receta->titulo, 'No actualizó la cabecera');
        self::assertSame(
            'receta-actualizada',
            $receta->slug,
            'El slug no se derivó del título cuando la actualización no envió uno explícito'
        );
        self::assertNotNull(
            $this->recetas->obtenerPorSlug('receta-actualizada'),
            'No se pudo recuperar la receta por su slug'
        );

        self::assertCount(1, $receta->ingredientes, 'No reemplazó ingredientes');
        self::assertSame('Boniato', $receta->ingredientes[0]->nombre, 'Conservó el ingrediente anterior');
        self::assertCount(2, $receta->pasos, 'No reemplazó los pasos');
        self::assertSame(
            'Platos principales',
            $receta->categorias[0]->nombre,
            'No guardó categorías estructuradas'
        );
        self::assertSame('Horno', $receta->etiquetas[0]->nombre, 'No guardó etiquetas estructuradas');
    }

    public function test_resuelve_colisiones_de_slug_con_sufijo_numerico(): void
    {
        $this->recetas->crear(RecetaDatos::fromArray([
            'titulo' => 'Receta actualizada',
            'ingredientes' => [['nombre' => 'Sal', 'texto_original' => 'Sal']],
            'pasos' => [['numero' => 1, 'instruccion' => 'Probar.']],
        ]));

        $idDuplicado = $this->recetas->crear(RecetaDatos::fromArray([
            'titulo' => 'Receta actualizada',
            'ingredientes' => [['nombre' => 'Sal', 'texto_original' => 'Sal']],
            'pasos' => [['numero' => 1, 'instruccion' => 'Probar.']],
        ]));

        $recetaDuplicada = $this->recetas->obtenerPorId($idDuplicado);
        self::assertSame(
            'receta-actualizada-2',
            $recetaDuplicada?->slug,
            'No se resolvió la colisión de slugs con un sufijo numérico'
        );
    }

    public function test_actualizar_devuelve_false_para_un_id_inexistente(): void
    {
        $actualizada = $this->recetas->actualizar(999, RecetaDatos::fromArray([
            'titulo' => 'Inexistente',
            'ingredientes' => [],
            'pasos' => [],
        ]));

        self::assertFalse($actualizada, 'Aceptó un identificador inexistente');
    }

    public function test_actualizar_hace_rollback_si_los_pasos_tienen_numero_duplicado(): void
    {
        $id = $this->recetas->crear(RecetaDatos::fromArray([
            'titulo' => 'Receta con rollback',
            'ingredientes' => [['nombre' => 'Sal', 'texto_original' => 'Sal']],
            'pasos' => [['numero' => 1, 'instruccion' => 'Probar.']],
        ]));

        $this->expectException(PDOException::class);

        try {
            $this->recetas->actualizar($id, RecetaDatos::fromArray([
                'titulo' => 'No debe persistir',
                'ingredientes' => [['nombre' => 'Sal', 'texto_original' => 'Sal']],
                'pasos' => [
                    ['numero' => 1, 'instruccion' => 'Uno'],
                    ['numero' => 1, 'instruccion' => 'Duplicado'],
                ],
            ]));
        } finally {
            $receta = $this->recetas->obtenerPorId($id);
            self::assertSame('Receta con rollback', $receta?->titulo, 'La transacción no hizo rollback');
            self::assertCount(1, $receta->pasos, 'El rollback dejó relaciones incompletas');
        }
    }

    public function test_archivar_oculta_la_receta_del_publico_y_restaurar_la_recupera(): void
    {
        $id = $this->recetas->crear(RecetaDatos::fromArray([
            'titulo' => 'Receta archivable',
            'ingredientes' => [['nombre' => 'Sal', 'texto_original' => 'Sal']],
            'pasos' => [['numero' => 1, 'instruccion' => 'Probar.']],
        ]));

        self::assertTrue($this->recetas->cambiarArchivado($id, true), 'No archivó la receta');
        self::assertNull($this->recetas->obtenerPorId($id), 'La receta archivada sigue siendo pública');
        $recetaArchivada = $this->recetas->obtenerPorId($id, true);
        self::assertNotNull($recetaArchivada?->archivadaEn, 'No registró la fecha de archivado');

        $activas = $this->recetas->listar(1, 10, null, null, null, 'activas');
        $archivadas = $this->recetas->listar(1, 10, null, null, null, 'archivadas');
        self::assertSame(0, $activas['paginacion']['total'], 'Incluyó una receta archivada entre las activas');
        self::assertSame(1, $archivadas['paginacion']['total'], 'No incluyó la receta en el archivo');

        self::assertTrue($this->recetas->cambiarArchivado($id, false), 'No restauró la receta');
        self::assertNotNull($this->recetas->obtenerPorId($id), 'La receta restaurada no volvió a ser pública');
        $activasRestauradas = $this->recetas->listar(1, 10, null, null, null, 'activas');
        self::assertSame(
            1,
            $activasRestauradas['paginacion']['total'],
            'No devolvió la receta restaurada al listado activo'
        );
    }

    public function test_listar_ordena_por_titulo(): void
    {
        $this->recetas->crear(RecetaDatos::fromArray([
            'titulo' => 'Zanahorias asadas',
            'ingredientes' => [['nombre' => 'Zanahoria', 'texto_original' => 'Zanahoria']],
            'pasos' => [['numero' => 1, 'instruccion' => 'Asar.']],
        ]));
        $this->recetas->crear(RecetaDatos::fromArray([
            'titulo' => 'Ajo confitado',
            'ingredientes' => [['nombre' => 'Ajo', 'texto_original' => 'Ajo']],
            'pasos' => [['numero' => 1, 'instruccion' => 'Confitar.']],
        ]));

        $ordenadas = $this->recetas->listar(1, 10, null, null, null, 'activas', 'titulo', 'ASC');

        self::assertSame('Ajo confitado', $ordenadas['datos'][0]->titulo, 'No ordenó por título');
    }

    public function test_actualizar_imagen_marca_y_libera_el_uso(): void
    {
        $id = $this->recetas->crear(RecetaDatos::fromArray([
            'titulo' => 'Receta con imagen',
            'ingredientes' => [['nombre' => 'Sal', 'texto_original' => 'Sal']],
            'pasos' => [['numero' => 1, 'instruccion' => 'Probar.']],
        ]));
        $imagenPrueba = '/imagenes/aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa.webp';

        self::assertTrue($this->recetas->actualizarImagen($id, $imagenPrueba), 'No asignó la imagen de prueba');
        self::assertTrue($this->recetas->imagenEnUso($imagenPrueba), 'No detectó una imagen en uso');

        self::assertTrue($this->recetas->actualizarImagen($id, null), 'No eliminó la referencia a la imagen');
        self::assertFalse($this->recetas->imagenEnUso($imagenPrueba), 'Mantuvo la imagen como utilizada');
    }

    public function test_solo_elimina_definitivamente_recetas_archivadas_y_en_cascada(): void
    {
        $id = $this->recetas->crear(RecetaDatos::fromArray([
            'titulo' => 'Receta eliminable',
            'ingredientes' => [['nombre' => 'Sal', 'texto_original' => 'Sal']],
            'pasos' => [['numero' => 1, 'instruccion' => 'Probar.']],
        ]));

        self::assertFalse($this->recetas->eliminarArchivada($id), 'Eliminó una receta activa');
        self::assertTrue($this->recetas->cambiarArchivado($id, true), 'No archivó la receta eliminable');
        self::assertTrue($this->recetas->eliminarArchivada($id), 'No eliminó la receta archivada');
        self::assertNull($this->recetas->obtenerPorId($id, true), 'La receta eliminada sigue existiendo');

        $consulta = $this->pdo->query('SELECT COUNT(*) FROM receta_ingredientes WHERE receta_id = ' . $id);
        self::assertNotFalse($consulta, 'No se pudo comprobar las relaciones de la receta eliminada');
        $relacionesEliminadas = (int) $consulta->fetchColumn();
        self::assertSame(0, $relacionesEliminadas, 'No eliminó en cascada las relaciones de la receta');
    }
}
