import { useEffect, useState } from 'react';
import {
  Box,
  Button,
  Chip,
  Dialog,
  DialogActions,
  DialogContent,
  DialogTitle,
  Typography,
  useMediaQuery,
} from '@mui/material';
import ArchiveOutlinedIcon from '@mui/icons-material/ArchiveOutlined';
import RestartAltOutlinedIcon from '@mui/icons-material/RestartAltOutlined';
import type { Theme } from '@mui/material/styles';
import {
  AutocompleteInput,
  CreateButton,
  Datagrid,
  DateField,
  FilterButton,
  FunctionField,
  ImageField,
  List,
  NumberField,
  SelectInput,
  SimpleList,
  SortButton,
  TextField,
  TextInput,
  TopToolbar,
  useListContext,
  useNotify,
  useRefresh,
} from 'react-admin';
import type { RecetaResumenAdmin } from '../types';

interface TaxonomiaApi { nombre: string; slug: string }

function FiltroTaxonomia({ source, label, endpoint }: {
  source: 'categoria' | 'etiqueta';
  label: string;
  endpoint: '/api/admin/categorias' | '/api/admin/etiquetas';
}) {
  const [choices, setChoices] = useState<{ id: string; name: string }[]>([]);
  useEffect(() => {
    const controller = new AbortController();
    fetch(endpoint, { credentials: 'include', signal: controller.signal })
      .then((response) => response.ok ? response.json() as Promise<TaxonomiaApi[]> : Promise.reject())
      .then((datos) => setChoices(datos.map(({ nombre, slug }) => ({ id: slug, name: nombre }))))
      .catch(() => undefined);
    return () => controller.abort();
  }, [endpoint]);
  return <AutocompleteInput source={source} label={label} choices={choices} optionText="name" />;
}

const filtros = [
  <TextInput key="buscar" source="buscar" label="Buscar" placeholder="Título o descripción" resettable alwaysOn />,
  <SelectInput
    key="estado"
    source="estado"
    label="Estado"
    choices={[
      { id: 'activas', name: 'Activas' },
      { id: 'archivadas', name: 'Archivadas' },
      { id: 'todas', name: 'Todas' },
    ]}
    alwaysOn
  />,
  <FiltroTaxonomia key="categoria" source="categoria" label="Categoría" endpoint="/api/admin/categorias" />,
  <FiltroTaxonomia key="etiqueta" source="etiqueta" label="Etiqueta" endpoint="/api/admin/etiquetas" />,
];

function AccionesLista() {
  const { filterValues, setFilters } = useListContext();
  const tieneFiltros = Object.entries(filterValues).some(([clave, valor]) => clave !== 'estado' || valor !== 'activas');
  return (
    <TopToolbar className="lista-recetas__acciones">
      <FilterButton />
      <SortButton fields={['creado_en', 'titulo', 'id']} />
      {tieneFiltros && (
        <Button startIcon={<RestartAltOutlinedIcon />} onClick={() => setFilters({ estado: 'activas' }, [])}>
          Limpiar filtros
        </Button>
      )}
      <CreateButton label="Nueva receta" />
    </TopToolbar>
  );
}

function ArchivarRapido({ receta, compacto = false }: { receta: RecetaResumenAdmin; compacto?: boolean }) {
  const [abierto, setAbierto] = useState(false);
  const [procesando, setProcesando] = useState(false);
  const notify = useNotify();
  const refresh = useRefresh();
  if (receta.archivada_en) return null;

  const archivar = async () => {
    setProcesando(true);
    try {
      const response = await fetch(`/api/admin/recetas/${receta.id}`, {
        method: 'DELETE', credentials: 'include', headers: { Accept: 'application/json' },
      });
      const body = await response.json().catch(() => null) as { error?: unknown } | null;
      if (!response.ok) throw new Error(typeof body?.error === 'string' ? body.error : 'No se pudo archivar');
      notify('Receta archivada', { type: 'success' });
      setAbierto(false);
      refresh();
    } catch (error) {
      notify(error instanceof Error ? error.message : 'No se pudo archivar', { type: 'error' });
    } finally {
      setProcesando(false);
    }
  };

  return (
    <Box onClick={(event) => event.stopPropagation()} className={compacto ? 'lista-recetas__archivo-movil' : undefined}>
      <Button size="small" color="warning" startIcon={<ArchiveOutlinedIcon />} onClick={() => setAbierto(true)}>
        Archivar
      </Button>
      <Dialog open={abierto} onClose={() => !procesando && setAbierto(false)}>
        <DialogTitle>Archivar receta</DialogTitle>
        <DialogContent>“{receta.titulo}” dejará de aparecer en la web pública y podrás restaurarla después.</DialogContent>
        <DialogActions>
          <Button onClick={() => setAbierto(false)} disabled={procesando}>Cancelar</Button>
          <Button variant="contained" color="warning" onClick={archivar} disabled={procesando}>Archivar</Button>
        </DialogActions>
      </Dialog>
    </Box>
  );
}

