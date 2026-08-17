import { useEffect, useState } from 'react';
import { Link, useParams } from 'react-router-dom';
import { ErrorApi, obtenerReceta, type RecetaDetalle } from '../api/recetas';
import EstadoPagina from '../components/EstadoPagina';
import ImagenReceta from '../components/ImagenReceta';

export default function PaginaDetalle() {
  const { slug } = useParams();
  const slugValido = typeof slug === 'string' && slug.trim() !== '';
  const [receta, setReceta] = useState<RecetaDetalle | null>(null);
  const [estado, setEstado] = useState<'cargando' | 'error' | 'no-encontrada'>(slugValido ? 'cargando' : 'no-encontrada');
  const [intento, setIntento] = useState(0);

  useEffect(() => {
    if (!slugValido) return;
    let activo = true;
    obtenerReceta(slug).then((datos) => { if (activo) setReceta(datos); })
      .catch((e: unknown) => { if (activo) setEstado(e instanceof ErrorApi && e.status === 404 ? 'no-encontrada' : 'error'); });
    return () => { activo = false; };
  }, [slugValido, intento, slug]);

  if (receta === null) return <main className="contenedor pagina-error"><Link className="volver" to="/">← Volver al recetario</Link><EstadoPagina
    titulo={estado === 'cargando' ? 'Cargando receta' : estado === 'no-encontrada' ? 'Receta no encontrada' : 'No pudimos cargar la receta'}
    descripcion={estado === 'no-encontrada' ? 'Puede que la receta ya no exista.' : estado === 'error' ? 'Comprueba la conexión e inténtalo de nuevo.' : undefined}
    cargando={estado === 'cargando'} icono={estado !== 'cargando' ? <span className="icono-estado">!</span> : undefined}
    onReintentar={estado === 'error' ? () => { setEstado('cargando'); setIntento((n) => n + 1); } : undefined}
  /></main>;

  return <main className="pagina-detalle"><div className="contenedor navegacion"><Link className="volver" to="/">← Volver al recetario</Link></div><article>
    <header className="contenedor detalle-cabecera"><div className="detalle-titulo">
      {receta.categorias.length > 0 && <ul className="chips" aria-label="Categorías">{receta.categorias.map((c) => <li key={c.slug}><Link to={'/?categoria=' + encodeURIComponent(c.slug)}>{c.nombre}</Link></li>)}</ul>}
      <h1>{receta.titulo}</h1>{receta.descripcion && <p>{receta.descripcion}</p>}
    </div><ImagenReceta key={receta.imagen_url ?? 'sin-imagen'} imagenUrl={receta.imagen_url} alt={receta.titulo} className="hero-receta" /></header>
    <div className="contenedor"><dl className="metadatos">
      {receta.raciones !== null && <div><dt>Raciones</dt><dd>{receta.raciones}</dd></div>}
      {receta.tiempo_preparacion_min !== null && <div><dt>Preparación</dt><dd>{receta.tiempo_preparacion_min} min</dd></div>}
      {receta.tiempo_coccion_min !== null && <div><dt>Cocción</dt><dd>{receta.tiempo_coccion_min} min</dd></div>}
      {receta.tiempo_total_min !== null && <div><dt>Tiempo total</dt><dd>{receta.tiempo_total_min} min</dd></div>}
    </dl>
    {receta.etiquetas.length > 0 && <ul className="chips chips-secundarios" aria-label="Etiquetas">{receta.etiquetas.map((e) => <li key={e.slug}><Link to={'/?etiqueta=' + encodeURIComponent(e.slug)}>{e.nombre}</Link></li>)}</ul>}
    <div className="detalle-contenido"><section><h2>Ingredientes</h2>{receta.ingredientes.length ? <ul className="ingredientes">{receta.ingredientes.map((i) => <li key={i.posicion}>{i.texto_original || [i.cantidad, i.unidad, i.nombre].filter(Boolean).join(' ')}</li>)}</ul> : <p>No hay ingredientes disponibles.</p>}</section>
    <section><h2>Elaboración</h2>{receta.pasos.length ? <ol className="pasos">{receta.pasos.map((p) => <li key={p.numero} value={p.numero}>{p.instruccion}</li>)}</ol> : <p>No hay pasos disponibles.</p>}</section></div>
    {receta.fuente_url && <footer className="fuente"><a href={receta.fuente_url} target="_blank" rel="noreferrer">Ver receta original{receta.fuente_nombre ? ` en ${receta.fuente_nombre}` : ''} ↗</a></footer>}
    </div></article></main>;
}
