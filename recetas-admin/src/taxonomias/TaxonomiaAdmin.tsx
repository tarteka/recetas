import {
  Box,
  Button,
  Chip,
  FormControl,
  IconButton,
  InputAdornment,
  InputLabel,
  MenuItem,
  Select,
  TextField as CampoTexto,
  Tooltip,
  Typography,
  useMediaQuery,
} from '@mui/material';
import DeleteForeverOutlinedIcon from '@mui/icons-material/DeleteForeverOutlined';
import EditOutlinedIcon from '@mui/icons-material/EditOutlined';
import RestartAltOutlinedIcon from '@mui/icons-material/RestartAltOutlined';
import SearchOutlinedIcon from '@mui/icons-material/SearchOutlined';
import type { Theme } from '@mui/material/styles';
import {
  Create,
  CreateButton,
  Datagrid,
  DeleteButton,
  Edit,
  FunctionField,
  List,
  NumberField,
  SaveButton,
  TabbedForm,
  TabbedFormTabs,
  TextField,
  TextInput,
  required,
  useListContext,
  useRedirect,
  useResourceContext,
} from 'react-admin';
import type { RaRecord } from 'react-admin';

interface Taxonomia extends RaRecord<number> {
  id: number;
  nombre: string;
  slug: string;
  total_recetas: number;
}

function configuracion(recurso: string) {
  return recurso === 'categorias'
    ? { singular: 'categoría', plural: 'Categorías' }
    : { singular: 'etiqueta', plural: 'Etiquetas' };
}

function PanelTaxonomia() {
  const recurso = useResourceContext() ?? 'categorias';
  const { filterValues, setFilters, sort, setSort } = useListContext();
  const { plural, singular } = configuracion(recurso);
  const buscar = String(filterValues.buscar ?? '');

  return (
    <Box className="lista-recetas__panel taxonomias__panel">
      <Box className="lista-recetas__encabezado taxonomias__cabecera">
        <Box>
          <Typography component="h1" variant="h5">{plural}</Typography>
          <Typography variant="body2" color="text.secondary">Busca, organiza y gestiona la clasificación del recetario.</Typography>
        </Box>
        <CreateButton label={`Nueva ${singular}`} />
      </Box>
      <Box className="lista-recetas__filtros taxonomias__filtros" component="section" aria-label={`Búsqueda y orden de ${plural.toLowerCase()}`}>
        <CampoTexto
          className="lista-recetas__buscar"
          label={`Buscar ${plural.toLowerCase()}`}
          placeholder="Escribe un nombre o slug"
          value={buscar}
          onChange={(event) => setFilters(event.target.value ? { buscar: event.target.value } : {}, [])}
          slotProps={{ input: { startAdornment: <InputAdornment position="start"><SearchOutlinedIcon aria-hidden="true" /></InputAdornment> } }}
          size="small"
        />
        <FormControl size="small">
          <InputLabel id={`orden-${recurso}`}>Ordenar por</InputLabel>
          <Select
            labelId={`orden-${recurso}`}
            label="Ordenar por"
            value={`${sort.field}:${sort.order}`}
            onChange={(event) => {
              const [field, order] = event.target.value.split(':');
              setSort({ field, order: order as 'ASC' | 'DESC' });
            }}
          >
            <MenuItem value="nombre:ASC">Nombre de A a Z</MenuItem>
            <MenuItem value="nombre:DESC">Nombre de Z a A</MenuItem>
            <MenuItem value="total_recetas:DESC">Más utilizadas</MenuItem>
            <MenuItem value="total_recetas:ASC">Menos utilizadas</MenuItem>
            <MenuItem value="id:DESC">Más recientes</MenuItem>
          </Select>
        </FormControl>
        {buscar && <Button className="lista-recetas__limpiar" startIcon={<RestartAltOutlinedIcon />} onClick={() => setFilters({}, [])}>Restablecer</Button>}
      </Box>
    </Box>
  );
}

function AccionesTaxonomia({ item, movil = false }: { item: Taxonomia; movil?: boolean }) {
  const recurso = useResourceContext() ?? 'categorias';
  const redirect = useRedirect();
  return (
    <Box className={movil ? 'taxonomias-movil__acciones' : 'lista-recetas__acciones-fila'} onClick={(event) => event.stopPropagation()}>
      {movil && <Tooltip title="Editar" arrow><IconButton color="primary" aria-label={`Editar ${item.nombre}`} onClick={() => redirect('edit', recurso, item.id)}><EditOutlinedIcon /></IconButton></Tooltip>}
      <Tooltip title="Eliminar" arrow>
        <DeleteButton className="taxonomias__eliminar-icono" record={item} mutationMode="pessimistic" redirect={false} label="" icon={<DeleteForeverOutlinedIcon />} />
      </Tooltip>
    </Box>
  );
}

