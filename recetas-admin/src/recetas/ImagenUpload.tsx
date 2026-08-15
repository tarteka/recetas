import { useEffect, useMemo, useRef, useState } from 'react';
import { Box, Button, CircularProgress, Typography } from '@mui/material';
import CloudUploadOutlinedIcon from '@mui/icons-material/CloudUploadOutlined';
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

export function ImagenUpload() {
  const receta = useRecordContext<RecetaConImagen>();
  const { setValue } = useFormContext();
  const notify = useNotify();
  const inputRef = useRef<HTMLInputElement>(null);
  const [archivo, setArchivo] = useState<File | null>(null);
  const [imagenActual, setImagenActual] = useState(receta?.imagen_url ?? null);
  const [arrastrando, setArrastrando] = useState(false);
  const [subiendo, setSubiendo] = useState(false);

  const preview = useMemo(() => archivo ? URL.createObjectURL(archivo) : null, [archivo]);

  useEffect(() => () => {
    if (preview) URL.revokeObjectURL(preview);
  }, [preview]);

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
  };

  const subir = async () => {
    if (!archivo || !receta?.id) return;
    setSubiendo(true);
    try {
      const response = await fetch(`/api/admin/recetas/${receta.id}/imagen`, {
        method: 'POST',
        credentials: 'include',
        headers: {
          Accept: 'application/json',
          'Content-Type': archivo.type,
        },
        body: archivo,
      });
      const body = await response.json().catch(() => null) as RespuestaImagen | null;
      if (!response.ok) {
        throw new Error(typeof body?.error === 'string' ? body.error : 'No se pudo subir la imagen');
      }
      if (typeof body?.imagen_url !== 'string') {
        throw new Error('La API no devolvió la imagen guardada');
      }

      setImagenActual(body.imagen_url);
      setValue('imagen_url', body.imagen_url, { shouldDirty: false });
      setArchivo(null);
      if (inputRef.current) inputRef.current.value = '';
      notify('Imagen actualizada correctamente', { type: 'success' });
    } catch (error) {
      notify(error instanceof Error ? error.message : 'No se pudo subir la imagen', { type: 'error' });
    } finally {
      setSubiendo(false);
    }
  };

  return (
    <Box className="imagen-upload">
      <Typography variant="h6">Imagen de la receta</Typography>
      <Typography variant="body2" color="text.secondary">
        Se recortará automáticamente a formato 3:2 y se guardará como WebP.
      </Typography>

      <Box className="imagen-upload__preview">
        {preview || imagenActual ? (
          <img src={preview ?? imagenActual ?? ''} alt="Previsualización de la receta" />
        ) : (
          <Typography color="text.secondary">Esta receta todavía no tiene imagen.</Typography>
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
        <Button variant="outlined" onClick={() => inputRef.current?.click()}>
          Seleccionar archivo
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
        <Box className="imagen-upload__acciones">
          <Typography variant="body2" title={archivo.name}>{archivo.name}</Typography>
          <Button onClick={() => setArchivo(null)} disabled={subiendo}>Cancelar</Button>
          <Button variant="contained" onClick={subir} disabled={subiendo}>
            {subiendo ? <CircularProgress size={20} color="inherit" /> : 'Subir imagen'}
          </Button>
        </Box>
      )}
    </Box>
  );
}
