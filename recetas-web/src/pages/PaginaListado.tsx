import { useCallback, useEffect, useMemo, useState } from 'react';
import { useSearchParams } from 'react-router-dom';
import { obtenerCategorias, obtenerEtiquetas, obtenerRecetas, type RespuestaRecetas, type TaxonomiaResumen } from '../api/recetas';
import BuscadorRecetas from '../components/BuscadorRecetas';
import EstadoPagina from '../components/EstadoPagina';
import ListaRecetas from '../components/ListaRecetas';
import NubeEtiquetas, { type EtiquetaNube } from '../components/NubeEtiquetas';
import PaginacionRecetas from '../components/PaginacionRecetas';
import SelectorCategorias from '../components/SelectorCategorias';

const RECETAS_POR_PAGINA = 9;
interface ResultadoCargado { clave: string; respuesta: RespuestaRecetas }

export default function PaginaListado() {
  const [parametros, setParametros] = useSearchParams();
  const categoriaActiva = parametros.get('categoria');
  const etiquetaActiva = parametros.get('etiqueta');
  const buscar = parametros.get('buscar') ?? '';
  const paginaSolicitada = Number.parseInt(parametros.get('pagina') ?? '1', 10);
  const pagina = Number.isInteger(paginaSolicitada) && paginaSolicitada > 0 ? paginaSolicitada : 1;
  const claveConsulta = `${pagina}|${buscar}|${categoriaActiva ?? ''}|${etiquetaActiva ?? ''}`;
  const [resultado, setResultado] = useState<ResultadoCargado | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [categorias, setCategorias] = useState<TaxonomiaResumen[]>([]);
  const [etiquetasBase, setEtiquetasBase] = useState<TaxonomiaResumen[]>([]);
  const [intento, setIntento] = useState(0);

  useEffect(() => {
    const controlador = new AbortController();
    Promise.all([obtenerCategorias(controlador.signal), obtenerEtiquetas(controlador.signal)])
      .then(([nuevasCategorias, nuevasEtiquetas]) => { setCategorias(nuevasCategorias); setEtiquetasBase(nuevasEtiquetas); })
      .catch(() => { if (!controlador.signal.aborted) { setCategorias([]); setEtiquetasBase([]); } });
    return () => controlador.abort();
  }, []);

  useEffect(() => {
    const controlador = new AbortController();
    obtenerRecetas({ pagina, porPagina: RECETAS_POR_PAGINA, buscar: buscar.trim() || undefined, categoria: categoriaActiva ?? undefined, etiqueta: etiquetaActiva ?? undefined }, controlador.signal)
      .then((respuesta) => { setResultado({ clave: claveConsulta, respuesta }); setError(null); })
      .catch(() => { if (!controlador.signal.aborted) setError(claveConsulta); });
    return () => controlador.abort();
  }, [buscar, categoriaActiva, claveConsulta, etiquetaActiva, intento, pagina]);

  const etiquetas = useMemo<EtiquetaNube[]>(() => {
    if (etiquetasBase.length === 0) return [];
    const minimo = Math.min(...etiquetasBase.map((etiqueta) => etiqueta.total_recetas));
    const maximo = Math.max(...etiquetasBase.map((etiqueta) => etiqueta.total_recetas));
    return etiquetasBase.map((etiqueta) => ({
      ...etiqueta,
      nivel: maximo === minimo
        ? [...etiqueta.slug].reduce((total, caracter) => total + caracter.charCodeAt(0), 0) % 5
        : Math.round(((etiqueta.total_recetas - minimo) / (maximo - minimo)) * 4),
    }));
  }, [etiquetasBase]);

  const actualizarBusqueda = useCallback((valor: string) => {
    setParametros((actuales) => {
      const siguientes = new URLSearchParams(actuales);
      siguientes.delete('pagina');
      const termino = valor.trim();
      if (termino) siguientes.set('buscar', termino); else siguientes.delete('buscar');
      return siguientes;
    }, { replace: true });
  }, [setParametros]);

  const cargando = resultado?.clave !== claveConsulta && error !== claveConsulta;
  const respuesta = resultado?.clave === claveConsulta ? resultado.respuesta : null;
  const mostrarControles = categorias.length > 0 || etiquetas.length > 0 || respuesta !== null;

  return <main className="contenedor pagina-listado">
    <header className="cabecera"><p className="ceja">By Sergio Moreno</p><h1>Mi Recetario</h1><p>Un compendio de mis recetas favoritas.</p></header>
    <div className={`listado-layout${mostrarControles ? '' : ' listado-layout--sin-sidebar'}`}>
      {mostrarControles && <aside className="sidebar-listado" aria-label="Buscar y filtrar recetas">
        <BuscadorRecetas key={buscar} valorInicial={buscar} onBuscar={actualizarBusqueda} />
        <SelectorCategorias categorias={categorias} categoriaActiva={categoriaActiva} parametros={parametros} />
        <NubeEtiquetas etiquetas={etiquetas} etiquetaActiva={etiquetaActiva} parametros={parametros} />
      </aside>}
      <div className="listado-resultados" aria-busy={cargando}>
        {cargando ? <EstadoPagina titulo="Cargando recetas" descripcion="Estamos preparando esta página." cargando />
          : error === claveConsulta ? <EstadoPagina titulo="No pudimos cargar las recetas" descripcion="Comprueba la conexión e inténtalo de nuevo." icono={<span className="icono-estado">!</span>} onReintentar={() => { setError(null); setIntento((n) => n + 1); }} />
          : respuesta?.paginacion.total === 0 && !buscar && !categoriaActiva && !etiquetaActiva ? <EstadoPagina titulo="Tu recetario está vacío" descripcion="Cuando añadas recetas aparecerán aquí." icono={<span className="icono-estado">◇</span>} />
          : respuesta?.datos.length === 0 ? <EstadoPagina titulo="No hay resultados" descripcion="No encontramos recetas con los criterios seleccionados." icono={<span className="icono-estado">⌕</span>} />
          : respuesta && <><p className="resumen-resultados">{respuesta.paginacion.total} {respuesta.paginacion.total === 1 ? 'receta' : 'recetas'}</p><ListaRecetas recetas={respuesta.datos} /><PaginacionRecetas paginacion={respuesta.paginacion} parametros={parametros} /></>}
      </div>
    </div>
  </main>;
}
