<?php

declare(strict_types=1);

namespace App\Repository;

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
    public function actualizarImagen(int $id, string $imagenUrl): bool
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

    public function listar(): array
    {
        // Devuelve las recetas ordenadas desde la más reciente.
        $statement = $this->pdo->query(
            'SELECT
                id,
                titulo,
                descripcion,
                imagen_url,
                fuente_nombre,
                raciones,
                tiempo_total_min,
                creado_en
            FROM recetas
            ORDER BY creado_en DESC, id DESC'
        );

        return $statement->fetchAll();
    }

    public function obtenerPorId(int $id): ?array
    {
        // Recupera la cabecera de la receta solicitada.
        $statement = $this->pdo->prepare(
            'SELECT *
            FROM recetas
            WHERE id = :id'
        );

        $statement->execute(['id' => $id]);

        $receta = $statement->fetch();

        if ($receta === false) {
            return null;
        }

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

    public function crear(array $datos): int
    {
        // Guarda una receta completa de forma atómica.
        try {
            $this->pdo->beginTransaction();

            $statement = $this->pdo->prepare(
                'INSERT INTO recetas (
                    titulo,
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
            $nombre = trim((string) $categoria);

            if ($nombre === '') {
                continue;
            }

            $slug = $this->crearSlug($nombre);

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
            $nombre = trim((string) $etiqueta);

            if ($nombre === '') {
                continue;
            }

            $slug = $this->crearSlug($nombre);

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

    private function crearSlug(string $texto): string
    {
        // Normaliza un nombre para utilizarlo como slug simple.
        $texto = mb_strtolower(trim($texto), 'UTF-8');

        $texto = iconv(
            'UTF-8',
            'ASCII//TRANSLIT//IGNORE',
            $texto
        ) ?: $texto;

        $texto = preg_replace('/[^a-z0-9]+/', '-', $texto) ?? '';

        return trim($texto, '-');
    }
}