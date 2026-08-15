import { useEffect, useMemo, useState } from 'react';
import { obtenerRecetas, type RecetaResumen } from '../api/recetas';
import BuscadorRecetas from '../components/BuscadorRecetas';
import EstadoPagina from '../components/EstadoPagina';
import ListaRecetas from '../components/ListaRecetas';

export default function PaginaListado() {
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

  const filtradas = useMemo(() => {
    const termino = busqueda.trim().toLocaleLowerCase('es');
    return termino ? recetas.filter((r) => `${r.titulo} ${r.descripcion ?? ''}`.toLocaleLowerCase('es').includes(termino)) : recetas;
  }, [busqueda, recetas]);

  return <main className="contenedor pagina-listado">
    <header className="cabecera"><p className="ceja">By Sergio Moreno</p><h1>Mi Recetario</h1><p>Un compendio de mis recetas favoritas.</p></header>
    {estado === 'listo' && recetas.length > 0 && <BuscadorRecetas valor={busqueda} onChange={setBusqueda} />}
    {estado === 'cargando' ? <EstadoPagina titulo="Cargando recetas" descripcion="Estamos preparando tu recetario." cargando />
      : estado === 'error' ? <EstadoPagina titulo="No pudimos cargar las recetas" descripcion="Comprueba la conexión e inténtalo de nuevo." icono={<span className="icono-estado">!</span>} onReintentar={() => { setEstado('cargando'); setIntento((n) => n + 1); }} />
      : recetas.length === 0 ? <EstadoPagina titulo="Tu recetario está vacío" descripcion="Cuando añadas recetas aparecerán aquí." icono={<span className="icono-estado">◇</span>} />
      : filtradas.length === 0 ? <EstadoPagina titulo="No hay resultados" descripcion={`No encontramos recetas que coincidan con “${busqueda.trim()}”.`} icono={<span className="icono-estado">⌕</span>} />
      : <ListaRecetas recetas={filtradas} />}
  </main>;
}