function ListadoMovil() {
  const { data = [], isPending } = useListContext<Taxonomia>();
  return (
    <Box component="ul" className="lista-recetas-movil taxonomias-movil" aria-busy={isPending}>
      {data.map((item) => (
        <Box component="li" key={item.id} className="lista-recetas-movil__tarjeta taxonomias-movil__tarjeta">
          <Box className="taxonomias-movil__icono" aria-hidden="true">#</Box>
          <Box className="lista-recetas-movil__contenido">
            <Box className="lista-recetas-movil__cabecera">
              <Typography component="strong">{item.nombre}</Typography>
              <Typography variant="body2" color="text.secondary">/{item.slug}</Typography>
            </Box>
            <Chip label={`${item.total_recetas} ${item.total_recetas === 1 ? 'receta asociada' : 'recetas asociadas'}`} size="small" />
            <AccionesTaxonomia item={item} movil />
          </Box>
        </Box>
      ))}
    </Box>
  );
}

function ListadoEscritorio() {
  return (
    <Datagrid bulkActionButtons={false} rowClick="edit" className="lista-recetas-escritorio taxonomias-escritorio">
      <NumberField source="id" label="ID" />
      <TextField source="nombre" label="Nombre" />
      <TextField source="slug" label="Slug" />
      <FunctionField<Taxonomia> source="total_recetas" label="Uso" render={(item) => <Chip label={`${item.total_recetas} ${item.total_recetas === 1 ? 'receta' : 'recetas'}`} size="small" />} />
      <FunctionField<Taxonomia> label="Acciones" sortable={false} render={(item) => <AccionesTaxonomia item={item} />} />
    </Datagrid>
  );
}

function ContenidoLista() {
  const movil = useMediaQuery((theme: Theme) => theme.breakpoints.down('sm'));
  const { total, isPending, filterValues, setFilters } = useListContext();
  return (
    <>
      <PanelTaxonomia />
      {!isPending && total === 0 ? (
        <Box className="lista-recetas__vacia">
          <Typography variant="h6">{filterValues.buscar ? 'No hay resultados' : 'Todavía no hay términos'}</Typography>
          <Typography color="text.secondary">{filterValues.buscar ? 'Prueba otra búsqueda.' : 'Crea el primero para organizar el recetario.'}</Typography>
          {filterValues.buscar ? <Button onClick={() => setFilters({}, [])}>Restablecer búsqueda</Button> : <CreateButton label="Crear término" />}
        </Box>
      ) : (movil ? <ListadoMovil /> : <ListadoEscritorio />)}
    </>
  );
}

export function TaxonomiaList() {
  return <List className="lista-recetas taxonomias" actions={false} perPage={15} sort={{ field: 'nombre', order: 'ASC' }} empty={false}><ContenidoLista /></List>;
}

function validarSlug(valor: string | undefined) {
  return !valor || /^[a-z0-9]+(?:-[a-z0-9]+)*$/.test(valor) ? undefined : 'Usa minúsculas, números y guiones';
}

function BarraFormulario({ creando }: { creando: boolean }) {
  const recurso = useResourceContext() ?? 'categorias';
  const redirect = useRedirect();
  const { singular } = configuracion(recurso);
  return (
    <Box className="editor-receta__toolbar taxonomias__toolbar" role="toolbar">
      {!creando && <DeleteButton className="taxonomias__eliminar" mutationMode="pessimistic" redirect="list" />}
      <SaveButton label={creando ? `Crear ${singular}` : 'Guardar cambios'} />
      <Button type="button" variant="outlined" onClick={() => redirect('list', recurso)}>Cancelar</Button>
    </Box>
  );
}

function FormularioTaxonomia({ creando }: { creando: boolean }) {
  return (
    <TabbedForm
      toolbar={<BarraFormulario creando={creando} />}
      tabs={<TabbedFormTabs variant="scrollable" scrollButtons="auto" allowScrollButtonsMobile />}
      warnWhenUnsavedChanges
    >
      <TabbedForm.Tab label="Información">
        <Box className="taxonomias__campos">
          <Typography variant="h6">Datos del término</Typography>
          <Typography variant="body2" color="text.secondary">El nombre se muestra a los usuarios. Si dejas el slug vacío, se generará automáticamente.</Typography>
          <TextInput source="nombre" label="Nombre" validate={required('El nombre es obligatorio')} fullWidth />
          <TextInput source="slug" label="Slug" helperText="Dirección estable utilizada en filtros y enlaces" validate={validarSlug} fullWidth />
          {!creando && <Box className="taxonomias__uso"><Typography variant="body2" color="text.secondary">Recetas asociadas</Typography><NumberField source="total_recetas" /></Box>}
        </Box>
      </TabbedForm.Tab>
    </TabbedForm>
  );
}

export function TaxonomiaCreate() {
  const recurso = useResourceContext() ?? 'categorias';
  const { singular } = configuracion(recurso);
  return <Create className="editor-receta taxonomias-editor" title={`Nueva ${singular}`} redirect="edit" sx={{ '& .RaCreate-card': { overflow: 'hidden' } }}><FormularioTaxonomia creando /></Create>;
}

export function TaxonomiaEdit() {
  const recurso = useResourceContext() ?? 'categorias';
  const { singular } = configuracion(recurso);
  return <Edit className="editor-receta taxonomias-editor" title={`Editar ${singular}`} mutationMode="pessimistic" redirect="list" sx={{ '& .RaEdit-card': { overflow: 'hidden' } }}><FormularioTaxonomia creando={false} /></Edit>;
}
