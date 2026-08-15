import { useEffect, useState } from 'react';
import { obtenerRecetas, type RecetaResumen } from './api/recetas';
import DetalleReceta from './components/DetalleReceta';

function App() {
  const [recetas, setRecetas] = useState<RecetaResumen[]>([]);
  const [cargando, setCargando] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [recetaSeleccionadaId, setRecetaSeleccionadaId] = useState<number | null>(null);

  useEffect(() => {
    // Carga las recetas una sola vez al montar la aplicación.
    obtenerRecetas()
      .then(setRecetas)
      .catch(() => setError('No se pudieron cargar las recetas'))
      .finally(() => setCargando(false));
  }, []);

  if (recetaSeleccionadaId !== null) {
    return (
      <DetalleReceta
        recetaId={recetaSeleccionadaId}
        onVolver={() => setRecetaSeleccionadaId(null)}
      />
    );
  }

  if (cargando) {
    return <main>Cargando recetas...</main>;
  }

  if (error !== null) {
    return <main>{error}</main>;
  }

  return (
    <main>
      <h1>Recetas</h1>

      {recetas.length === 0 ? (
        <p>No hay recetas guardadas.</p>
      ) : (
        <ul>
          {recetas.map((receta) => (
            <li key={receta.id}>
              <button type="button" onClick={() => setRecetaSeleccionadaId(receta.id)}>
                <strong>{receta.titulo}</strong>

                {receta.tiempo_total_min !== null && <span> · {receta.tiempo_total_min} min</span>}
              </button>
            </li>
          ))}
        </ul>
      )}
    </main>
  );
}

export default App;
