interface Props { valor: string; onChange: (valor: string) => void }

export default function BuscadorRecetas({ valor, onChange }: Props) {
  return <div className="buscador">
    <label htmlFor="buscar-recetas">Buscar recetas</label>
    <input id="buscar-recetas" type="search" placeholder="Buscar por título o descripción" value={valor} onChange={(e) => onChange(e.target.value)} />
  </div>;
}
