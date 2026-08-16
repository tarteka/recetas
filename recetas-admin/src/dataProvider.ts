import { HttpError } from 'react-admin';
import type {
  CreateParams,
  CreateResult,
  DataProvider,
  DeleteParams,
  DeleteResult,
  GetListParams,
  GetListResult,
  GetOneParams,
  GetOneResult,
  RaRecord,
  UpdateParams,
  UpdateResult,
} from 'react-admin';
import type { RespuestaRecetas } from './types';

const RECURSO_RECETAS = 'recetas';
const RECURSOS_TAXONOMIA = ['categorias', 'etiquetas'];

function comprobarRecurso(resource: string): void {
  if (resource !== RECURSO_RECETAS && !RECURSOS_TAXONOMIA.includes(resource)) {
    throw new Error(`Recurso no soportado: ${resource}`);
  }
}

function valorFiltro(filter: Record<string, unknown>, nombre: string): string | undefined {
  const valor = filter[nombre];
  return typeof valor === 'string' && valor.trim() !== '' ? valor.trim() : undefined;
}

async function solicitarJson(url: string, init: RequestInit = {}): Promise<unknown> {
  const response = await fetch(url, {
    ...init,
    credentials: 'include',
    headers: { Accept: 'application/json', ...init.headers },
  });
  if (!response.ok) {
    const body = await response.json().catch(() => null) as { error?: unknown } | null;
    const message = typeof body?.error === 'string'
      ? body.error
      : 'No se pudo completar la petición';
    throw new HttpError(message, response.status, body);
  }
  if (response.status === 204) return null;
  return response.json() as Promise<unknown>;
}

function esRespuestaRecetas(value: unknown): value is RespuestaRecetas {
  if (typeof value !== 'object' || value === null) return false;
  const response = value as Record<string, unknown>;
  if (!Array.isArray(response.datos)) return false;
  if (typeof response.paginacion !== 'object' || response.paginacion === null) return false;
  return typeof (response.paginacion as Record<string, unknown>).total === 'number';
}

function nombresClasificacion(value: unknown): string[] {
  if (!Array.isArray(value)) return [];

  return value
    .map((item) => {
      if (typeof item === 'string') return item;
      if (typeof item === 'object' && item !== null) {
        const nombre = (item as Record<string, unknown>).nombre;
        return typeof nombre === 'string' ? nombre : '';
      }
      return '';
    })
    .filter((nombre) => nombre.trim() !== '');
}

function normalizarReceta(receta: Record<string, unknown>): Record<string, unknown> {
  return {
    ...receta,
    categorias: nombresClasificacion(receta.categorias),
    etiquetas: nombresClasificacion(receta.etiquetas),
  };
}

async function getList<RecordType extends RaRecord = RaRecord>(
  resource: string,
  params: GetListParams,
): Promise<GetListResult<RecordType>> {
  comprobarRecurso(resource);

  if (RECURSOS_TAXONOMIA.includes(resource)) {
    const query = new URLSearchParams({
      pagina: String(params.pagination?.page ?? 1),
      por_pagina: String(params.pagination?.perPage ?? 15),
      ordenar: params.sort?.field ?? 'nombre',
      direccion: params.sort?.order ?? 'ASC',
    });
    const buscarTaxonomia = valorFiltro(params.filter, 'buscar') ?? valorFiltro(params.filter, 'q');
    if (buscarTaxonomia) query.set('buscar', buscarTaxonomia);
    const response = await solicitarJson(`/api/admin/${resource}?${query}`);
    if (!esRespuestaRecetas(response)) throw new Error('La API devolvió un listado no válido');
    return { data: response.datos as unknown as RecordType[], total: response.paginacion.total };
  }

  const query = new URLSearchParams({
    pagina: String(params.pagination?.page ?? 1),
    por_pagina: String(params.pagination?.perPage ?? 10),
  });
  const buscar = valorFiltro(params.filter, 'buscar') ?? valorFiltro(params.filter, 'q');
  const categoria = valorFiltro(params.filter, 'categoria');
  const etiqueta = valorFiltro(params.filter, 'etiqueta');
  const estado = valorFiltro(params.filter, 'estado') ?? 'activas';
  if (buscar) query.set('buscar', buscar);
  if (categoria) query.set('categoria', categoria);
  if (etiqueta) query.set('etiqueta', etiqueta);
  query.set('estado', estado);
  query.set('ordenar', params.sort?.field ?? 'creado_en');
  query.set('direccion', params.sort?.order ?? 'DESC');

  const response = await solicitarJson(`/api/admin/recetas?${query}`);
  if (!esRespuestaRecetas(response)) {
    throw new Error('La API devolvió un listado de recetas no válido');
  }

  return {
    data: response.datos.map((receta) => normalizarReceta(receta)) as unknown as RecordType[],
    total: response.paginacion.total,
  };
}

