import { useMemo } from 'react';
import { authProvider } from './authProvider';

const errores: Record<string, string> = {
  access_denied: 'Esta cuenta de Google no tiene acceso al recetario.',
  authentication_failed: 'No se pudo completar el acceso con Google.',
  invalid_state: 'La solicitud de acceso ha caducado. Inténtalo de nuevo.',
};

export function AdminLogin() {
  const error = useMemo(() => {
    const code = new URLSearchParams(window.location.search).get('error');
    return code ? errores[code] ?? 'No se pudo iniciar sesión.' : null;
  }, []);

  return (
    <main className="login-admin">
      <section className="login-admin__tarjeta" aria-labelledby="titulo-login">
        <p className="login-admin__ceja">Mi Recetario</p>
        <h1 id="titulo-login">Administración</h1>
        <p>Accede con una cuenta de Google autorizada para gestionar el recetario.</p>
        {error && <p className="login-admin__error" role="alert">{error}</p>}
        <button type="button" onClick={() => { void authProvider.login?.({}); }}>
          Entrar con Google
        </button>
      </section>
    </main>
  );
}
