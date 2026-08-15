import { Navigate, Route, Routes } from 'react-router-dom';
import Footer from './components/Footer';
import PaginaDetalle from './pages/PaginaDetalle';
import PaginaListado from './pages/PaginaListado';

export default function App() {
  return <div className="aplicacion">
    <Routes>
      <Route path="/" element={<PaginaListado />} />
      <Route path="/recetas/:id" element={<PaginaDetalle />} />
      <Route path="*" element={<Navigate to="/" replace />} />
    </Routes>
    <Footer />
  </div>;
}
