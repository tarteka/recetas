import { useEffect, useMemo, useState } from 'react';
import { useSearchParams } from 'react-router-dom';
import { obtenerRecetas, type CategoriaReceta, type RecetaResumen } from '../api/recetas';
import BuscadorRecetas from '../components/BuscadorRecetas';
import EstadoPagina from '../components/EstadoPagina';
import ListaRecetas from '../components/ListaRecetas';
import SelectorCategorias from '../components/SelectorCategorias';

export default function PaginaListado() {
  const [parametros] = useSearchParams();
  const categoriaActiva = parametros.get('categoria');
  const [recetas, setRecetas] = useState<RecetaResumen[]>([]);
  const [busqueda, setBusqueda] = useState('');
  const [estado, setEstado] = useState<'cargando' | 'error' | 'listo'>('cargando');
  const [intento, setIntento] = useState(0);

  useEffect(() => {
    let activo = true;
    obtenerRecetas().then((datos) => { if (activo) { setRecetas(datos); setEstado('listo'); } })
      .catch(() => { if (activo) setEstado('error'); });
    return () => { activo = false; };
  }, [intento]);

  const categorias = useMemo(() => {
    const unicas = new Map<string, CategoriaReceta>();
    recetas.forEach((receta) => receta.categorias.forEach((categoria) => unicas.set(categoria.slug, categoria)));
    return [...unicas.values()].sort((a, b) => a.nombre.localeCompare(b.nombre, 'es'));
  }, [recetas]);

  const filtradas = useMemo(() => {
    const termino = busqueda.trim().toLocaleLowerCase('es');
    return recetas.filter((receta) => {
      const coincideCategoria = categoriaActiva === null || receta.categorias.some((categoria) => categoria.slug === categoriaActiva);
      const coincideTexto = termino === '' || `${receta.titulo} ${receta.descripcion ?? ''}`.toLocaleLowerCase('es').includes(termino);
      return coincideCategoria && coincideTexto;
    });
  }, [busqueda, categoriaActiva, recetas]);

  const mostrarControles = estado === 'listo' && recetas.length > 0;

  return <main className="contenedor pagina-listado">
    <header className="cabecera"><p className="ceja">By Sergio Moreno</p><h1>Mi Recetario</h1><p>Un compendio de mis recetas favoritas.</p></header>
    <div className={`listado-layout${mostrarControles ? '' : ' listado-layout--sin-sidebar'}`}>
      {mostrarControles && <aside className="sidebar-listado" aria-label="Buscar y filtrar recetas">
        <BuscadorRecetas valor={busqueda} onChange={setBusqueda} />
        <SelectorCategorias categorias={categorias} categoriaActiva={categoriaActiva} />
      </aside>}
      <div className="listado-resultados">
        {estado === 'cargando' ? <EstadoPagina titulo="Cargando recetas" descripcion="Estamos preparando tu recetario." cargando />
          : estado === 'error' ? <EstadoPagina titulo="No pudimos cargar las recetas" descripcion="Comprueba la conexión e inténtalo de nuevo." icono={<span className="icono-estado">!</span>} onReintentar={() => { setEstado('cargando'); setIntento((n) => n + 1); }} />
          : recetas.length === 0 ? <EstadoPagina titulo="Tu recetario está vacío" descripcion="Cuando añadas recetas aparecerán aquí." icono={<span className="icono-estado">◇</span>} />
          : filtradas.length === 0 ? <EstadoPagina titulo="No hay resultados" descripcion="No encontramos recetas con los criterios seleccionados." icono={<span className="icono-estado">⌕</span>} />
          : <ListaRecetas recetas={filtradas} />}
      </div>
    </div>
  </main>;
}
