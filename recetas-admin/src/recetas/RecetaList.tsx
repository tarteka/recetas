import { useEffect, useState } from 'react';
import {
  Autocomplete,
  Box,
  Button,
  Chip,
  Dialog,
  DialogActions,
  DialogContent,
  DialogTitle,
  FormControl,
  InputAdornment,
  InputLabel,
  MenuItem,
  Select,
  TextField as CampoTexto,
  Typography,
  useMediaQuery,
} from '@mui/material';
import ArchiveOutlinedIcon from '@mui/icons-material/ArchiveOutlined';
import DeleteForeverOutlinedIcon from '@mui/icons-material/DeleteForeverOutlined';
import RestartAltOutlinedIcon from '@mui/icons-material/RestartAltOutlined';
import SearchOutlinedIcon from '@mui/icons-material/SearchOutlined';
import type { Theme } from '@mui/material/styles';
import {
  CreateButton,
  Datagrid,
  DateField,
  FunctionField,
  ImageField,
  List,
  NumberField,
  SimpleList,
  TextField,
  useListContext,
  useNotify,
  useRefresh,
} from 'react-admin';
import type { RecetaResumenAdmin } from '../types';

interface TaxonomiaApi { nombre: string; slug: string }
interface Opcion { id: string; nombre: string }

function PanelListado() {
  const { filterValues, setFilters, sort, setSort } = useListContext();
  const [categorias, setCategorias] = useState<Opcion[]>([]);
  const [etiquetas, setEtiquetas] = useState<Opcion[]>([]);

  useEffect(() => {
    const controller = new AbortController();
    const cargar = async (endpoint: string, asignar: (opciones: Opcion[]) => void) => {
      const response = await fetch(endpoint, { credentials: 'include', signal: controller.signal });
      if (!response.ok) return;
      const datos = await response.json() as TaxonomiaApi[];
      asignar(datos.map(({ nombre, slug }) => ({ id: slug, nombre })));
    };
    void cargar('/api/admin/categorias', setCategorias);
    void cargar('/api/admin/etiquetas', setEtiquetas);
    return () => controller.abort();
  }, []);

  const actualizar = (campo: string, valor: string) => {
    const siguientes = { ...filterValues };
    if (valor) siguientes[campo] = valor;
    else delete siguientes[campo];
    setFilters(siguientes, []);
  };
  const limpiar = () => setFilters({ estado: 'activas' }, []);
  const filtrosActivos = Boolean(filterValues.buscar || filterValues.categoria || filterValues.etiqueta || filterValues.estado !== 'activas');
  const categoriaActual = categorias.find(({ id }) => id === filterValues.categoria) ?? null;
  const etiquetaActual = etiquetas.find(({ id }) => id === filterValues.etiqueta) ?? null;

  return (
    <Box className="lista-recetas__panel">
      <Box className="lista-recetas__encabezado">
        <Box>
          <Typography component="h1" variant="h5">Recetas</Typography>
          <Typography variant="body2" color="text.secondary">Busca, organiza y gestiona el contenido del recetario.</Typography>
        </Box>
        <CreateButton label="Nueva receta" />
      </Box>

      <Box className="lista-recetas__filtros" component="section" aria-label="Búsqueda y filtros">
        <CampoTexto
          className="lista-recetas__buscar"
          label="Buscar recetas"
          placeholder="Escribe un título o una descripción"
          value={filterValues.buscar ?? ''}
          onChange={(event) => actualizar('buscar', event.target.value)}
          slotProps={{ input: { startAdornment: <InputAdornment position="start"><SearchOutlinedIcon /></InputAdornment> } }}
          size="small"
        />
        <FormControl size="small">
          <InputLabel id="estado-listado">Estado</InputLabel>
          <Select labelId="estado-listado" label="Estado" value={filterValues.estado ?? 'activas'} onChange={(event) => actualizar('estado', event.target.value)}>
            <MenuItem value="activas">Activas</MenuItem>
            <MenuItem value="archivadas">Archivadas</MenuItem>
            <MenuItem value="todas">Todas</MenuItem>
          </Select>
        </FormControl>
        <Autocomplete
          options={categorias}
          value={categoriaActual}
          onChange={(_event, opcion) => actualizar('categoria', opcion?.id ?? '')}
          getOptionLabel={(opcion) => opcion.nombre}
          renderInput={(params) => <CampoTexto {...params} label="Categoría" placeholder="Todas" size="small" />}
        />
        <Autocomplete
          options={etiquetas}
          value={etiquetaActual}
          onChange={(_event, opcion) => actualizar('etiqueta', opcion?.id ?? '')}
          getOptionLabel={(opcion) => opcion.nombre}
          renderInput={(params) => <CampoTexto {...params} label="Etiqueta" placeholder="Todas" size="small" />}
        />
        <FormControl size="small">
          <InputLabel id="orden-listado">Ordenar por</InputLabel>
          <Select
            labelId="orden-listado"
            label="Ordenar por"
            value={`${sort.field}:${sort.order}`}
            onChange={(event) => {
              const [field, order] = event.target.value.split(':');
              setSort({ field, order: order as 'ASC' | 'DESC' });
            }}
          >
            <MenuItem value="creado_en:DESC">Más recientes</MenuItem>
            <MenuItem value="creado_en:ASC">Más antiguas</MenuItem>
            <MenuItem value="titulo:ASC">Título de A a Z</MenuItem>
            <MenuItem value="titulo:DESC">Título de Z a A</MenuItem>
            <MenuItem value="id:DESC">ID descendente</MenuItem>
            <MenuItem value="id:ASC">ID ascendente</MenuItem>
          </Select>
        </FormControl>
        {filtrosActivos && (
          <Button className="lista-recetas__limpiar" startIcon={<RestartAltOutlinedIcon />} onClick={limpiar}>
            Restablecer
          </Button>
        )}
      </Box>
    </Box>
  );
}

