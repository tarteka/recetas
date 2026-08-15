import type { RecetaResumen } from '../api/recetas';
import TarjetaReceta from './TarjetaReceta';

export default function ListaRecetas({ recetas }: { recetas: RecetaResumen[] }) {
  return <section aria-labelledby="titulo-listado"><h2 id="titulo-listado" className="solo-lectores">Listado de recetas</h2><div className="grid-recetas">{recetas.map((r) => <TarjetaReceta key={r.id} receta={r} />)}</div></section>;
}
