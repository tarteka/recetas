import { useEffect, useState } from 'react';
import {
  Autocomplete,
  Box,
  Button,
  Chip,
  Collapse,
  Dialog,
  DialogActions,
  DialogContent,
  DialogContentText,
  DialogTitle,
  FormControl,
  InputAdornment,
  InputLabel,
  IconButton,
  MenuItem,
  Select,
  TextField as CampoTexto,
  Tooltip,
  Typography,
  useMediaQuery,
} from '@mui/material';
import ArchiveOutlinedIcon from '@mui/icons-material/ArchiveOutlined';
import DeleteForeverOutlinedIcon from '@mui/icons-material/DeleteForeverOutlined';
import EditOutlinedIcon from '@mui/icons-material/EditOutlined';
import ExpandMoreOutlinedIcon from '@mui/icons-material/ExpandMoreOutlined';
import FilterListOutlinedIcon from '@mui/icons-material/FilterListOutlined';
import RestartAltOutlinedIcon from '@mui/icons-material/RestartAltOutlined';
import RestoreOutlinedIcon from '@mui/icons-material/RestoreOutlined';
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
  TextField,
  useListContext,
  useNotify,
  useRedirect,
  useRefresh,
} from 'react-admin';
import type { RecetaResumenAdmin } from '../types';

interface TaxonomiaApi { nombre: string; slug: string }
interface Opcion { id: string; nombre: string }

