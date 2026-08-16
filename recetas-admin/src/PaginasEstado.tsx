import { Box, Button, Typography } from '@mui/material';
import ErrorOutlineOutlinedIcon from '@mui/icons-material/ErrorOutlineOutlined';
import SearchOffOutlinedIcon from '@mui/icons-material/SearchOffOutlined';
import { useNavigate } from 'react-router-dom';

function EstadoPagina({ encontrado }: { encontrado: boolean }) {
  const navigate = useNavigate();
  return (
    <Box className="estado-admin" role="alert">
      {encontrado ? <ErrorOutlineOutlinedIcon /> : <SearchOffOutlinedIcon />}
      <Typography component="h1" variant="h5">{encontrado ? 'No se pudo mostrar esta página' : 'Página no encontrada'}</Typography>
      <Typography color="text.secondary">
        {encontrado ? 'Se produjo un error inesperado. Puedes intentarlo de nuevo o volver al listado.' : 'La dirección no existe o el enlace ya no está disponible.'}
      </Typography>
      <Box className="estado-admin__acciones">
        {encontrado && <Button variant="outlined" onClick={() => window.location.reload()}>Intentar de nuevo</Button>}
        <Button variant="contained" onClick={() => navigate('/recetas')}>Volver a recetas</Button>
      </Box>
    </Box>
  );
}

export function ErrorAdmin() { return <EstadoPagina encontrado />; }
export function PaginaNoEncontrada() { return <EstadoPagina encontrado={false} />; }
