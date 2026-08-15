import { useEffect, useMemo, useRef, useState } from 'react';
import {
  Box,
  Button,
  CircularProgress,
  Dialog,
  DialogActions,
  DialogContent,
  DialogTitle,
  LinearProgress,
  Typography,
} from '@mui/material';
import CloudUploadOutlinedIcon from '@mui/icons-material/CloudUploadOutlined';
import DeleteOutlineIcon from '@mui/icons-material/DeleteOutlined';
import ImageOutlinedIcon from '@mui/icons-material/ImageOutlined';
import { useNotify, useRecordContext } from 'react-admin';
import { useFormContext } from 'react-hook-form';

const BYTES_MAXIMOS = 10 * 1024 * 1024;
const TIPOS_PERMITIDOS = ['image/jpeg', 'image/png', 'image/webp'];

interface RecetaConImagen {
  id: number;
  imagen_url: string | null;
}

interface RespuestaImagen {
  imagen_url?: unknown;
  error?: unknown;
}

function tamanoLegible(bytes: number): string {
  return bytes >= 1024 * 1024
    ? `${(bytes / (1024 * 1024)).toFixed(1)} MB`
    : `${Math.ceil(bytes / 1024)} KB`;
}

export function ImagenUpload() {
  const receta = useRecordContext<RecetaConImagen>();
  const { setValue } = useFormContext();
  const notify = useNotify();
  const inputRef = useRef<HTMLInputElement>(null);
  const [archivo, setArchivo] = useState<File | null>(null);
  const [imagenActual, setImagenActual] = useState(receta?.imagen_url ?? null);
  const [arrastrando, setArrastrando] = useState(false);
  const [subiendo, setSubiendo] = useState(false);
  const [progreso, setProgreso] = useState(0);
  const [confirmarEliminacion, setConfirmarEliminacion] = useState(false);
  const [eliminando, setEliminando] = useState(false);

  const preview = useMemo(() => archivo ? URL.createObjectURL(archivo) : null, [archivo]);

  useEffect(() => () => {
    if (preview) URL.revokeObjectURL(preview);
  }, [preview]);

  const limpiarSeleccion = () => {
    setArchivo(null);
    setProgreso(0);
    if (inputRef.current) inputRef.current.value = '';
  };

  const seleccionar = (candidato?: File) => {
    if (!candidato) return;
    if (!TIPOS_PERMITIDOS.includes(candidato.type)) {
      notify('Usa una imagen JPG, PNG o WebP', { type: 'warning' });
      return;
    }
    if (candidato.size > BYTES_MAXIMOS) {
      notify('La imagen no puede superar los 10 MB', { type: 'warning' });
      return;
    }
    setArchivo(candidato);
    setProgreso(0);
  };

  const subir = async () => {
    if (!archivo || !receta?.id) return;
    setSubiendo(true);
    setProgreso(0);
    try {
      const body = await new Promise<RespuestaImagen>((resolve, reject) => {
        const request = new XMLHttpRequest();
        request.open('POST', `/api/admin/recetas/${receta.id}/imagen`);
        request.withCredentials = true;
        request.setRequestHeader('Accept', 'application/json');
        request.setRequestHeader('Content-Type', archivo.type);
        request.upload.onprogress = (event) => {
          if (event.lengthComputable) setProgreso(Math.round((event.loaded / event.total) * 100));
        };
        request.onerror = () => reject(new Error('No se pudo conectar con el servidor'));
        request.onload = () => {
          let response: RespuestaImagen = {};
          try {
            response = JSON.parse(request.responseText) as RespuestaImagen;
          } catch {
            // La validación posterior ofrece un mensaje seguro.
          }
          if (request.status < 200 || request.status >= 300) {
            reject(new Error(typeof response.error === 'string' ? response.error : 'No se pudo subir la imagen'));
            return;
          }
          resolve(response);
        };
        request.send(archivo);
      });

      if (typeof body.imagen_url !== 'string') throw new Error('La API no devolvió la imagen guardada');
      setImagenActual(body.imagen_url);
      setValue('imagen_url', body.imagen_url, { shouldDirty: false });
      limpiarSeleccion();
      notify('Imagen convertida a WebP y actualizada', { type: 'success' });
    } catch (error) {
      notify(error instanceof Error ? error.message : 'No se pudo subir la imagen', { type: 'error' });
    } finally {
      setSubiendo(false);
    }
  };

  const eliminar = async () => {
    if (!receta?.id) return;
    setEliminando(true);
    try {
      const response = await fetch(`/api/admin/recetas/${receta.id}/imagen`, {
        method: 'DELETE',
        credentials: 'include',
        headers: { Accept: 'application/json' },
      });
      const body = await response.json().catch(() => null) as RespuestaImagen | null;
      if (!response.ok) {
        throw new Error(typeof body?.error === 'string' ? body.error : 'No se pudo eliminar la imagen');
      }
      setImagenActual(null);
      setValue('imagen_url', null, { shouldDirty: false });
      limpiarSeleccion();
      setConfirmarEliminacion(false);
      notify('Imagen eliminada', { type: 'success' });
    } catch (error) {
      notify(error instanceof Error ? error.message : 'No se pudo eliminar la imagen', { type: 'error' });
    } finally {
      setEliminando(false);
    }
  };

  return (
    <Box className="imagen-upload">
      <Box>
        <Typography variant="h6">Imagen de la receta</Typography>
        <Typography variant="body2" color="text.secondary">
          La imagen se recorta a 3:2, se redimensiona a 1200 × 800 px y se guarda como WebP.
        </Typography>
      </Box>

      <Box className="imagen-upload__preview">
        {preview || imagenActual ? (
          <>
            <img src={preview ?? imagenActual ?? ''} alt="Previsualización de la receta" />
            {preview && <span className="imagen-upload__estado">Vista previa</span>}
          </>
        ) : (
          <Box className="imagen-upload__vacia">
            <ImageOutlinedIcon />
            <Typography color="text.secondary">Esta receta todavía no tiene imagen.</Typography>
          </Box>
        )}
      </Box>

      <Box
        className={`imagen-upload__dropzone${arrastrando ? ' is-dragging' : ''}`}
        onDragEnter={(event) => { event.preventDefault(); setArrastrando(true); }}
        onDragOver={(event) => event.preventDefault()}
        onDragLeave={() => setArrastrando(false)}
        onDrop={(event) => {
          event.preventDefault();
          setArrastrando(false);
          seleccionar(event.dataTransfer.files[0]);
        }}
      >
        <CloudUploadOutlinedIcon />
        <Box>
          <Typography sx={{ fontWeight: 700 }}>Arrastra una imagen aquí</Typography>
          <Typography variant="body2" color="text.secondary">JPG, PNG o WebP · máximo 10 MB</Typography>
        </Box>
        <Button variant="outlined" onClick={() => inputRef.current?.click()} disabled={subiendo || eliminando}>
          {imagenActual ? 'Sustituir imagen' : 'Seleccionar archivo'}
        </Button>
        <input
          ref={inputRef}
          type="file"
          accept="image/jpeg,image/png,image/webp"
          hidden
          onChange={(event) => seleccionar(event.target.files?.[0])}
        />
      </Box>

      {archivo && (
        <Box className="imagen-upload__archivo">
          <Box>
            <Typography variant="body2" title={archivo.name}>{archivo.name}</Typography>
            <Typography variant="caption" color="text.secondary">{tamanoLegible(archivo.size)}</Typography>
          </Box>
          {subiendo && <LinearProgress variant={progreso > 0 ? 'determinate' : 'indeterminate'} value={progreso} />}
          <Box className="imagen-upload__acciones">
            <Button onClick={limpiarSeleccion} disabled={subiendo}>Cancelar</Button>
            <Button variant="contained" onClick={subir} disabled={subiendo}>
              {subiendo ? <><CircularProgress size={18} color="inherit" /> Subiendo {progreso || ''}{progreso ? '%' : ''}</> : 'Guardar imagen'}
            </Button>
          </Box>
        </Box>
      )}

      {imagenActual && !archivo && (
        <Box className="imagen-upload__pie">
          <Typography variant="caption" color="text.secondary">Imagen WebP normalizada y guardada por el recetario.</Typography>
          <Button color="error" startIcon={<DeleteOutlineIcon />} onClick={() => setConfirmarEliminacion(true)}>
            Eliminar imagen
          </Button>
        </Box>
      )}

      <Dialog open={confirmarEliminacion} onClose={() => !eliminando && setConfirmarEliminacion(false)}>
        <DialogTitle>¿Eliminar la imagen?</DialogTitle>
        <DialogContent>
          La receta quedará sin imagen. Esta acción no elimina la receta y podrás subir otra después.
        </DialogContent>
        <DialogActions>
          <Button onClick={() => setConfirmarEliminacion(false)} disabled={eliminando}>Cancelar</Button>
          <Button color="error" variant="contained" onClick={eliminar} disabled={eliminando}>
            {eliminando ? <CircularProgress size={20} color="inherit" /> : 'Eliminar imagen'}
          </Button>
        </DialogActions>
      </Dialog>
    </Box>
  );
}