async function getOne<RecordType extends RaRecord = RaRecord>(
  resource: string,
  params: GetOneParams<RecordType>,
): Promise<GetOneResult<RecordType>> {
  comprobarRecurso(resource);
  if (RECURSOS_TAXONOMIA.includes(resource)) {
    const response = await solicitarJson(`/api/admin/${resource}/${encodeURIComponent(String(params.id))}`);
    if (typeof response !== 'object' || response === null || !('id' in response)) throw new Error('La API devolvió un término no válido');
    return { data: response as RecordType };
  }
  const response = await solicitarJson(`/api/admin/recetas/${encodeURIComponent(String(params.id))}`);
  if (typeof response !== 'object' || response === null || !('id' in response)) {
    throw new Error('La API devolvió una receta no válida');
  }
  return {
    data: normalizarReceta(response as Record<string, unknown>) as unknown as RecordType,
  };
}

function prepararReceta(data: Record<string, unknown>): Record<string, unknown> {
  const ingredientes = Array.isArray(data.ingredientes)
    ? data.ingredientes.map((value) => {
      const ingrediente = value as Record<string, unknown>;
      const textoOriginal = [
        ingrediente.cantidad,
        ingrediente.unidad,
        ingrediente.nombre,
        ingrediente.notas,
      ]
        .filter((part) => part !== null && part !== undefined && String(part).trim() !== '')
        .map(String)
        .join(' ');
      return { ...ingrediente, texto_original: textoOriginal };
    })
    : [];

  const pasos = Array.isArray(data.pasos)
    ? data.pasos.map((value, index) => ({
      ...(value as Record<string, unknown>),
      numero: index + 1,
    }))
    : [];

  return { ...data, ingredientes, pasos };
}


async function create<RecordType extends RaRecord = RaRecord>(
  resource: string,
  params: CreateParams<RecordType>,
): Promise<CreateResult<RecordType>> {
  comprobarRecurso(resource);
  if (RECURSOS_TAXONOMIA.includes(resource)) {
    const response = await solicitarJson(`/api/admin/${resource}`, {
      method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(params.data),
    });
    return { data: response as RecordType };
  }
  const response = await solicitarJson('/api/admin/recetas', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(prepararReceta(params.data as Record<string, unknown>)),
  });
  if (typeof response !== 'object' || response === null || !('id' in response)) {
    throw new Error('La API no devolvió el identificador de la receta creada');
  }
  return { data: normalizarReceta(response as Record<string, unknown>) as unknown as RecordType };
}

async function update<RecordType extends RaRecord = RaRecord>(
  resource: string,
  params: UpdateParams<RecordType>,
): Promise<UpdateResult<RecordType>> {
  comprobarRecurso(resource);
  if (RECURSOS_TAXONOMIA.includes(resource)) {
    const response = await solicitarJson(`/api/admin/${resource}/${encodeURIComponent(String(params.id))}`, {
      method: 'PUT', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(params.data),
    });
    return { data: response as RecordType };
  }
  const response = await solicitarJson(
    `/api/admin/recetas/${encodeURIComponent(String(params.id))}`,
    {
      method: 'PUT',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(prepararReceta(params.data as Record<string, unknown>)),
    },
  );
  if (typeof response !== 'object' || response === null || !('id' in response)) {
    throw new Error('La API devolvió una receta actualizada no válida');
  }
  return {
    data: normalizarReceta(response as Record<string, unknown>) as unknown as RecordType,
  };
}


function noSoportado(operacion: string, resource: string): Promise<never> {
  return Promise.reject(new Error(`${operacion} no está disponible para ${resource} en esta versión de solo lectura`));
}

async function eliminar<RecordType extends RaRecord = RaRecord>(resource: string, params: DeleteParams<RecordType>): Promise<DeleteResult<RecordType>> {
  comprobarRecurso(resource);
  if (!RECURSOS_TAXONOMIA.includes(resource)) return noSoportado('delete', resource);
  await solicitarJson(`/api/admin/${resource}/${encodeURIComponent(String(params.id))}`, { method: 'DELETE' });
  return { data: (params.previousData ?? { id: params.id }) as RecordType };
}

export const dataProvider: DataProvider = {
  getList,
  getOne,
  getMany: (resource) => noSoportado('getMany', resource),
  getManyReference: (resource) => noSoportado('getManyReference', resource),
  create,
  update,
  updateMany: (resource) => noSoportado('updateMany', resource),
  delete: eliminar,
  deleteMany: (resource) => noSoportado('deleteMany', resource),
};
