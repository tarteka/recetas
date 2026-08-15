import { Link } from 'react-router-dom';
import type { EtiquetaReceta } from '../api/recetas';

export interface EtiquetaFrecuente extends EtiquetaReceta {
  frecuencia: number;
  nivel: number;
}

interface Props {
  etiquetas: EtiquetaFrecuente[];
  etiquetaActiva: string | null;
  categoriaActiva: string | null;
}

function crearUrl(categoria: string | null, etiqueta: string | null) {
  const parametros = new URLSearchParams();
  if (categoria) parametros.set('categoria', categoria);
  if (etiqueta) parametros.set('etiqueta', etiqueta);
  const query = parametros.toString();
  return query ? `/?${query}` : '/';
}

export default function NubeEtiquetas({ etiquetas, etiquetaActiva, categoriaActiva }: Props) {
  if (etiquetas.length === 0) return null;

  return <nav className="nube-etiquetas" aria-label="Filtrar por etiqueta">
    <h2>Etiquetas</h2>
    <div className="nube-etiquetas__lista">
      {etiquetaActiva && <Link className="nube-etiquetas__todas" to={crearUrl(categoriaActiva, null)}>Todas</Link>}
      {etiquetas.map((etiqueta) => <Link
        key={etiqueta.slug}
        className={`nube-etiquetas__nivel-${etiqueta.nivel}`}
        to={crearUrl(categoriaActiva, etiqueta.slug)}
        aria-current={etiquetaActiva === etiqueta.slug ? 'page' : undefined}
        title={`${etiqueta.frecuencia} ${etiqueta.frecuencia === 1 ? 'receta' : 'recetas'}`}
      >{etiqueta.nombre}</Link>)}
    </div>
  </nav>;
}
