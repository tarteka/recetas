export interface RecetaResumen {
  id: number;
  titulo: string;
  descripcion: string | null;
  imagen_url: string | null;
  fuente_nombre: string | null;
  raciones: number | null;
  tiempo_total_min: number | null;
  creado_en: string;
  categorias: CategoriaReceta[];
}

export interface IngredienteReceta {
  nombre: string;
  cantidad: number | null;
  unidad: string | null;
  notas: string | null;
  texto_original: string;
  posicion: number;
}

export interface PasoReceta {
  numero: number;
  instruccion: string;
  imagen_url: string | null;
}

export interface CategoriaReceta {
  nombre: string;
  slug: string;
}

export interface EtiquetaReceta {
  nombre: string;
  slug: string;
}

export interface RecetaDetalle {
  id: number;
  titulo: string;
  descripcion: string | null;
  fuente_url: string | null;
  fuente_nombre: string | null;
  imagen_url: string | null;
  raciones: number | null;
  tiempo_preparacion_min: number | null;
  tiempo_coccion_min: number | null;
  tiempo_total_min: number | null;
  creado_en: string;
  actualizado_en: string | null;
  ingredientes: IngredienteReceta[];
  pasos: PasoReceta[];
  categorias: CategoriaReceta[];
  etiquetas: EtiquetaReceta[];
}

/**
 * Obtiene el listado resumido de recetas.
 */
export class ErrorApi extends Error {
  readonly status: number;

  constructor(message: string, status: number) {
    super(message);
    this.status = status;
  }
}

export async function obtenerRecetas(): Promise<RecetaResumen[]> {
  const respuesta = await fetch('/api/recetas');

  if (!respuesta.ok) {
    throw new ErrorApi('No se pudieron cargar las recetas', respuesta.status);
  }

  return respuesta.json() as Promise<RecetaResumen[]>;
}

/**
 * Obtiene todos los datos de una receta concreta.
 */
export async function obtenerReceta(id: number): Promise<RecetaDetalle> {
  const respuesta = await fetch(`/api/recetas/${id}`);

  if (!respuesta.ok) {
    throw new ErrorApi('No se pudo cargar la receta', respuesta.status);
  }

  return respuesta.json() as Promise<RecetaDetalle>;
}