function PanelListado() {
  const { filterValues, setFilters, sort, setSort } = useListContext();
  const esMovil = useMediaQuery((theme: Theme) => theme.breakpoints.down('sm'));
  const [filtrosAbiertos, setFiltrosAbiertos] = useState(false);
  const [categorias, setCategorias] = useState<Opcion[]>([]);
  const [etiquetas, setEtiquetas] = useState<Opcion[]>([]);

  useEffect(() => {
    const controller = new AbortController();
    const cargar = async (endpoint: string, asignar: (opciones: Opcion[]) => void) => {
      const response = await fetch(endpoint, { credentials: 'include', signal: controller.signal });
      if (!response.ok) return;
      const body = await response.json() as TaxonomiaApi[] | { datos: TaxonomiaApi[] };
      const datos = Array.isArray(body) ? body : body.datos;
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

      {esMovil && (
        <Button
          className="lista-recetas__alternar-filtros"
          startIcon={<FilterListOutlinedIcon />}
          endIcon={<ExpandMoreOutlinedIcon className={filtrosAbiertos ? 'esta-abierto' : ''} />}
          onClick={() => setFiltrosAbiertos((abiertos) => !abiertos)}
          aria-expanded={filtrosAbiertos}
          aria-controls="filtros-recetas"
        >
          {filtrosActivos ? 'Buscar y filtrar · filtros activos' : 'Buscar y filtrar'}
        </Button>
      )}
      <Collapse in={!esMovil || filtrosAbiertos} timeout={esMovil ? 'auto' : 0}>
      <Box className="lista-recetas__filtros" component="section" id="filtros-recetas" aria-label="Búsqueda y filtros">
        <CampoTexto
          className="lista-recetas__buscar"
          label="Buscar recetas"
          placeholder="Escribe un título o una descripción"
          value={filterValues.buscar ?? ''}
          onChange={(event) => actualizar('buscar', event.target.value)}
          slotProps={{ input: { startAdornment: <InputAdornment position="start"><SearchOutlinedIcon aria-hidden="true" /></InputAdornment> } }}
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
      </Collapse>
    </Box>
  );
}

function AccionRapida({ receta, compacto = false }: { receta: RecetaResumenAdmin; compacto?: boolean }) {
  const [accion, setAccion] = useState<'archivar' | 'restaurar' | 'eliminar' | null>(null);
  const [procesando, setProcesando] = useState(false);
  const notify = useNotify();
  const refresh = useRefresh();
  const archivada = Boolean(receta.archivada_en);
  const dialogoId = `accion-receta-${receta.id}`;
  const descripcionId = `descripcion-accion-receta-${receta.id}`;

  const confirmar = async () => {
    if (!accion) return;
    setProcesando(true);
    try {
      const response = await fetch(
        accion === 'eliminar'
          ? `/api/admin/recetas/${receta.id}/definitiva`
          : accion === 'restaurar'
            ? `/api/admin/recetas/${receta.id}/restaurar`
            : `/api/admin/recetas/${receta.id}`,
        { method: accion === 'restaurar' ? 'POST' : 'DELETE', credentials: 'include', headers: { Accept: 'application/json' } },
      );
      const body = await response.json().catch(() => null) as { error?: unknown } | null;
      if (!response.ok) throw new Error(typeof body?.error === 'string' ? body.error : 'No se pudo completar la acción');
      notify(
        accion === 'eliminar' ? 'Receta eliminada definitivamente' : accion === 'restaurar' ? 'Receta restaurada' : 'Receta archivada',
        { type: 'success' },
      );
      setAccion(null);
      refresh();
    } catch (error) {
      notify(error instanceof Error ? error.message : 'No se pudo completar la acción', { type: 'error' });
    } finally {
      setProcesando(false);
    }
  };

  return (
    <Box onClick={(event) => event.stopPropagation()} className={`lista-recetas__acciones-fila${compacto ? ' lista-recetas__accion-movil' : ''}`}>
      {archivada ? (
        <>
          <Tooltip title="Restaurar receta" arrow>
            <IconButton size="small" color="primary" aria-label="Restaurar receta" onClick={() => setAccion('restaurar')}>
              <RestoreOutlinedIcon />
            </IconButton>
          </Tooltip>
          <Tooltip title="Eliminar receta" arrow>
            <IconButton size="small" color="error" aria-label="Eliminar receta" onClick={() => setAccion('eliminar')}>
              <DeleteForeverOutlinedIcon />
            </IconButton>
          </Tooltip>
        </>
      ) : (
        <Tooltip title="Archivar receta" arrow>
          <IconButton size="small" color="warning" aria-label="Archivar receta" onClick={() => setAccion('archivar')}>
            <ArchiveOutlinedIcon />
          </IconButton>
        </Tooltip>
      )}
      <Dialog open={accion !== null} onClose={() => !procesando && setAccion(null)} aria-labelledby={dialogoId} aria-describedby={descripcionId}>
        <DialogTitle id={dialogoId}>{accion === 'eliminar' ? 'Eliminar receta definitivamente' : accion === 'restaurar' ? 'Restaurar receta' : 'Archivar receta'}</DialogTitle>
        <DialogContent>
          <DialogContentText id={descripcionId}>
            {accion === 'eliminar'
              ? `“${receta.titulo}” se eliminará junto con sus ingredientes y pasos. Esta acción no se puede deshacer.`
              : accion === 'restaurar'
                ? `“${receta.titulo}” volverá a aparecer en la web pública.`
                : `“${receta.titulo}” dejará de aparecer en la web pública y podrás restaurarla después.`}
          </DialogContentText>
        </DialogContent>
        <DialogActions>
          <Button onClick={() => setAccion(null)} disabled={procesando}>Cancelar</Button>
          <Button variant="contained" color={accion === 'eliminar' ? 'error' : accion === 'archivar' ? 'warning' : 'primary'} onClick={confirmar} disabled={procesando}>
            {accion === 'eliminar' ? 'Eliminar definitivamente' : accion === 'restaurar' ? 'Restaurar' : 'Archivar'}
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
  const { data = [], isPending } = useListContext<RecetaResumenAdmin>();
  const redirect = useRedirect();
  return (
    <Box component="ul" className="lista-recetas-movil" aria-busy={isPending}>
      {data.map((receta) => (
        <Box component="li" className="lista-recetas-movil__tarjeta" key={receta.id}>
          <Miniatura receta={receta} />
          <Box className="lista-recetas-movil__contenido">
            <Box className="lista-recetas-movil__cabecera">
              <Typography component="strong">{receta.titulo}</Typography>
              {receta.archivada_en && <Chip className="lista-recetas__archivada" label="Archivada" size="small" />}
              <Box className="lista-recetas-movil__meta"><Typography component="span" variant="caption">ID {receta.id}</Typography><Typography component="span" variant="caption">Publicada {fechaPublicacion(receta.creado_en)}</Typography></Box>
            </Box>
            <Box className="lista-recetas-movil__datos">
              <Typography variant="body2"><strong>Categorías:</strong> {receta.categorias.join(', ') || 'Sin categoría'}</Typography>
              <Typography variant="body2"><strong>Etiquetas:</strong> {receta.etiquetas.join(', ') || 'Sin etiquetas'}</Typography>
            </Box>
            <Box className="lista-recetas-movil__acciones" aria-label={`Acciones para ${receta.titulo}`}>
              <Tooltip title="Editar receta" arrow>
                <IconButton color="primary" aria-label={`Editar ${receta.titulo}`} onClick={() => redirect('edit', 'recetas', receta.id)}>
                  <EditOutlinedIcon />
                </IconButton>
              </Tooltip>
              <AccionRapida receta={receta} compacto />
            </Box>
          </Box>
        </Box>
      ))}
    </Box>
  );
}

function ListadoEscritorio() {
  return <Datagrid bulkActionButtons={false} rowClick="edit" className="lista-recetas-escritorio"><ImageField source="imagen_url" label="Imagen" sortable={false} sx={{ '& img': { width: 88, height: 59, objectFit: 'cover', borderRadius: 1.5 } }} /><NumberField source="id" label="ID" /><FunctionField<RecetaResumenAdmin> source="titulo" label="Título" render={(receta) => <Box className="lista-recetas__titulo"><TextField source="titulo" />{receta.archivada_en && <Chip className="lista-recetas__archivada" label="Archivada" size="small" />}</Box>} /><FunctionField<RecetaResumenAdmin> label="Categorías" sortable={false} render={(receta) => taxonomias(receta.categorias, 'Sin categoría')} /><FunctionField<RecetaResumenAdmin> label="Etiquetas" sortable={false} render={(receta) => taxonomias(receta.etiquetas, 'Sin etiquetas')} /><DateField source="creado_en" label="Publicada" locales="es-ES" /><FunctionField<RecetaResumenAdmin> label="Acciones" sortable={false} render={(receta) => <AccionRapida receta={receta} />} /></Datagrid>;
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
