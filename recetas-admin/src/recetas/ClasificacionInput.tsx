import { useEffect, useState } from 'react';
import { AutocompleteArrayInput, useNotify } from 'react-admin';

interface TaxonomiaApi {
  nombre: string;
}

interface OpcionTaxonomia {
  id: string;
  name: string;
}

interface ClasificacionInputProps {
  source: 'categorias' | 'etiquetas';
  label: string;
  endpoint: '/api/categorias' | '/api/etiquetas';
  nombreSingular: string;
}

export function ClasificacionInput({
  source,
  label,
  endpoint,
  nombreSingular,
}: ClasificacionInputProps) {
  const notify = useNotify();
  const [choices, setChoices] = useState<OpcionTaxonomia[]>([]);
  const [isLoading, setIsLoading] = useState(true);

  useEffect(() => {
    const controller = new AbortController();

    fetch(endpoint, { signal: controller.signal, credentials: 'include' })
      .then(async (response) => {
        if (!response.ok) throw new Error('No se pudo cargar la clasificación');
        return response.json() as Promise<TaxonomiaApi[]>;
      })
      .then((taxonomias) => {
        setChoices(taxonomias.map(({ nombre }) => ({ id: nombre, name: nombre })));
      })
      .catch((error: unknown) => {
        if (error instanceof DOMException && error.name === 'AbortError') return;
        notify(`No se pudieron cargar las ${source}`, { type: 'warning' });
      })
      .finally(() => {
        if (!controller.signal.aborted) setIsLoading(false);
      });

    return () => controller.abort();
  }, [endpoint, notify, source]);

  const crearOpcion = async (texto?: string) => {
    const nombre = texto?.trim() ?? '';
    if (!nombre) return undefined;

    const existente = choices.find(
      (choice) => choice.name.localeCompare(nombre, 'es', { sensitivity: 'base' }) === 0,
    );
    if (existente) return existente;

    const nuevaOpcion = { id: nombre, name: nombre };
    setChoices((opciones) => [...opciones, nuevaOpcion]);
    await Promise.resolve();
    return nuevaOpcion;
  };

  return (
    <AutocompleteArrayInput
      className="clasificacion-selector"
      source={source}
      label={label}
      choices={choices}
      onCreate={crearOpcion}
      isLoading={isLoading}
      translateChoice={false}
      noOptionsText={`No hay ${source} coincidentes`}
      createLabel={`Escribe para buscar o crear una ${nombreSingular}`}
      openOnFocus
      filterSelectedOptions
      blurOnSelect={false}
      helperText={`Selecciona una existente o escribe una nueva ${nombreSingular}`}
      TextFieldProps={{
        placeholder: `Buscar o añadir ${nombreSingular}`,
      }}
      fullWidth
    />
  );
}
