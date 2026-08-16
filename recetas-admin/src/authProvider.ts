import { HttpError } from 'react-admin';
import type { AuthProvider, UserIdentity } from 'react-admin';

interface AdminMe {
  id: string;
  email: string;
  nombre: string | null;
  avatar_url: string | null;
}

function isAdminMe(value: unknown): value is AdminMe {
  if (typeof value !== 'object' || value === null) return false;
  const identity = value as Record<string, unknown>;
  return typeof identity.id === 'string'
    && typeof identity.email === 'string'
    && (typeof identity.nombre === 'string' || identity.nombre === null)
    && (typeof identity.avatar_url === 'string' || identity.avatar_url === null);
}

async function obtenerIdentidad(): Promise<AdminMe> {
  const response = await fetch('/api/admin/me', {
    credentials: 'include',
    headers: { Accept: 'application/json' },
  });
  if (!response.ok) {
    throw new HttpError('Sesión administrativa no válida', response.status);
  }
  const data: unknown = await response.json();
  if (!isAdminMe(data)) {
    throw new Error('La API devolvió una identidad administrativa no válida');
  }
  return data;
}

function statusFromError(error: unknown): number | undefined {
  if (typeof error !== 'object' || error === null || !('status' in error)) return undefined;
  return typeof error.status === 'number' ? error.status : undefined;
}

function nombreVisible(identity: AdminMe): string {
  const nombre = identity.nombre?.trim().split(/\s+/)[0];
  return nombre || identity.email.split('@')[0];
}

export const authProvider: AuthProvider = {
  login: () => {
    window.location.assign('/api/admin/auth/google');
    return Promise.resolve();
  },
  logout: async () => {
    await fetch('/api/admin/logout', {
      method: 'POST',
      credentials: 'include',
      headers: { Accept: 'application/json' },
    });
  },
  checkAuth: async () => {
    await obtenerIdentidad();
  },
  checkError: (error: unknown) => {
    const status = statusFromError(error);
    return status === 401 || status === 403 ? Promise.reject() : Promise.resolve();
  },
  getIdentity: async (): Promise<UserIdentity> => {
    const identity = await obtenerIdentidad();
    return {
      id: identity.id,
      fullName: nombreVisible(identity),
      avatar: identity.avatar_url ?? undefined,
    };
  },
  getPermissions: () => Promise.resolve(undefined),
};
