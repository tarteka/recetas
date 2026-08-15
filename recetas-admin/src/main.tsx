import { StrictMode } from 'react';
import { createRoot } from 'react-dom/client';
import { Admin, Resource } from 'react-admin';
import { BrowserRouter } from 'react-router-dom';
import { dataProvider } from './dataProvider';
import { RecetaList } from './recetas/RecetaList';
import './styles.css';

createRoot(document.getElementById('root')!).render(
  <StrictMode>
    <BrowserRouter basename="/admin">
      <Admin dataProvider={dataProvider} title="Mi Recetario">
        <Resource name="recetas" list={RecetaList} options={{ label: 'Recetas' }} />
      </Admin>
    </BrowserRouter>
  </StrictMode>,
);
