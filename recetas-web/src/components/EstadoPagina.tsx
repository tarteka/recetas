import type { ReactNode } from 'react';

interface Props { titulo: string; descripcion?: string; icono?: ReactNode; cargando?: boolean; onReintentar?: () => void }

export default function EstadoPagina({ titulo, descripcion, icono, cargando = false, onReintentar }: Props) {
  return <section className="estado-pagina" aria-live="polite" aria-busy={cargando}>
    {cargando ? <span className="spinner" aria-hidden="true" /> : icono}
    <h2>{titulo}</h2>
    {descripcion && <p>{descripcion}</p>}
    {onReintentar && <button className="boton" type="button" onClick={onReintentar}>Reintentar</button>}
  </section>;
}
