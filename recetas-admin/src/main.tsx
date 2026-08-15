import { StrictMode } from 'react';
import { createRoot } from 'react-dom/client';
import { Admin, Resource } from 'react-admin';
import { BrowserRouter } from 'react-router-dom';
import { AdminLogin } from './AdminLogin';
import { authProvider } from './authProvider';
import { dataProvider } from './dataProvider';
import { RecetaEdit } from './recetas/RecetaEdit';
import { RecetaList } from './recetas/RecetaList';
import { temaRecetario } from './theme';
import './styles.css';

createRoot(document.getElementById('root')!).render(
  <StrictMode>
    <BrowserRouter basename="/admin">
      <Admin
        authProvider={authProvider}
        dataProvider={dataProvider}
        loginPage={AdminLogin}
        requireAuth
        title="Mi Recetario"
        theme={temaRecetario}
      >
        <Resource name="recetas" list={RecetaList} edit={RecetaEdit} options={{ label: 'Recetas' }} />
      </Admin>
    </BrowserRouter>
  </StrictMode>,
);
