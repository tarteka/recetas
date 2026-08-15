import { Link } from 'react-router-dom';

export default function Footer() {
  return <footer className="footer-principal">
    <div className="contenedor">
      <p>
        Un proyecto de <a href="https://proyectozero.org" target="_blank" rel="noreferrer">Proyecto Zero</a>
        <span className="footer-principal__separador" aria-hidden="true">|</span>
        <Link to="/acerca-de">Acerca de</Link>
      </p>
    </div>
  </footer>;
}
