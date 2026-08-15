import {
  Datagrid,
  DateField,
  FunctionField,
  ImageField,
  List,
  NumberField,
  TextField,
} from 'react-admin';
import type { RecetaResumenAdmin } from '../types';

function nombresCategorias(receta: RecetaResumenAdmin): string {
  return receta.categorias.join(', ') || 'Sin categoría';
}

export function RecetaList() {
  return (
    <List
      perPage={10}
      sort={{ field: 'creado_en', order: 'DESC' }}
      title="Recetas"
    >
      <Datagrid bulkActionButtons={false} rowClick="edit">
        <ImageField
          source="imagen_url"
          label="Imagen"
          sortable={false}
          sx={{ '& img': { width: 96, height: 64, objectFit: 'cover', borderRadius: 1 } }}
        />
        <NumberField source="id" label="ID" sortable={false} />
        <TextField source="titulo" label="Título" sortable={false} />
        <NumberField source="tiempo_total_min" label="Tiempo total (min)" sortable={false} />
        <NumberField source="raciones" label="Raciones" sortable={false} />
        <FunctionField<RecetaResumenAdmin>
          label="Categorías"
          sortable={false}
          render={nombresCategorias}
        />
        <DateField source="creado_en" label="Creada" showTime sortable={false} />
      </Datagrid>
    </List>
  );
}
