import type { RaRecord } from 'react-admin';

export interface CategoriaReceta {
  nombre: string;
  slug: string;
}

export interface EtiquetaReceta {
  nombre: string;
  slug: string;
}

export interface RecetaResumen extends RaRecord<number> {
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

export interface RecetaResumenAdmin extends RaRecord<number> {
  id: number;
  titulo: string;
  imagen_url: string | null;
  creado_en: string;
  categorias: string[];
  etiquetas: string[];
}

export interface RecetaDetalle extends RecetaResumen {
  fuente_url: string | null;
  tiempo_preparacion_min: number | null;
  tiempo_coccion_min: number | null;
  actualizado_en: string | null;
  ingredientes: unknown[];
  pasos: unknown[];
}

export interface RespuestaRecetas {
  datos: RecetaResumen[];
  paginacion: {
    pagina: number;
    por_pagina: number;
    total: number;
    total_paginas: number;
  };
}
