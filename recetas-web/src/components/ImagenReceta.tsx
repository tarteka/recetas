import { useState } from 'react';

interface Props { imagenUrl: string | null; alt: string; className?: string }

export default function ImagenReceta({ imagenUrl, alt, className = '' }: Props) {
  const [fallo, setFallo] = useState(false);
  const clases = `imagen-receta ${className}`.trim();
  if (!imagenUrl || fallo) return <div className={`${clases} imagen-placeholder`} role="img" aria-label={`Sin imagen: ${alt}`}><span aria-hidden="true">♨</span></div>;
  return <img className={clases} src={imagenUrl} alt={alt} onError={() => setFallo(true)} />;
}
