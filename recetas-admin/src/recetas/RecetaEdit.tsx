import { Box, Typography } from '@mui/material';
import {
  ArrayInput,
  Button,
  Edit,
  NumberInput,
  SaveButton,
  SimpleFormIterator,
  TabbedForm,
  TextInput,
  Toolbar,
  minValue,
  required,
  useRedirect,
} from 'react-admin';
import { ClasificacionInput } from './ClasificacionInput';
import { ImagenUpload } from './ImagenUpload';

function BarraGuardado() {
  const redirect = useRedirect();

  return (
    <Toolbar className="editor-receta__toolbar">
      <SaveButton label="Guardar cambios" />
      <Button
        label="Cancelar"
        onClick={() => redirect('list', 'recetas')}
        variant="outlined"
      />
    </Toolbar>
  );
}

const validarTitulo = [required('El título es obligatorio')];
const validarPositivo = [minValue(0, 'No puede ser negativo')];

export function RecetaEdit() {
  return (
    <Edit
      className="editor-receta"
      mutationMode="pessimistic"
      redirect="list"
      title="Editar receta"
      sx={{
        '& .RaEdit-card': {
          overflow: 'hidden',
        },
      }}
    >
      <TabbedForm toolbar={<BarraGuardado />} warnWhenUnsavedChanges>
        <TabbedForm.Tab label="Información">
          <Box sx={{ width: '100%' }}>
            <TextInput
              source="titulo"
              label="Título"
              validate={validarTitulo}
              fullWidth
            />
            <TextInput
              source="descripcion"
              label="Descripción"
              multiline
              minRows={4}
              fullWidth
            />
            <Box
              sx={{
                display: 'grid',
                gridTemplateColumns: { xs: '1fr', sm: 'repeat(2, 1fr)', lg: 'repeat(4, 1fr)' },
                gap: 2,
              }}
            >
              <NumberInput source="raciones" label="Raciones" validate={validarPositivo} fullWidth />
              <NumberInput
                source="tiempo_preparacion_min"
                label="Preparación (min)"
                validate={validarPositivo}
                fullWidth
              />
              <NumberInput
                source="tiempo_coccion_min"
                label="Cocción (min)"
                validate={validarPositivo}
                fullWidth
              />
              <NumberInput
                source="tiempo_total_min"
                label="Total (min)"
                validate={validarPositivo}
                fullWidth
              />
            </Box>
          </Box>
        </TabbedForm.Tab>

        <TabbedForm.Tab label="Ingredientes">
          <Typography variant="body2" color="text.secondary" sx={{ mb: 2 }}>
            Arrastra las filas para cambiar el orden de presentación.
          </Typography>
          <ArrayInput source="ingredientes" label={false} fullWidth>
            <SimpleFormIterator inline fullWidth>
              <NumberInput source="cantidad" label="Cantidad" fullWidth sx={{ flex: '0 1 160px', minWidth: 120 }} />
              <TextInput source="unidad" label="Unidad" fullWidth sx={{ flex: '0 1 180px', minWidth: 130 }} />
              <TextInput
                source="nombre"
                label="Ingrediente"
                validate={required('Indica el ingrediente')}
                fullWidth
                sx={{ minWidth: 220, flex: 2 }}
              />
              <TextInput source="notas" label="Notas" fullWidth sx={{ minWidth: 200, flex: 2 }} />
            </SimpleFormIterator>
          </ArrayInput>
        </TabbedForm.Tab>

        <TabbedForm.Tab label="Elaboración">
          <Typography variant="body2" color="text.secondary" sx={{ mb: 2 }}>
            El número de cada paso se recalcula automáticamente según su posición.
          </Typography>
          <ArrayInput source="pasos" label={false} fullWidth>
            <SimpleFormIterator
              fullWidth
              getItemLabel={(index) => 'Paso ' + (index + 1)}
            >
              <TextInput
                source="instruccion"
                label="Instrucción"
                validate={required('La instrucción es obligatoria')}
                multiline
                minRows={3}
                fullWidth
              />
            </SimpleFormIterator>
          </ArrayInput>
        </TabbedForm.Tab>

        <TabbedForm.Tab label="Clasificación">
          <Box className="editor-receta__clasificacion">
            <Box>
              <Typography variant="h6" sx={{ mb: 0.5 }}>Categorías</Typography>
              <Typography variant="body2" color="text.secondary" sx={{ mb: 2 }}>
                Agrupan la receta dentro de las secciones principales.
              </Typography>
              <ClasificacionInput
                source="categorias"
                label="Añadir categorías"
                endpoint="/api/categorias"
                nombreSingular="categoría"
              />
            </Box>
            <Box>
              <Typography variant="h6" sx={{ mb: 0.5 }}>Etiquetas</Typography>
              <Typography variant="body2" color="text.secondary" sx={{ mb: 2 }}>
                Describen características, técnicas o estilos de la receta.
              </Typography>
              <ClasificacionInput
                source="etiquetas"
                label="Añadir etiquetas"
                endpoint="/api/etiquetas"
                nombreSingular="etiqueta"
              />
            </Box>
          </Box>
        </TabbedForm.Tab>

        <TabbedForm.Tab label="Fuente e imagen">
          <Box sx={{ width: '100%' }}>
            <ImagenUpload />
            <TextInput source="imagen_url" label="URL de imagen" disabled fullWidth />
            <TextInput source="fuente_nombre" label="Nombre de la fuente" fullWidth />
            <TextInput source="fuente_url" label="URL de la fuente" type="url" fullWidth />
          </Box>
        </TabbedForm.Tab>
      </TabbedForm>
    </Edit>
  );
}
