<?php

declare(strict_types=1);

namespace App\Repository;

use App\Service\Slugger;
use PDO;
use Throwable;

final class RecetaRepository
{
    public function __construct(
        private readonly PDO $pdo
    ) {
    }

    /**
     * Actualiza la imagen almacenada de una receta.
     */
    public function actualizarImagen(int $id, ?string $imagenUrl): bool
    {
        $statement = $this->pdo->prepare(
            'UPDATE recetas
         SET imagen_url = :imagen_url,
             actualizado_en = CURRENT_TIMESTAMP
         WHERE id = :id'
        );

        $statement->execute([
            'id' => $id,
            'imagen_url' => $imagenUrl,
        ]);

        return $statement->rowCount() > 0;
    }

    public function imagenEnUso(string $imagenUrl): bool
    {
        $statement = $this->pdo->prepare(
            'SELECT EXISTS(SELECT 1 FROM recetas WHERE imagen_url = :imagen_url)'
        );
        $statement->execute(['imagen_url' => $imagenUrl]);

        return (bool) $statement->fetchColumn();
    }

    public function listar(
        int $pagina,
        int $porPagina,
        ?string $buscar,
        ?string $categoria,
        ?string $etiqueta,
        string $estado = 'activas',
        string $ordenar = 'creado_en',
        string $direccion = 'DESC'
    ): array {
        $condiciones = [];
        $parametros = [];

        if ($estado === 'archivadas') {
            $condiciones[] = 'r.archivada_en IS NOT NULL';
        } elseif ($estado !== 'todas') {
            $condiciones[] = 'r.archivada_en IS NULL';
        }

        if ($buscar !== null) {
            $condiciones[] = '(r.titulo LIKE :buscar OR r.descripcion LIKE :buscar)';
            $parametros['buscar'] = '%' . $buscar . '%';
        }
        if ($categoria !== null) {
            $condiciones[] = 'EXISTS (
                SELECT 1 FROM receta_categorias rc
                INNER JOIN categorias c ON c.id = rc.categoria_id
                WHERE rc.receta_id = r.id AND c.slug = :categoria
            )';
            $parametros['categoria'] = $categoria;
        }
        if ($etiqueta !== null) {
            $condiciones[] = 'EXISTS (
                SELECT 1 FROM receta_etiquetas re
                INNER JOIN etiquetas e ON e.id = re.etiqueta_id
                WHERE re.receta_id = r.id AND e.slug = :etiqueta
            )';
            $parametros['etiqueta'] = $etiqueta;
        }

        $where = $condiciones === []
            ? ''
            : ' WHERE ' . implode(' AND ', $condiciones);

        $columnasOrden = [
            'id' => 'r.id',
            'titulo' => 'r.titulo COLLATE NOCASE',
            'creado_en' => 'r.creado_en',
        ];
        $ordenSql = $columnasOrden[$ordenar] ?? $columnasOrden['creado_en'];
        $direccionSql = strtoupper($direccion) === 'ASC' ? 'ASC' : 'DESC';

        $statement = $this->pdo->prepare(
            'SELECT COUNT(*) FROM recetas r' . $where
        );
        $statement->execute($parametros);
        $total = (int) $statement->fetchColumn();
        $totalPaginas = max(1, (int) ceil($total / $porPagina));
        $pagina = min($pagina, $totalPaginas);

        $statement = $this->pdo->prepare(
            'SELECT
                r.id,
                r.titulo,
                r.slug,
                r.descripcion,
                r.imagen_url,
                r.fuente_nombre,
                r.raciones,
                r.tiempo_total_min,
                r.creado_en,
                r.archivada_en
            FROM recetas r' . $where . '
            ORDER BY ' . $ordenSql . ' ' . $direccionSql . ', r.id ' . $direccionSql . '
            LIMIT :limite OFFSET :desplazamiento'
        );
        foreach ($parametros as $nombre => $valor) {
            $statement->bindValue(':' . $nombre, $valor, PDO::PARAM_STR);
        }
        $statement->bindValue(':limite', $porPagina, PDO::PARAM_INT);
        $statement->bindValue(
            ':desplazamiento',
            ($pagina - 1) * $porPagina,
            PDO::PARAM_INT
        );
        $statement->execute();
        $recetas = $statement->fetchAll();

        $this->incorporarTaxonomias($recetas);

        return [
            'datos' => $recetas,
            'paginacion' => [
                'pagina' => $pagina,
                'por_pagina' => $porPagina,
                'total' => $total,
                'total_paginas' => $totalPaginas,
            ],
        ];
    }

    public function listarCategorias(): array
    {
        $statement = $this->pdo->query(
            'SELECT
                c.nombre,
                c.slug,
                COUNT(r.id) AS total_recetas
            FROM categorias c
            LEFT JOIN receta_categorias rc ON rc.categoria_id = c.id
            LEFT JOIN recetas r ON r.id = rc.receta_id AND r.archivada_en IS NULL
            GROUP BY c.id, c.nombre, c.slug
            HAVING COUNT(r.id) > 0
            ORDER BY c.nombre'
        );

        return $statement->fetchAll();
    }

    public function listarEtiquetas(): array
    {
        $statement = $this->pdo->query(
            'SELECT
                e.nombre,
                e.slug,
                COUNT(r.id) AS total_recetas
            FROM etiquetas e
            LEFT JOIN receta_etiquetas re ON re.etiqueta_id = e.id
            LEFT JOIN recetas r ON r.id = re.receta_id AND r.archivada_en IS NULL
            GROUP BY e.id, e.nombre, e.slug
            HAVING COUNT(r.id) > 0
            ORDER BY e.nombre'
        );

        return $statement->fetchAll();
    }

    public function listarCategoriasAdmin(): array
    {
        return $this->pdo->query(
            'SELECT c.nombre, c.slug, COUNT(rc.receta_id) AS total_recetas
             FROM categorias c
             INNER JOIN receta_categorias rc ON rc.categoria_id = c.id
             GROUP BY c.id, c.nombre, c.slug
             ORDER BY c.nombre'
        )->fetchAll();
    }

    public function listarEtiquetasAdmin(): array
    {
        return $this->pdo->query(
            'SELECT e.nombre, e.slug, COUNT(re.receta_id) AS total_recetas
             FROM etiquetas e
             INNER JOIN receta_etiquetas re ON re.etiqueta_id = e.id
             GROUP BY e.id, e.nombre, e.slug
             ORDER BY e.nombre'
        )->fetchAll();
    }

    private function incorporarTaxonomias(array &$recetas): void
    {
        if ($recetas === []) {
            return;
        }

        $recetasPorId = [];
        foreach ($recetas as $indice => $receta) {
            $recetas[$indice]['categorias'] = [];
            $recetas[$indice]['etiquetas'] = [];
            $recetasPorId[(int) $receta['id']] = $indice;
        }

        $ids = array_map(
            static fn(array $receta): int => (int) $receta['id'],
            $recetas
        );
        $marcadores = implode(',', array_fill(0, count($ids), '?'));

        $statement = $this->pdo->prepare(
            'SELECT rc.receta_id, c.nombre, c.slug
            FROM receta_categorias rc
            INNER JOIN categorias c ON c.id = rc.categoria_id
            WHERE rc.receta_id IN (' . $marcadores . ')
            ORDER BY c.nombre'
        );
        $statement->execute($ids);
        foreach ($statement->fetchAll() as $categoria) {
            $indice = $recetasPorId[(int) $categoria['receta_id']];
            $recetas[$indice]['categorias'][] = [
                'nombre' => $categoria['nombre'],
                'slug' => $categoria['slug'],
            ];
        }

        $statement = $this->pdo->prepare(
            'SELECT re.receta_id, e.nombre, e.slug
            FROM receta_etiquetas re
            INNER JOIN etiquetas e ON e.id = re.etiqueta_id
            WHERE re.receta_id IN (' . $marcadores . ')
            ORDER BY e.nombre'
        );
        $statement->execute($ids);
        foreach ($statement->fetchAll() as $etiqueta) {
            $indice = $recetasPorId[(int) $etiqueta['receta_id']];
            $recetas[$indice]['etiquetas'][] = [
                'nombre' => $etiqueta['nombre'],
                'slug' => $etiqueta['slug'],
            ];
        }
    }

    public function obtenerPorId(int $id, bool $incluirArchivadas = false): ?array
    {
        return $this->obtenerPorCondicion('id', $id, $incluirArchivadas);
    }

    public function obtenerPorSlug(string $slug, bool $incluirArchivadas = false): ?array
    {
        return $this->obtenerPorCondicion('slug', $slug, $incluirArchivadas);
    }

    private function obtenerPorCondicion(string $columna, int|string $valor, bool $incluirArchivadas): ?array
    {
        // Recupera la cabecera de la receta solicitada, por id o por slug.
        $statement = $this->pdo->prepare(
            'SELECT *
            FROM recetas
            WHERE ' . $columna . ' = :valor' . ($incluirArchivadas ? '' : ' AND archivada_en IS NULL')
        );

        $statement->execute(['valor' => $valor]);

        $receta = $statement->fetch();

        if ($receta === false) {
            return null;
        }

        $id = (int) $receta['id'];

        // Recupera los ingredientes manteniendo su orden original.
        $statement = $this->pdo->prepare(
            'SELECT
                i.nombre,
                ri.cantidad,
                ri.unidad,
                ri.notas,
                ri.texto_original,
                ri.posicion
            FROM receta_ingredientes ri
            LEFT JOIN ingredientes i
                ON i.id = ri.ingrediente_id
            WHERE ri.receta_id = :receta_id
            ORDER BY ri.posicion'
        );

        $statement->execute(['receta_id' => $id]);
        $receta['ingredientes'] = $statement->fetchAll();

        // Recupera los pasos de elaboración ordenados.
        $statement = $this->pdo->prepare(
            'SELECT
                numero,
                instruccion,
                imagen_url
            FROM receta_pasos
            WHERE receta_id = :receta_id
            ORDER BY numero'
        );

        $statement->execute(['receta_id' => $id]);
        $receta['pasos'] = $statement->fetchAll();

        // Recupera las categorías asociadas.
        $statement = $this->pdo->prepare(
            'SELECT
                c.nombre,
                c.slug
            FROM categorias c
            INNER JOIN receta_categorias rc
                ON rc.categoria_id = c.id
            WHERE rc.receta_id = :receta_id
            ORDER BY c.nombre'
        );

        $statement->execute(['receta_id' => $id]);
        $receta['categorias'] = $statement->fetchAll();

        // Recupera las etiquetas asociadas.
        $statement = $this->pdo->prepare(
            'SELECT
                e.nombre,
                e.slug
            FROM etiquetas e
            INNER JOIN receta_etiquetas re
                ON re.etiqueta_id = e.id
            WHERE re.receta_id = :receta_id
            ORDER BY e.nombre'
        );

        $statement->execute(['receta_id' => $id]);
        $receta['etiquetas'] = $statement->fetchAll();

        return $receta;
    }

    public function cambiarArchivado(int $id, bool $archivar): bool
    {
        $statement = $this->pdo->prepare(
            'UPDATE recetas
             SET archivada_en = ' . ($archivar ? 'CURRENT_TIMESTAMP' : 'NULL') . ',
                 actualizado_en = CURRENT_TIMESTAMP
             WHERE id = :id'
        );
        $statement->execute(['id' => $id]);

        return $statement->rowCount() > 0;
    }

    public function eliminarArchivada(int $id): bool
    {
        $statement = $this->pdo->prepare(
            'DELETE FROM recetas WHERE id = :id AND archivada_en IS NOT NULL'
        );
        $statement->execute(['id' => $id]);

        return $statement->rowCount() > 0;
    }

    public function crear(array $datos): int
    {
        // Guarda una receta completa de forma atómica.
        try {
            $this->pdo->beginTransaction();

            $statement = $this->pdo->prepare(
                'INSERT INTO recetas (
                    titulo,
                    slug,
                    descripcion,
                    fuente_url,
                    fuente_nombre,
                    imagen_url,
                    raciones,
                    tiempo_preparacion_min,
                    tiempo_coccion_min,
                    tiempo_total_min
                ) VALUES (
                    :titulo,
                    :slug,
                    :descripcion,
                    :fuente_url,
                    :fuente_nombre,
                    :imagen_url,
                    :raciones,
                    :tiempo_preparacion_min,
                    :tiempo_coccion_min,
                    :tiempo_total_min
                )'
            );

            $statement->execute([
                'titulo' => $datos['titulo'],
                'slug' => $this->resolverSlug($datos),
                'descripcion' => $datos['descripcion'] ?? null,
                'fuente_url' => $datos['fuente_url'] ?? null,
                'fuente_nombre' => $datos['fuente_nombre'] ?? null,
                'imagen_url' => $datos['imagen_url'] ?? null,
                'raciones' => $datos['raciones'] ?? null,
                'tiempo_preparacion_min' => $datos['tiempo_preparacion_min'] ?? null,
                'tiempo_coccion_min' => $datos['tiempo_coccion_min'] ?? null,
                'tiempo_total_min' => $datos['tiempo_total_min'] ?? null,
            ]);

            $recetaId = (int) $this->pdo->lastInsertId();

            $this->guardarIngredientes(
                $recetaId,
                $datos['ingredientes'] ?? []
            );

            $this->guardarPasos(
                $recetaId,
                $datos['pasos'] ?? []
            );

            $this->guardarCategorias(
                $recetaId,
                $datos['categorias'] ?? []
            );

            $this->guardarEtiquetas(
                $recetaId,
                $datos['etiquetas'] ?? []
            );

            $this->pdo->commit();

            return $recetaId;
        } catch (Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            throw $exception;
        }
    }

    public function actualizar(int $id, array $datos): bool
    {
        $statement = $this->pdo->prepare('SELECT 1 FROM recetas WHERE id = :id');
        $statement->execute(['id' => $id]);

        if ($statement->fetchColumn() === false) {
            return false;
        }

        try {
            $this->pdo->beginTransaction();

            $statement = $this->pdo->prepare(
                'UPDATE recetas SET
                    titulo = :titulo,
                    slug = :slug,
                    descripcion = :descripcion,
                    fuente_url = :fuente_url,
                    fuente_nombre = :fuente_nombre,
                    imagen_url = :imagen_url,
                    raciones = :raciones,
                    tiempo_preparacion_min = :tiempo_preparacion_min,
                    tiempo_coccion_min = :tiempo_coccion_min,
                    tiempo_total_min = :tiempo_total_min,
                    actualizado_en = CURRENT_TIMESTAMP
                WHERE id = :id'
            );
            $statement->execute([
                'id' => $id,
                'titulo' => trim((string) $datos['titulo']),
                'slug' => $this->resolverSlug($datos, $id),
                'descripcion' => $datos['descripcion'] ?? null,
                'fuente_url' => $datos['fuente_url'] ?? null,
                'fuente_nombre' => $datos['fuente_nombre'] ?? null,
                'imagen_url' => $datos['imagen_url'] ?? null,
                'raciones' => $datos['raciones'] ?? null,
                'tiempo_preparacion_min' => $datos['tiempo_preparacion_min'] ?? null,
                'tiempo_coccion_min' => $datos['tiempo_coccion_min'] ?? null,
                'tiempo_total_min' => $datos['tiempo_total_min'] ?? null,
            ]);

            foreach (['receta_ingredientes', 'receta_pasos', 'receta_categorias', 'receta_etiquetas'] as $tabla) {
                $statement = $this->pdo->prepare('DELETE FROM ' . $tabla . ' WHERE receta_id = :id');
                $statement->execute(['id' => $id]);
            }

            $this->guardarIngredientes($id, $datos['ingredientes']);
            $this->guardarPasos($id, $datos['pasos']);
            $this->guardarCategorias($id, $datos['categorias'] ?? []);
            $this->guardarEtiquetas($id, $datos['etiquetas'] ?? []);

            $this->pdo->commit();
            return true;
        } catch (Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }
    }

    private function guardarIngredientes(
        int $recetaId,
        array $ingredientes
    ): void {
        // Guarda los ingredientes y mantiene un catálogo sin duplicados.
        foreach ($ingredientes as $posicion => $ingrediente) {
            $statement = $this->pdo->prepare(
                'INSERT INTO ingredientes (nombre)
                 VALUES (:nombre)
                 ON CONFLICT(nombre) DO NOTHING'
            );

            $statement->execute([
                'nombre' => $ingrediente['nombre'],
            ]);

            $statement = $this->pdo->prepare(
                'SELECT id
                 FROM ingredientes
                 WHERE nombre = :nombre'
            );

            $statement->execute([
                'nombre' => $ingrediente['nombre'],
            ]);

            $ingredienteId = (int) $statement->fetchColumn();

            $statement = $this->pdo->prepare(
                'INSERT INTO receta_ingredientes (
                    receta_id,
                    ingrediente_id,
                    cantidad,
                    unidad,
                    notas,
                    texto_original,
                    posicion
                ) VALUES (
                    :receta_id,
                    :ingrediente_id,
                    :cantidad,
                    :unidad,
                    :notas,
                    :texto_original,
                    :posicion
                )'
            );

            $statement->execute([
                'receta_id' => $recetaId,
                'ingrediente_id' => $ingredienteId,
                'cantidad' => $ingrediente['cantidad'] ?? null,
                'unidad' => $ingrediente['unidad'] ?? null,
                'notas' => $ingrediente['notas'] ?? null,
                'texto_original' => $ingrediente['texto_original'],
                'posicion' => $posicion + 1,
            ]);
        }
    }

    private function guardarPasos(
        int $recetaId,
        array $pasos
    ): void {
        // Guarda los pasos de elaboración respetando su orden.
        $statement = $this->pdo->prepare(
            'INSERT INTO receta_pasos (
                receta_id,
                numero,
                instruccion,
                imagen_url
            ) VALUES (
                :receta_id,
                :numero,
                :instruccion,
                :imagen_url
            )'
        );

        foreach ($pasos as $posicion => $paso) {
            $statement->execute([
                'receta_id' => $recetaId,
                'numero' => $paso['numero'] ?? $posicion + 1,
                'instruccion' => $paso['instruccion'],
                'imagen_url' => $paso['imagen_url'] ?? null,
            ]);
        }
    }

    private function guardarCategorias(
        int $recetaId,
        array $categorias
    ): void {
        // Crea categorías inexistentes y las relaciona con la receta.
        foreach ($categorias as $categoria) {
            $nombre = trim((string) (is_array($categoria) ? ($categoria['nombre'] ?? '') : $categoria));

            if ($nombre === '') {
                continue;
            }

            $slug = Slugger::generar($nombre);

            $statement = $this->pdo->prepare(
                'INSERT INTO categorias (nombre, slug)
                VALUES (:nombre, :slug)
                ON CONFLICT(nombre) DO NOTHING'
            );

            $statement->execute([
                'nombre' => $nombre,
                'slug' => $slug,
            ]);

            $statement = $this->pdo->prepare(
                'SELECT id
                FROM categorias
                WHERE nombre = :nombre'
            );

            $statement->execute([
                'nombre' => $nombre,
            ]);

            $categoriaId = (int) $statement->fetchColumn();

            $statement = $this->pdo->prepare(
                'INSERT OR IGNORE INTO receta_categorias (
                    receta_id,
                    categoria_id
                ) VALUES (
                    :receta_id,
                    :categoria_id
                )'
            );

            $statement->execute([
                'receta_id' => $recetaId,
                'categoria_id' => $categoriaId,
            ]);
        }
    }

    private function guardarEtiquetas(
        int $recetaId,
        array $etiquetas
    ): void {
        // Crea etiquetas inexistentes y las relaciona con la receta.
        foreach ($etiquetas as $etiqueta) {
            $nombre = trim((string) (is_array($etiqueta) ? ($etiqueta['nombre'] ?? '') : $etiqueta));

            if ($nombre === '') {
                continue;
            }

            $slug = Slugger::generar($nombre);

            $statement = $this->pdo->prepare(
                'INSERT INTO etiquetas (nombre, slug)
                VALUES (:nombre, :slug)
                ON CONFLICT(nombre) DO NOTHING'
            );

            $statement->execute([
                'nombre' => $nombre,
                'slug' => $slug,
            ]);

            $statement = $this->pdo->prepare(
                'SELECT id
                FROM etiquetas
                WHERE nombre = :nombre'
            );

            $statement->execute([
                'nombre' => $nombre,
            ]);

            $etiquetaId = (int) $statement->fetchColumn();

            $statement = $this->pdo->prepare(
                'INSERT OR IGNORE INTO receta_etiquetas (
                    receta_id,
                    etiqueta_id
                ) VALUES (
                    :receta_id,
                    :etiqueta_id
                )'
            );

            $statement->execute([
                'receta_id' => $recetaId,
                'etiqueta_id' => $etiquetaId,
            ]);
        }
    }

    /**
     * Resuelve el slug de una receta a partir de los datos recibidos:
     * usa el slug indicado explícitamente o lo deriva del título, y
     * garantiza que sea único añadiendo un sufijo numérico si hace falta.
     */
    private function resolverSlug(array $datos, ?int $exceptoId = null): string
    {
        $entrada = trim((string) ($datos['slug'] ?? ''));
        $base = Slugger::generar($entrada !== '' ? $entrada : (string) $datos['titulo']);

        if ($base === '') {
            $base = 'receta';
        }

        $slug = $base;
        $sufijo = 2;
        while ($this->slugEnUso($slug, $exceptoId)) {
            $slug = $base . '-' . $sufijo;
            $sufijo++;
        }

        return $slug;
    }

    private function slugEnUso(string $slug, ?int $exceptoId): bool
    {
        $sql = 'SELECT EXISTS(SELECT 1 FROM recetas WHERE slug = :slug'
            . ($exceptoId !== null ? ' AND id <> :id' : '')
            . ')';

        $statement = $this->pdo->prepare($sql);
        $parametros = ['slug' => $slug];
        if ($exceptoId !== null) {
            $parametros['id'] = $exceptoId;
        }
        $statement->execute($parametros);

        return (bool) $statement->fetchColumn();
    }
}
