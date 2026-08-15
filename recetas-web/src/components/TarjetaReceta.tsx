import { Link } from 'react-router-dom';
import type { RecetaResumen } from '../api/recetas';
import ImagenReceta from './ImagenReceta';

export default function TarjetaReceta({ receta }: { receta: RecetaResumen }) {
  return <article className="tarjeta">
    <Link className="tarjeta__principal" to={`/recetas/${receta.id}`}>
      <ImagenReceta imagenUrl={receta.imagen_url} alt={receta.titulo} />
      <div className="tarjeta__contenido"><h2>{receta.titulo}</h2>
        {receta.tiempo_total_min !== null && <p className="tiempo">◷ {receta.tiempo_total_min} min</p>}
      </div>
    </Link>
    {receta.categorias.length > 0 && <ul className="chips tarjeta__categorias" aria-label="Categorías">{receta.categorias.map((c) => <li key={c.slug}><Link to={`/?categoria=${encodeURIComponent(c.slug)}`}>{c.nombre}</Link></li>)}</ul>}
  </article>;
}
