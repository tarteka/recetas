import { Link } from 'react-router-dom';
import type { Paginacion } from '../api/recetas';

interface Props { paginacion: Paginacion; parametros: URLSearchParams }

function urlPagina(parametros: URLSearchParams, pagina: number) {
  const siguientes = new URLSearchParams(parametros);
  if (pagina === 1) siguientes.delete('pagina'); else siguientes.set('pagina', String(pagina));
  const query = siguientes.toString();
  return query ? `/?${query}` : '/';
}

export default function PaginacionRecetas({ paginacion, parametros }: Props) {
  if (paginacion.total_paginas <= 1) return null;
  const candidatas = [1, paginacion.pagina - 1, paginacion.pagina, paginacion.pagina + 1, paginacion.total_paginas];
  const paginas = [...new Set(candidatas.filter((pagina) => pagina >= 1 && pagina <= paginacion.total_paginas))].sort((a, b) => a - b);

  return <nav className="paginacion" aria-label="Paginación de recetas">
    {paginacion.pagina > 1 ? <Link className="paginacion__direccion" to={urlPagina(parametros, paginacion.pagina - 1)} rel="prev">← Anterior</Link> : <span className="paginacion__direccion paginacion__desactivada">← Anterior</span>}
    <ol>{paginas.map((pagina, indice) => <li key={pagina}>
      {indice > 0 && pagina - paginas[indice - 1] > 1 && <span className="paginacion__separador" aria-hidden="true">…</span>}
      <Link to={urlPagina(parametros, pagina)} aria-current={pagina === paginacion.pagina ? 'page' : undefined} aria-label={`Página ${pagina}`}>{pagina}</Link>
    </li>)}</ol>
    {paginacion.pagina < paginacion.total_paginas ? <Link className="paginacion__direccion" to={urlPagina(parametros, paginacion.pagina + 1)} rel="next">Siguiente →</Link> : <span className="paginacion__direccion paginacion__desactivada">Siguiente →</span>}
  </nav>;
}
