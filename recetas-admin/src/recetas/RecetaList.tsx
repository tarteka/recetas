import { Box, Chip, Typography, useMediaQuery } from '@mui/material';
import type { Theme } from '@mui/material/styles';
import {
  Datagrid,
  DateField,
  FunctionField,
  ImageField,
  List,
  NumberField,
  SimpleList,
  TextField,
} from 'react-admin';
import type { RecetaResumenAdmin } from '../types';

function taxonomias(valores: string[], vacio: string) {
  if (valores.length === 0) return <Typography variant="body2" color="text.secondary">{vacio}</Typography>;
  return (
    <Box className="lista-recetas__taxonomias">
      {valores.map((valor) => <Chip key={valor} label={valor} size="small" />)}
    </Box>
  );
}

function fechaPublicacion(fecha: string): string {
  return new Intl.DateTimeFormat('es-ES', {
    day: '2-digit',
    month: 'short',
    year: 'numeric',
  }).format(new Date(fecha));
}

function Miniatura({ receta }: { receta: RecetaResumenAdmin }) {
  return (
    <Box className="lista-recetas-movil__imagen">
      {receta.imagen_url ? (
        <img src={receta.imagen_url} alt="" />
      ) : (
        <Typography variant="caption">Sin imagen</Typography>
      )}
    </Box>
  );
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
          <Box className="lista-recetas-movil__meta">
            <Typography component="span" variant="caption">ID {receta.id}</Typography>
            <Typography component="span" variant="caption">
              Publicada {fechaPublicacion(receta.creado_en)}
            </Typography>
          </Box>
        </Box>
      )}
      secondaryText={(receta) => (
        <Box className="lista-recetas-movil__datos">
          <Typography variant="body2">
            <strong>Categorías:</strong> {receta.categorias.join(', ') || 'Sin categoría'}
          </Typography>
          <Typography variant="body2">
            <strong>Etiquetas:</strong> {receta.etiquetas.join(', ') || 'Sin etiquetas'}
          </Typography>
        </Box>
      )}
    />
  );
}

function ListadoEscritorio() {
  return (
    <Datagrid bulkActionButtons={false} rowClick="edit" className="lista-recetas-escritorio">
      <ImageField
        source="imagen_url"
        label="Imagen"
        sortable={false}
        sx={{ '& img': { width: 88, height: 59, objectFit: 'cover', borderRadius: 1.5 } }}
      />
      <NumberField source="id" label="ID" sortable={false} />
      <TextField source="titulo" label="Título" sortable={false} />
      <FunctionField<RecetaResumenAdmin>
        label="Categorías"
        sortable={false}
        render={(receta) => taxonomias(receta.categorias, 'Sin categoría')}
      />
      <FunctionField<RecetaResumenAdmin>
        label="Etiquetas"
        sortable={false}
        render={(receta) => taxonomias(receta.etiquetas, 'Sin etiquetas')}
      />
      <DateField source="creado_en" label="Publicada" sortable={false} />
    </Datagrid>
  );
}

export function RecetaList() {
  const esMovil = useMediaQuery((theme: Theme) => theme.breakpoints.down('sm'));

  return (
    <List
      className="lista-recetas"
      perPage={10}
      sort={{ field: 'creado_en', order: 'DESC' }}
      title="Recetas"
    >
      {esMovil ? <ListadoMovil /> : <ListadoEscritorio />}
    </List>
  );
}
