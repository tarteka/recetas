import { useState } from 'react';
import {
  Button,
  Dialog,
  DialogActions,
  DialogContent,
  DialogContentText,
  DialogTitle,
} from '@mui/material';
import ArchiveOutlinedIcon from '@mui/icons-material/ArchiveOutlined';
import DeleteForeverOutlinedIcon from '@mui/icons-material/DeleteForeverOutlined';
import RestoreOutlinedIcon from '@mui/icons-material/RestoreOutlined';
import { useNotify, useRecordContext, useRedirect } from 'react-admin';

interface RecetaArchivable {
  id: number;
  titulo: string;
  archivada_en?: string | null;
}

export function AccionArchivado() {
  const receta = useRecordContext<RecetaArchivable>();
  const notify = useNotify();
  const redirect = useRedirect();
  const [abierto, setAbierto] = useState(false);
  const [procesando, setProcesando] = useState(false);
  const [eliminarAbierto, setEliminarAbierto] = useState(false);
  const [eliminando, setEliminando] = useState(false);

  if (!receta) return null;
  const archivada = Boolean(receta.archivada_en);

  const confirmar = async () => {
    setProcesando(true);
    try {
      const response = await fetch(
        archivada
          ? `/api/admin/recetas/${receta.id}/restaurar`
          : `/api/admin/recetas/${receta.id}`,
        {
          method: archivada ? 'POST' : 'DELETE',
          credentials: 'include',
          headers: { Accept: 'application/json' },
        },
      );
      const body = await response.json().catch(() => null) as { error?: unknown } | null;
      if (!response.ok) {
        throw new Error(typeof body?.error === 'string' ? body.error : 'No se pudo completar la acción');
      }
      notify(archivada ? 'Receta restaurada' : 'Receta archivada', { type: 'success' });
      redirect('list', 'recetas');
    } catch (error) {
      notify(error instanceof Error ? error.message : 'No se pudo completar la acción', { type: 'error' });
    } finally {
      setProcesando(false);
      setAbierto(false);
    }
  };

  const eliminarDefinitivamente = async () => {
    setEliminando(true);
    try {
      const response = await fetch(`/api/admin/recetas/${receta.id}/definitiva`, {
        method: 'DELETE',
        credentials: 'include',
        headers: { Accept: 'application/json' },
      });
      const body = await response.json().catch(() => null) as { error?: unknown } | null;
      if (!response.ok) {
        throw new Error(typeof body?.error === 'string' ? body.error : 'No se pudo eliminar la receta');
      }
      notify('Receta eliminada definitivamente', { type: 'success' });
      redirect('list', 'recetas');
    } catch (error) {
      notify(error instanceof Error ? error.message : 'No se pudo eliminar la receta', { type: 'error' });
    } finally {
      setEliminando(false);
      setEliminarAbierto(false);
    }
  };

  return (
    <>
      <Button
        className="editor-receta__archivar"
        type="button"
        color={archivada ? 'primary' : 'warning'}
        variant="outlined"
        startIcon={archivada ? <RestoreOutlinedIcon /> : <ArchiveOutlinedIcon />}
        onClick={() => setAbierto(true)}
      >
        {archivada ? 'Restaurar receta' : 'Archivar receta'}
      </Button>
      {archivada && (
        <Button
          className="editor-receta__eliminar"
          type="button"
          color="error"
          variant="outlined"
          startIcon={<DeleteForeverOutlinedIcon />}
          onClick={() => setEliminarAbierto(true)}
        >
          Eliminar definitivamente
        </Button>
      )}
      <Dialog open={abierto} onClose={() => !procesando && setAbierto(false)}>
        <DialogTitle>{archivada ? 'Restaurar receta' : 'Archivar receta'}</DialogTitle>
        <DialogContent>
          <DialogContentText>
            {archivada
              ? `“${receta.titulo}” volverá a aparecer en la web pública.`
              : `“${receta.titulo}” dejará de aparecer en la web pública, pero podrás restaurarla después.`}
          </DialogContentText>
        </DialogContent>
        <DialogActions>
          <Button onClick={() => setAbierto(false)} disabled={procesando}>Cancelar</Button>
          <Button variant="contained" onClick={confirmar} disabled={procesando}>
            {archivada ? 'Restaurar' : 'Archivar'}
          </Button>
        </DialogActions>
      </Dialog>
      <Dialog open={eliminarAbierto} onClose={() => !eliminando && setEliminarAbierto(false)}>
        <DialogTitle>Eliminar receta definitivamente</DialogTitle>
        <DialogContent>
          <DialogContentText>
            Vas a eliminar “{receta.titulo}”, junto con sus ingredientes, pasos y relaciones. Esta acción no se puede deshacer.
          </DialogContentText>
        </DialogContent>
        <DialogActions>
          <Button onClick={() => setEliminarAbierto(false)} disabled={eliminando}>Cancelar</Button>
          <Button color="error" variant="contained" onClick={eliminarDefinitivamente} disabled={eliminando}>
            Eliminar definitivamente
          </Button>
        </DialogActions>
      </Dialog>
    </>
  );
}
