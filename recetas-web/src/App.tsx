import { Navigate, Route, Routes } from 'react-router-dom';
import PaginaDetalle from './pages/PaginaDetalle';
import PaginaListado from './pages/PaginaListado';

export default function App() {
  return <Routes>
    <Route path="/" element={<PaginaListado />} />
    <Route path="/recetas/:id" element={<PaginaDetalle />} />
    <Route path="*" element={<Navigate to="/" replace />} />
  </Routes>;
}
