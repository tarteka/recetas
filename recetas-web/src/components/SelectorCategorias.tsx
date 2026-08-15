import { Link } from 'react-router-dom';
import type { CategoriaReceta } from '../api/recetas';

interface Props {
  categorias: CategoriaReceta[];
  categoriaActiva: string | null;
}

export default function SelectorCategorias({ categorias, categoriaActiva }: Props) {
  return <nav className="selector-categorias" aria-label="Selección rápida por categoría">
    <h2>Explorar por categoría</h2>
    <ul>
      <li><Link to="/" aria-current={categoriaActiva === null ? 'page' : undefined}>Todas</Link></li>
      {categorias.map((categoria) => <li key={categoria.slug}>
        <Link to={`/?categoria=${encodeURIComponent(categoria.slug)}`} aria-current={categoriaActiva === categoria.slug ? 'page' : undefined}>{categoria.nombre}</Link>
      </li>)}
    </ul>
  </nav>;
}
