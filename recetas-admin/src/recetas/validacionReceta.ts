import { minValue, required } from 'react-admin';

type ValoresReceta = Record<string, unknown>;

export const validarTitulo = [
  required('El título es obligatorio'),
  (valor: unknown) => typeof valor === 'string' && valor.trim() !== ''
    ? undefined
    : 'El título es obligatorio',
];

export const validarRaciones = [
  minValue(1, 'Debe ser al menos 1'),
  (valor: unknown) => valor == null || valor === '' || Number.isInteger(Number(valor))
    ? undefined
    : 'Debe ser un número entero',
];

export const validarMinutos = [
  minValue(0, 'No puede ser negativo'),
  (valor: unknown) => valor == null || valor === '' || Number.isInteger(Number(valor))
    ? undefined
    : 'Indica minutos completos',
];

export const validarCantidad = (valor: unknown) => {
  if (valor == null || valor === '') return undefined;
  return Number.isFinite(Number(valor)) && Number(valor) > 0
    ? undefined
    : 'Debe ser mayor que 0';
};

export const validarLista = (mensaje: string) => (valor: unknown) => (
  Array.isArray(valor) && valor.length > 0 ? undefined : mensaje
);

export const validarUrlOpcional = (valor: unknown) => {
  if (valor == null || String(valor).trim() === '') return undefined;
  try {
    const url = new URL(String(valor));
    return ['http:', 'https:'].includes(url.protocol) ? undefined : 'Usa una URL http o https';
  } catch {
    return 'Introduce una URL válida';
  }
};

export function validarCoherenciaReceta(valores: ValoresReceta) {
  const errores: Record<string, string> = {};
  const total = Number(valores.tiempo_total_min);
  const preparacion = Number(valores.tiempo_preparacion_min);
  const coccion = Number(valores.tiempo_coccion_min);

  if (
    valores.tiempo_total_min != null
    && valores.tiempo_total_min !== ''
    && ((valores.tiempo_preparacion_min != null && valores.tiempo_preparacion_min !== '' && total < preparacion)
      || (valores.tiempo_coccion_min != null && valores.tiempo_coccion_min !== '' && total < coccion))
  ) {
    errores.tiempo_total_min = 'El tiempo total no puede ser menor que preparación o cocción';
  }

  return errores;
}
