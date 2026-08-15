export interface CategoriaReceta { nombre: string; slug: string }
export interface EtiquetaReceta { nombre: string; slug: string }

export interface TaxonomiaResumen {
  nombre: string;
  slug: string;
  total_recetas: number;
}

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
  etiquetas: EtiquetaReceta[];
}

export interface IngredienteReceta {
  nombre: string;
  cantidad: number | null;
  unidad: string | null;
  notas: string | null;
  texto_original: string;
  posicion: number;
}

export interface PasoReceta { numero: number; instruccion: string; imagen_url: string | null }

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

export interface Paginacion {
  pagina: number;
  por_pagina: number;
  total: number;
  total_paginas: number;
}

export interface RespuestaRecetas { datos: RecetaResumen[]; paginacion: Paginacion }

export interface ParametrosRecetas {
  pagina: number;
  porPagina: number;
  buscar?: string;
  categoria?: string;
  etiqueta?: string;
}

export class ErrorApi extends Error {
  readonly status: number;
  constructor(message: string, status: number) { super(message); this.status = status; }
}

export async function obtenerRecetas(parametros: ParametrosRecetas, signal?: AbortSignal): Promise<RespuestaRecetas> {
  const query = new URLSearchParams({
    pagina: String(parametros.pagina),
    por_pagina: String(parametros.porPagina),
  });
  if (parametros.buscar) query.set('buscar', parametros.buscar);
  if (parametros.categoria) query.set('categoria', parametros.categoria);
  if (parametros.etiqueta) query.set('etiqueta', parametros.etiqueta);

  const respuesta = await fetch(`/api/recetas?${query}`, { signal });
  if (!respuesta.ok) throw new ErrorApi('No se pudieron cargar las recetas', respuesta.status);
  return respuesta.json() as Promise<RespuestaRecetas>;
}

export async function obtenerCategorias(signal?: AbortSignal): Promise<TaxonomiaResumen[]> {
  const respuesta = await fetch('/api/categorias', { signal });
  if (!respuesta.ok) throw new ErrorApi('No se pudieron cargar las categorías', respuesta.status);
  return respuesta.json() as Promise<TaxonomiaResumen[]>;
}

export async function obtenerEtiquetas(signal?: AbortSignal): Promise<TaxonomiaResumen[]> {
  const respuesta = await fetch('/api/etiquetas', { signal });
  if (!respuesta.ok) throw new ErrorApi('No se pudieron cargar las etiquetas', respuesta.status);
  return respuesta.json() as Promise<TaxonomiaResumen[]>;
}

export async function obtenerReceta(id: number): Promise<RecetaDetalle> {
  const respuesta = await fetch(`/api/recetas/${id}`);
  if (!respuesta.ok) throw new ErrorApi('No se pudo cargar la receta', respuesta.status);
  return respuesta.json() as Promise<RecetaDetalle>;
}
