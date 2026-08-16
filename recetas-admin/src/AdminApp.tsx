import { lazy, Suspense } from 'react';
import { Admin, Resource } from 'react-admin';
import { AdminLogin } from './AdminLogin';
import { authProvider } from './authProvider';
import { dataProvider } from './dataProvider';
import { temaRecetario } from './theme';

const RecetaList = lazy(() => import('./recetas/RecetaList').then((modulo) => ({ default: modulo.RecetaList })));
const RecetaCreate = lazy(() => import('./recetas/RecetaCreate').then((modulo) => ({ default: modulo.RecetaCreate })));
const RecetaEdit = lazy(() => import('./recetas/RecetaEdit').then((modulo) => ({ default: modulo.RecetaEdit })));

function PantallaCarga() {
  return (
    <div className="carga-admin" role="status" aria-live="polite">
      <span className="carga-admin__indicador" aria-hidden="true" />
      <p>Cargando administración…</p>
    </div>
  );
}

export function AdminApp() {
  return (
    <Suspense fallback={<PantallaCarga />}>
      <Admin
        authProvider={authProvider}
        dataProvider={dataProvider}
        loginPage={AdminLogin}
        requireAuth
        title="Mi Recetario"
        theme={temaRecetario}
      >
        <Resource name="recetas" list={RecetaList} edit={RecetaEdit} create={RecetaCreate} options={{ label: 'Recetas' }} />
      </Admin>
    </Suspense>
  );
}
