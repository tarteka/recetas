import { Link } from 'react-router-dom';
import type { TaxonomiaResumen } from '../api/recetas';

interface Props { categorias: TaxonomiaResumen[]; categoriaActiva: string | null; parametros: URLSearchParams }

function urlCategoria(parametros: URLSearchParams, slug?: string) {
  const siguientes = new URLSearchParams(parametros);
  siguientes.delete('pagina');
  if (slug) siguientes.set('categoria', slug); else siguientes.delete('categoria');
  const query = siguientes.toString();
  return query ? `/?${query}` : '/';
}

export default function SelectorCategorias({ categorias, categoriaActiva, parametros }: Props) {
  return <nav className="selector-categorias" aria-label="Selección rápida por categoría">
    <h2>Explorar por categoría</h2><ul>
      <li><Link to={urlCategoria(parametros)} aria-current={categoriaActiva === null ? 'page' : undefined}>Todas</Link></li>
      {categorias.map((categoria) => <li key={categoria.slug}><Link to={urlCategoria(parametros, categoria.slug)} aria-current={categoriaActiva === categoria.slug ? 'page' : undefined}>{categoria.nombre} <small>({categoria.total_recetas})</small></Link></li>)}
    </ul>
  </nav>;
}