function taxonomias(valores: string[], vacio: string) {
  if (valores.length === 0) return <Typography variant="body2" color="text.secondary">{vacio}</Typography>;
  return <Box className="lista-recetas__taxonomias">{valores.map((valor) => <Chip key={valor} label={valor} size="small" />)}</Box>;
}

function fechaPublicacion(fecha: string): string {
  return new Intl.DateTimeFormat('es-ES', { day: '2-digit', month: 'short', year: 'numeric' }).format(new Date(fecha));
}

function Miniatura({ receta }: { receta: RecetaResumenAdmin }) {
  return <Box className="lista-recetas-movil__imagen">{receta.imagen_url ? <img src={receta.imagen_url} alt="" /> : <Typography variant="caption">Sin imagen</Typography>}</Box>;
}

function ListadoMovil() {
  return (
    <SimpleList<RecetaResumenAdmin>
      className="lista-recetas-movil"
      rowClick="edit"
      leftIcon={(receta) => <Miniatura receta={receta} />}
      primaryText={(receta) => (
        <Box className="lista-recetas-movil__cabecera">
          <Typography component="strong">{receta.titulo}</Typography>
          {receta.archivada_en && <Chip className="lista-recetas__archivada" label="Archivada" size="small" />}
          <Box className="lista-recetas-movil__meta"><Typography component="span" variant="caption">ID {receta.id}</Typography><Typography component="span" variant="caption">Publicada {fechaPublicacion(receta.creado_en)}</Typography></Box>
        </Box>
      )}
      secondaryText={(receta) => (
        <Box className="lista-recetas-movil__datos">
          <Typography variant="body2"><strong>Categorías:</strong> {receta.categorias.join(', ') || 'Sin categoría'}</Typography>
          <Typography variant="body2"><strong>Etiquetas:</strong> {receta.etiquetas.join(', ') || 'Sin etiquetas'}</Typography>
          <ArchivarRapido receta={receta} compacto />
        </Box>
      )}
    />
  );
}

function ListadoEscritorio() {
  return (
    <Datagrid bulkActionButtons={false} rowClick="edit" className="lista-recetas-escritorio">
      <ImageField source="imagen_url" label="Imagen" sortable={false} sx={{ '& img': { width: 88, height: 59, objectFit: 'cover', borderRadius: 1.5 } }} />
      <NumberField source="id" label="ID" />
      <FunctionField<RecetaResumenAdmin> source="titulo" label="Título" render={(receta) => <Box className="lista-recetas__titulo"><TextField source="titulo" />{receta.archivada_en && <Chip className="lista-recetas__archivada" label="Archivada" size="small" />}</Box>} />
      <FunctionField<RecetaResumenAdmin> label="Categorías" sortable={false} render={(receta) => taxonomias(receta.categorias, 'Sin categoría')} />
      <FunctionField<RecetaResumenAdmin> label="Etiquetas" sortable={false} render={(receta) => taxonomias(receta.etiquetas, 'Sin etiquetas')} />
      <DateField source="creado_en" label="Publicada" />
      <FunctionField<RecetaResumenAdmin> label="Acciones" sortable={false} render={(receta) => <ArchivarRapido receta={receta} />} />
    </Datagrid>
  );
}

function ListaVacia() {
  const { filterValues, setFilters } = useListContext();
  const filtrada = Object.keys(filterValues).length > 1 || filterValues.estado !== 'activas';
  return (
    <Box className="lista-recetas__vacia">
      <Typography variant="h6">{filtrada ? 'No hay recetas que coincidan' : 'Todavía no hay recetas'}</Typography>
      <Typography color="text.secondary">{filtrada ? 'Prueba otra búsqueda o elimina los filtros aplicados.' : 'Crea la primera receta para empezar el recetario.'}</Typography>
      {filtrada ? <Button onClick={() => setFilters({ estado: 'activas' }, [])}>Limpiar filtros</Button> : <CreateButton label="Crear receta" />}
    </Box>
  );
}

export function RecetaList() {
  const esMovil = useMediaQuery((theme: Theme) => theme.breakpoints.down('sm'));
  return (
    <List
      className="lista-recetas"
      storeKey="recetas-admin-lista"
      filters={filtros}
      filterDefaultValues={{ estado: 'activas' }}
      actions={<AccionesLista />}
      empty={<ListaVacia />}
      perPage={10}
      sort={{ field: 'creado_en', order: 'DESC' }}
      title="Recetas"
    >
      {esMovil ? <ListadoMovil /> : <ListadoEscritorio />}
    </List>
  );
}
