import { StrictMode } from 'react';
import { createRoot } from 'react-dom/client';
import { createBrowserRouter, RouterProvider } from 'react-router-dom';
import { AdminApp } from './AdminApp';
import './styles.css';

// react-admin's `warnWhenUnsavedChanges` y `useBlocker` requieren un data
// router (no un <BrowserRouter> clásico) para poder bloquear la navegación.
const router = createBrowserRouter(
  [{ path: '*', element: <AdminApp /> }],
  {
    basename: '/admin',
    future: {
      v7_fetcherPersist: false,
      v7_normalizeFormMethod: false,
      v7_partialHydration: false,
      v7_relativeSplatPath: false,
      v7_skipActionErrorRevalidation: false,
    },
  },
);

createRoot(document.getElementById('root')!).render(
  <StrictMode>
    <RouterProvider router={router} />
  </StrictMode>,
);
