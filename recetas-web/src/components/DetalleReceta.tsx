import { useEffect, useState } from 'react';
import { obtenerReceta, type RecetaDetalle } from '../api/recetas';

interface DetalleRecetaProps {
  recetaId: number;
  onVolver: () => void;
}

function DetalleReceta({ recetaId, onVolver }: DetalleRecetaProps) {
  const [receta, setReceta] = useState<RecetaDetalle | null>(null);
  const [cargando, setCargando] = useState(true);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    let activo = true;

    setCargando(true);
    setError(null);
    setReceta(null);

    obtenerReceta(recetaId)
      .then((recetaCargada) => {
        if (activo) {
          setReceta(recetaCargada);
        }
      })
      .catch(() => {
        if (activo) {
          setError('No se pudo cargar la receta');
        }
      })
      .finally(() => {
        if (activo) {
          setCargando(false);
        }
      });

    return () => {
      activo = false;
    };
  }, [recetaId]);

  if (cargando) {
    return (
      <main className="detalle-receta">
        <button type="button" onClick={onVolver}>← Volver</button>
        <p>Cargando receta...</p>
      </main>
    );
  }

  if (error !== null || receta === null) {
    return (
      <main className="detalle-receta">
        <button type="button" onClick={onVolver}>← Volver</button>
        <p role="alert">{error ?? 'No se encontró la receta'}</p>
      </main>
    );
  }

  const ingredientes = [...receta.ingredientes].sort((a, b) => a.posicion - b.posicion);
  const pasos = [...receta.pasos].sort((a, b) => a.numero - b.numero);

  return (
    <main className="detalle-receta">
      <button type="button" onClick={onVolver}>← Volver</button>

      <article>
        <header>
          <h1>{receta.titulo}</h1>
          {receta.descripcion !== null && <p>{receta.descripcion}</p>}
          {receta.imagen_url !== null && <img src={receta.imagen_url} alt={receta.titulo} />}
        </header>

        <dl className="datos-receta">
          {receta.raciones !== null && <><dt>Raciones</dt><dd>{receta.raciones}</dd></>}
          {receta.tiempo_preparacion_min !== null && <><dt>Preparación</dt><dd>{receta.tiempo_preparacion_min} min</dd></>}
          {receta.tiempo_coccion_min !== null && <><dt>Cocción</dt><dd>{receta.tiempo_coccion_min} min</dd></>}
          {receta.tiempo_total_min !== null && <><dt>Tiempo total</dt><dd>{receta.tiempo_total_min} min</dd></>}
        </dl>

        {receta.categorias.length > 0 && (
          <section className="categorias-receta">
            <h2>Categorías</h2>
            <ul>{receta.categorias.map((categoria) => <li key={categoria.slug}>{categoria.nombre}</li>)}</ul>
          </section>
        )}

        {receta.etiquetas.length > 0 && (
          <section className="etiquetas-receta">
            <h2>Etiquetas</h2>
            <ul>{receta.etiquetas.map((etiqueta) => <li key={etiqueta.slug}>{etiqueta.nombre}</li>)}</ul>
          </section>
        )}

        <section className="ingredientes-receta">
          <h2>Ingredientes</h2>
          {ingredientes.length === 0 ? (
            <p>No hay ingredientes disponibles.</p>
          ) : (
            <ul>
              {ingredientes.map((ingrediente) => (
                <li key={ingrediente.posicion}>
                  {ingrediente.texto_original || [ingrediente.cantidad, ingrediente.unidad, ingrediente.nombre].filter(Boolean).join(' ')}
                </li>
              ))}
            </ul>
          )}
        </section>

        <section className="pasos-receta">
          <h2>Preparación</h2>
          {pasos.length === 0 ? (
            <p>No hay pasos disponibles.</p>
          ) : (
            <ol>
              {pasos.map((paso) => (
                <li key={paso.numero} value={paso.numero}>{paso.instruccion}</li>
              ))}
            </ol>
          )}
        </section>

        {receta.fuente_url !== null && (
          <p>
            <a href={receta.fuente_url} target="_blank" rel="noreferrer">
              Ver receta original{receta.fuente_nombre !== null ? ` en ${receta.fuente_nombre}` : ''}
            </a>
          </p>
        )}
      </article>
    </main>
  );
}

export default DetalleReceta;
