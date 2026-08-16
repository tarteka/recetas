import { lazy, Suspense } from 'react';
import { Admin, Resource } from 'react-admin';
import CategoryOutlinedIcon from '@mui/icons-material/CategoryOutlined';
import LocalOfferOutlinedIcon from '@mui/icons-material/LocalOfferOutlined';
import { AdminLogin } from './AdminLogin';
import { authProvider } from './authProvider';
import { dataProvider } from './dataProvider';
import { temaRecetario } from './theme';
import { i18nProvider } from './i18nProvider';
import { ErrorAdmin, PaginaNoEncontrada } from './PaginasEstado';

const RecetaList = lazy(() => import('./recetas/RecetaList').then((modulo) => ({ default: modulo.RecetaList })));
const RecetaCreate = lazy(() => import('./recetas/RecetaCreate').then((modulo) => ({ default: modulo.RecetaCreate })));
const RecetaEdit = lazy(() => import('./recetas/RecetaEdit').then((modulo) => ({ default: modulo.RecetaEdit })));
const TaxonomiaList = lazy(() => import('./taxonomias/TaxonomiaAdmin').then((modulo) => ({ default: modulo.TaxonomiaList })));
const TaxonomiaCreate = lazy(() => import('./taxonomias/TaxonomiaAdmin').then((modulo) => ({ default: modulo.TaxonomiaCreate })));
const TaxonomiaEdit = lazy(() => import('./taxonomias/TaxonomiaAdmin').then((modulo) => ({ default: modulo.TaxonomiaEdit })));

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
        i18nProvider={i18nProvider}
        loginPage={AdminLogin}
        catchAll={PaginaNoEncontrada}
        error={ErrorAdmin}
        requireAuth
        title="Mi Recetario"
        theme={temaRecetario}
      >
        <Resource name="recetas" list={RecetaList} edit={RecetaEdit} create={RecetaCreate} options={{ label: 'Recetas' }} />
        <Resource name="categorias" icon={CategoryOutlinedIcon} list={TaxonomiaList} edit={TaxonomiaEdit} create={TaxonomiaCreate} options={{ label: 'Categorías' }} />
        <Resource name="etiquetas" icon={LocalOfferOutlinedIcon} list={TaxonomiaList} edit={TaxonomiaEdit} create={TaxonomiaCreate} options={{ label: 'Etiquetas' }} />
      </Admin>
    </Suspense>
  );
}
