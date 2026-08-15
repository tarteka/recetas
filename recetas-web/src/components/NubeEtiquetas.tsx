import { Link } from 'react-router-dom';
import type { TaxonomiaResumen } from '../api/recetas';

export interface EtiquetaNube extends TaxonomiaResumen { nivel: number }
interface Props { etiquetas: EtiquetaNube[]; etiquetaActiva: string | null; parametros: URLSearchParams }

function urlEtiqueta(parametros: URLSearchParams, slug?: string) {
  const siguientes = new URLSearchParams(parametros);
  siguientes.delete('pagina');
  if (slug) siguientes.set('etiqueta', slug); else siguientes.delete('etiqueta');
  const query = siguientes.toString();
  return query ? `/?${query}` : '/';
}

export default function NubeEtiquetas({ etiquetas, etiquetaActiva, parametros }: Props) {
  if (etiquetas.length === 0) return null;
  return <nav className="nube-etiquetas" aria-label="Filtrar por etiqueta"><h2>Etiquetas</h2><div className="nube-etiquetas__lista">
    {etiquetaActiva && <Link className="nube-etiquetas__todas" to={urlEtiqueta(parametros)}>Todas</Link>}
    {etiquetas.map((etiqueta) => <Link key={etiqueta.slug} className={`nube-etiquetas__nivel-${etiqueta.nivel}`} to={urlEtiqueta(parametros, etiqueta.slug)} aria-current={etiquetaActiva === etiqueta.slug ? 'page' : undefined} title={`${etiqueta.total_recetas} ${etiqueta.total_recetas === 1 ? 'receta' : 'recetas'}`}>{etiqueta.nombre}</Link>)}
  </div></nav>;
}
