<?php

declare(strict_types=1);

namespace App\Repository;

use App\Model\TaxonomiaAdmin;
use App\Model\TaxonomiaDatos;
use PDO;
use RuntimeException;

final class TaxonomiaRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /**
     * @return array{
     *     datos: list<TaxonomiaAdmin>,
     *     paginacion: array{pagina: int, por_pagina: int, total: int, total_paginas: int},
     * }
     */
    public function listar(string $tipo, int $pagina, int $porPagina, ?string $buscar, string $orden, string $direccion): array
    {
        [$tabla, $relacion, $clave] = $this->configuracion($tipo);
        $where = '';
        $parametros = [];
        if ($buscar !== null) {
            $where = ' WHERE t.nombre LIKE :buscar OR t.slug LIKE :buscar';
            $parametros['buscar'] = '%' . $buscar . '%';
        }

        $cuenta = $this->pdo->prepare('SELECT COUNT(*) FROM ' . $tabla . ' t' . $where);
        $cuenta->execute($parametros);
        $total = (int) $cuenta->fetchColumn();
        $totalPaginas = max(1, (int) ceil($total / $porPagina));
        $pagina = min($pagina, $totalPaginas);
        $columnaOrden = $orden === 'total_recetas' ? 'total_recetas' : ($orden === 'id' ? 't.id' : 't.nombre COLLATE NOCASE');
        $direccion = $direccion === 'DESC' ? 'DESC' : 'ASC';

        $sql = 'SELECT t.id, t.nombre, t.slug, COUNT(r.receta_id) AS total_recetas
                FROM ' . $tabla . ' t
                LEFT JOIN ' . $relacion . ' r ON r.' . $clave . ' = t.id' . $where . '
                GROUP BY t.id, t.nombre, t.slug
                ORDER BY ' . $columnaOrden . ' ' . $direccion . ', t.id ASC
                LIMIT :limite OFFSET :desplazamiento';
        $statement = $this->pdo->prepare($sql);
        foreach ($parametros as $nombre => $valor) {
            $statement->bindValue(':' . $nombre, $valor, PDO::PARAM_STR);
        }
        $statement->bindValue(':limite', $porPagina, PDO::PARAM_INT);
        $statement->bindValue(':desplazamiento', ($pagina - 1) * $porPagina, PDO::PARAM_INT);
        $statement->execute();

        return [
            'datos' => array_map(TaxonomiaAdmin::desdeFila(...), $statement->fetchAll()),
            'paginacion' => ['pagina' => $pagina, 'por_pagina' => $porPagina, 'total' => $total, 'total_paginas' => $totalPaginas],
        ];
    }

    public function obtener(string $tipo, int $id): ?TaxonomiaAdmin
    {
        [$tabla, $relacion, $clave] = $this->configuracion($tipo);
        $statement = $this->pdo->prepare(
            'SELECT t.id, t.nombre, t.slug, COUNT(r.receta_id) AS total_recetas
             FROM ' . $tabla . ' t
             LEFT JOIN ' . $relacion . ' r ON r.' . $clave . ' = t.id
             WHERE t.id = :id GROUP BY t.id, t.nombre, t.slug'
        );
        $statement->execute(['id' => $id]);
        $resultado = $statement->fetch();
        return $resultado === false ? null : TaxonomiaAdmin::desdeFila($resultado);
    }

    public function existeDuplicado(string $tipo, TaxonomiaDatos $datos, ?int $exceptoId = null): bool
    {
        [$tabla] = $this->configuracion($tipo);
        $sql = 'SELECT EXISTS(SELECT 1 FROM ' . $tabla . '
                WHERE (nombre = :nombre COLLATE NOCASE OR slug = :slug COLLATE NOCASE)';
        $parametros = ['nombre' => $datos->nombre, 'slug' => $datos->slug];
        if ($exceptoId !== null) {
            $sql .= ' AND id <> :id';
            $parametros['id'] = $exceptoId;
        }
        $statement = $this->pdo->prepare($sql . ')');
        $statement->execute($parametros);
        return (bool) $statement->fetchColumn();
    }

    public function crear(string $tipo, TaxonomiaDatos $datos): TaxonomiaAdmin
    {
        [$tabla] = $this->configuracion($tipo);
        $statement = $this->pdo->prepare('INSERT INTO ' . $tabla . ' (nombre, slug) VALUES (:nombre, :slug)');
        $statement->execute(['nombre' => $datos->nombre, 'slug' => $datos->slug]);

        $creado = $this->obtener($tipo, (int) $this->pdo->lastInsertId());
        if ($creado === null) {
            // No debería ocurrir nunca: acabamos de insertar la fila.
            throw new RuntimeException('No se pudo releer el término recién creado');
        }

        return $creado;
    }

    public function actualizar(string $tipo, int $id, TaxonomiaDatos $datos): ?TaxonomiaAdmin
    {
        [$tabla] = $this->configuracion($tipo);
        $statement = $this->pdo->prepare('UPDATE ' . $tabla . ' SET nombre = :nombre, slug = :slug WHERE id = :id');
        $statement->execute(['id' => $id, 'nombre' => $datos->nombre, 'slug' => $datos->slug]);
        return $this->obtener($tipo, $id);
    }

    public function eliminar(string $tipo, int $id): string
    {
        $actual = $this->obtener($tipo, $id);
        if ($actual === null) return 'no_encontrada';
        if ($actual->totalRecetas > 0) return 'en_uso';
        [$tabla] = $this->configuracion($tipo);
        $statement = $this->pdo->prepare('DELETE FROM ' . $tabla . ' WHERE id = :id');
        $statement->execute(['id' => $id]);
        return 'eliminada';
    }

    /** @return array{0: string, 1: string, 2: string} */
    private function configuracion(string $tipo): array
    {
        return $tipo === 'categorias'
            ? ['categorias', 'receta_categorias', 'categoria_id']
            : ['etiquetas', 'receta_etiquetas', 'etiqueta_id'];
    }
}