function AccionRapida({ receta, compacto = false }: { receta: RecetaResumenAdmin; compacto?: boolean }) {
  const [abierto, setAbierto] = useState(false);
  const [procesando, setProcesando] = useState(false);
  const notify = useNotify();
  const refresh = useRefresh();
  const eliminacion = Boolean(receta.archivada_en);

  const confirmar = async () => {
    setProcesando(true);
    try {
      const response = await fetch(
        eliminacion ? `/api/admin/recetas/${receta.id}/definitiva` : `/api/admin/recetas/${receta.id}`,
        { method: 'DELETE', credentials: 'include', headers: { Accept: 'application/json' } },
      );
      const body = await response.json().catch(() => null) as { error?: unknown } | null;
      if (!response.ok) throw new Error(typeof body?.error === 'string' ? body.error : 'No se pudo completar la acción');
      notify(eliminacion ? 'Receta eliminada definitivamente' : 'Receta archivada', { type: 'success' });
      setAbierto(false);
      refresh();
    } catch (error) {
      notify(error instanceof Error ? error.message : 'No se pudo completar la acción', { type: 'error' });
    } finally {
      setProcesando(false);
    }
  };

  return (
    <Box onClick={(event) => event.stopPropagation()} className={compacto ? 'lista-recetas__accion-movil' : undefined}>
      <Button
        size="small"
        color={eliminacion ? 'error' : 'warning'}
        startIcon={eliminacion ? <DeleteForeverOutlinedIcon /> : <ArchiveOutlinedIcon />}
        onClick={() => setAbierto(true)}
      >
        {eliminacion ? 'Eliminar' : 'Archivar'}
      </Button>
      <Dialog open={abierto} onClose={() => !procesando && setAbierto(false)}>
        <DialogTitle>{eliminacion ? 'Eliminar receta definitivamente' : 'Archivar receta'}</DialogTitle>
        <DialogContent>
          {eliminacion
            ? `“${receta.titulo}” se eliminará junto con sus ingredientes y pasos. Esta acción no se puede deshacer.`
            : `“${receta.titulo}” dejará de aparecer en la web pública y podrás restaurarla después.`}
        </DialogContent>
        <DialogActions>
          <Button onClick={() => setAbierto(false)} disabled={procesando}>Cancelar</Button>
          <Button variant="contained" color={eliminacion ? 'error' : 'warning'} onClick={confirmar} disabled={procesando}>
            {eliminacion ? 'Eliminar definitivamente' : 'Archivar'}
          </Button>
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
  return <SimpleList<RecetaResumenAdmin> className="lista-recetas-movil" rowClick="edit" leftIcon={(receta) => <Miniatura receta={receta} />} primaryText={(receta) => <Box className="lista-recetas-movil__cabecera"><Typography component="strong">{receta.titulo}</Typography>{receta.archivada_en && <Chip className="lista-recetas__archivada" label="Archivada" size="small" />}<Box className="lista-recetas-movil__meta"><Typography component="span" variant="caption">ID {receta.id}</Typography><Typography component="span" variant="caption">Publicada {fechaPublicacion(receta.creado_en)}</Typography></Box></Box>} secondaryText={(receta) => <Box className="lista-recetas-movil__datos"><Typography variant="body2"><strong>Categorías:</strong> {receta.categorias.join(', ') || 'Sin categoría'}</Typography><Typography variant="body2"><strong>Etiquetas:</strong> {receta.etiquetas.join(', ') || 'Sin etiquetas'}</Typography><AccionRapida receta={receta} compacto /></Box>} />;
}

function ListadoEscritorio() {
  return <Datagrid bulkActionButtons={false} rowClick="edit" className="lista-recetas-escritorio"><ImageField source="imagen_url" label="Imagen" sortable={false} sx={{ '& img': { width: 88, height: 59, objectFit: 'cover', borderRadius: 1.5 } }} /><NumberField source="id" label="ID" /><FunctionField<RecetaResumenAdmin> source="titulo" label="Título" render={(receta) => <Box className="lista-recetas__titulo"><TextField source="titulo" />{receta.archivada_en && <Chip className="lista-recetas__archivada" label="Archivada" size="small" />}</Box>} /><FunctionField<RecetaResumenAdmin> label="Categorías" sortable={false} render={(receta) => taxonomias(receta.categorias, 'Sin categoría')} /><FunctionField<RecetaResumenAdmin> label="Etiquetas" sortable={false} render={(receta) => taxonomias(receta.etiquetas, 'Sin etiquetas')} /><DateField source="creado_en" label="Publicada" /><FunctionField<RecetaResumenAdmin> label="Acciones" sortable={false} render={(receta) => <AccionRapida receta={receta} />} /></Datagrid>;
}

function ListaVacia() {
  const { filterValues, setFilters } = useListContext();
  const filtrada = Object.keys(filterValues).length > 1 || filterValues.estado !== 'activas';
  return <Box className="lista-recetas__vacia"><Typography variant="h6">{filtrada ? 'No hay recetas que coincidan' : 'Todavía no hay recetas'}</Typography><Typography color="text.secondary">{filtrada ? 'Prueba otra búsqueda o restablece los filtros.' : 'Crea la primera receta para empezar el recetario.'}</Typography>{filtrada ? <Button onClick={() => setFilters({ estado: 'activas' }, [])}>Restablecer filtros</Button> : <CreateButton label="Crear receta" />}</Box>;
}

function ContenidoLista({ movil }: { movil: boolean }) {
  const { total, isPending } = useListContext();
  return <><PanelListado />{!isPending && total === 0 ? <ListaVacia /> : (movil ? <ListadoMovil /> : <ListadoEscritorio />)}</>;
}

export function RecetaList() {
  const esMovil = useMediaQuery((theme: Theme) => theme.breakpoints.down('sm'));
  return (
    <List className="lista-recetas" storeKey="recetas-admin-lista" filterDefaultValues={{ estado: 'activas' }} actions={false} empty={false} perPage={10} sort={{ field: 'creado_en', order: 'DESC' }} title="Recetas">
      <ContenidoLista movil={esMovil} />
    </List>
  );
}
