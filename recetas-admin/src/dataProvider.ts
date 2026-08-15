import { HttpError } from 'react-admin';
import type {
  DataProvider,
  GetListParams,
  GetListResult,
  GetOneParams,
  GetOneResult,
  RaRecord,
} from 'react-admin';
import type { RespuestaRecetas } from './types';

const RECURSO_RECETAS = 'recetas';

function comprobarRecurso(resource: string): void {
  if (resource !== RECURSO_RECETAS) {
    throw new Error(`Recurso no soportado: ${resource}`);
  }
}

function valorFiltro(filter: Record<string, unknown>, nombre: string): string | undefined {
  const valor = filter[nombre];
  return typeof valor === 'string' && valor.trim() !== '' ? valor.trim() : undefined;
}

async function solicitarJson(url: string): Promise<unknown> {
  const response = await fetch(url, { headers: { Accept: 'application/json' } });
  if (!response.ok) {
    throw new HttpError('No se pudo completar la petición', response.status);
  }
  return response.json() as Promise<unknown>;
}

function esRespuestaRecetas(value: unknown): value is RespuestaRecetas {
  if (typeof value !== 'object' || value === null) return false;
  const response = value as Record<string, unknown>;
  if (!Array.isArray(response.datos)) return false;
  if (typeof response.paginacion !== 'object' || response.paginacion === null) return false;
  return typeof (response.paginacion as Record<string, unknown>).total === 'number';
}

async function getList<RecordType extends RaRecord = RaRecord>(
  resource: string,
  params: GetListParams,
): Promise<GetListResult<RecordType>> {
  comprobarRecurso(resource);

  const query = new URLSearchParams({
    pagina: String(params.pagination?.page ?? 1),
    por_pagina: String(params.pagination?.perPage ?? 10),
  });
  const buscar = valorFiltro(params.filter, 'buscar') ?? valorFiltro(params.filter, 'q');
  const categoria = valorFiltro(params.filter, 'categoria');
  const etiqueta = valorFiltro(params.filter, 'etiqueta');
  if (buscar) query.set('buscar', buscar);
  if (categoria) query.set('categoria', categoria);
  if (etiqueta) query.set('etiqueta', etiqueta);

  const response = await solicitarJson(`/api/recetas?${query}`);
  if (!esRespuestaRecetas(response)) {
    throw new Error('La API devolvió un listado de recetas no válido');
  }

  return {
    data: response.datos as unknown as RecordType[],
    total: response.paginacion.total,
  };
}

async function getOne<RecordType extends RaRecord = RaRecord>(
  resource: string,
  params: GetOneParams<RecordType>,
): Promise<GetOneResult<RecordType>> {
  comprobarRecurso(resource);
  const response = await solicitarJson(`/api/recetas/${encodeURIComponent(String(params.id))}`);
  if (typeof response !== 'object' || response === null || !('id' in response)) {
    throw new Error('La API devolvió una receta no válida');
  }
  return { data: response as unknown as RecordType };
}

function noSoportado(operacion: string, resource: string): Promise<never> {
  return Promise.reject(new Error(`${operacion} no está disponible para ${resource} en esta versión de solo lectura`));
}

export const dataProvider: DataProvider = {
  getList,
  getOne,
  getMany: (resource) => noSoportado('getMany', resource),
  getManyReference: (resource) => noSoportado('getManyReference', resource),
  create: (resource) => noSoportado('create', resource),
  update: (resource) => noSoportado('update', resource),
  updateMany: (resource) => noSoportado('updateMany', resource),
  delete: (resource) => noSoportado('delete', resource),
  deleteMany: (resource) => noSoportado('deleteMany', resource),
};
