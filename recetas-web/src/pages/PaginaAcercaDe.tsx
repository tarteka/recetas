import { Link } from 'react-router-dom';

export default function PaginaAcercaDe() {
  return <main className="pagina-acerca">
    <div className="contenedor">
      <nav className="acerca-navegacion" aria-label="Navegación secundaria">
        <Link className="volver" to="/">← Volver al recetario</Link>
      </nav>

      <header className="acerca-cabecera">
        <p className="ceja">Acerca del proyecto</p>
        <h1>Cocina, datos y automatización.</h1>
        <p className="acerca-cabecera__entradilla">
          Mi Recetario es una aplicación web full-stack para construir y consultar una colección personal de recetas estructuradas.
        </p>
      </header>

      <section className="acerca-bloque acerca-introduccion" aria-labelledby="titulo-proposito">
        <div>
          <p className="acerca-numero" aria-hidden="true">01</p>
          <h2 id="titulo-proposito">El propósito</h2>
        </div>
        <div className="acerca-texto">
          <p>
            El proyecto reúne recetas, ingredientes, pasos, tiempos, categorías, etiquetas e imágenes en un único recetario fácil de consultar desde cualquier dispositivo.
          </p>
          <p>
            No es únicamente una colección de enlaces: la información se extrae, normaliza y almacena de forma estructurada para conservarla y poder encontrarla después.
          </p>
        </div>
      </section>

      <section className="acerca-bloque" aria-labelledby="titulo-flujo">
        <div>
          <p className="acerca-numero" aria-hidden="true">02</p>
          <h2 id="titulo-flujo">Cómo funciona</h2>
        </div>
        <ol className="acerca-pasos">
          <li><strong>Importación</strong><span>OpenClaw interpreta una receta desde su fuente y extrae sus datos relevantes.</span></li>
          <li><strong>Persistencia</strong><span>Una API Slim valida la información y la almacena en SQLite.</span></li>
          <li><strong>Imágenes</strong><span>Las imágenes originales o generadas se validan y normalizan a WebP.</span></li>
          <li><strong>Consulta</strong><span>React presenta el recetario con búsqueda, filtros y navegación responsive.</span></li>
        </ol>
      </section>

      <section className="acerca-tecnologia" aria-labelledby="titulo-tecnologia">
        <div>
          <p className="acerca-numero" aria-hidden="true">03</p>
          <h2 id="titulo-tecnologia">Tecnología</h2>
          <p>Una arquitectura pequeña y comprensible, construida con herramientas abiertas y sin capas innecesarias.</p>
        </div>
        <ul aria-label="Tecnologías utilizadas">
          <li><span>Interfaz</span><strong>React + TypeScript</strong></li>
          <li><span>API</span><strong>Slim + PHP</strong></li>
          <li><span>Datos</span><strong>SQLite</strong></li>
          <li><span>Automatización</span><strong>OpenClaw</strong></li>
          <li><span>Entorno</span><strong>Docker</strong></li>
        </ul>
      </section>

      <aside className="acerca-cierre">
        <p>Un proyecto creado como parte de <a href="https://proyectozero.org" target="_blank" rel="noreferrer">Proyecto Zero ↗</a></p>
      </aside>
    </div>
  </main>;
}
