<?php

declare(strict_types=1);

namespace App\Model;

/**
 * Nombre y slug ya validados de una categoría o etiqueta (lado de
 * escritura, payload de creación/actualización). A diferencia de
 * RecetaDatos, aquí no hace falta un constructor privado + fromArray():
 * App\Controller\TaxonomiaController::datos() ya es el único sitio que
 * valida y construye a la vez (no hay un TaxonomiaValidator separado del
 * que haya que "confiar" en un punto posterior). No hereda de Taxonomia
 * (lado de lectura) — igual que RecetaDatos no hereda de Receta.
 */
final class TaxonomiaDatos
{
    public function __construct(
        public readonly string $nombre,
        public readonly string $slug,
    ) {
    }
}
