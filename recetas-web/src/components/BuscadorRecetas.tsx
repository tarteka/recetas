import { useEffect, useState } from 'react';

interface Props { valorInicial: string; onBuscar: (valor: string) => void }

export default function BuscadorRecetas({ valorInicial, onBuscar }: Props) {
  const [valor, setValor] = useState(valorInicial);

  useEffect(() => {
    if (valor === valorInicial) return;
    const temporizador = window.setTimeout(() => onBuscar(valor), 300);
    return () => window.clearTimeout(temporizador);
  }, [onBuscar, valor, valorInicial]);

  return <form className="buscador" role="search" onSubmit={(evento) => { evento.preventDefault(); onBuscar(valor); }}>
    <label htmlFor="buscar-recetas">Buscar recetas</label>
    <input id="buscar-recetas" type="search" placeholder="Título o descripción" value={valor} onChange={(evento) => setValor(evento.target.value)} />
  </form>;
}
