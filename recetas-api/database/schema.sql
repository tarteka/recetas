PRAGMA foreign_keys = ON;

-- Recetas principales.
CREATE TABLE IF NOT EXISTS recetas (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    titulo TEXT NOT NULL,
    descripcion TEXT,

    fuente_url TEXT UNIQUE,
    fuente_nombre TEXT,
    imagen_url TEXT,

    raciones INTEGER,
    tiempo_preparacion_min INTEGER,
    tiempo_coccion_min INTEGER,
    tiempo_total_min INTEGER,

    creado_en TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    actualizado_en TEXT,
    archivada_en TEXT
);

-- Catálogo de ingredientes.
CREATE TABLE IF NOT EXISTS ingredientes (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    nombre TEXT NOT NULL UNIQUE
);

-- Ingredientes utilizados por cada receta.
CREATE TABLE IF NOT EXISTS receta_ingredientes (
    id INTEGER PRIMARY KEY AUTOINCREMENT,

    receta_id INTEGER NOT NULL,
    ingrediente_id INTEGER,

    cantidad REAL,
    unidad TEXT,
    notas TEXT,
    texto_original TEXT NOT NULL,
    posicion INTEGER NOT NULL,

    FOREIGN KEY (receta_id)
        REFERENCES recetas(id)
        ON DELETE CASCADE,

    FOREIGN KEY (ingrediente_id)
        REFERENCES ingredientes(id)
        ON DELETE SET NULL,

    UNIQUE (receta_id, posicion)
);

-- Pasos ordenados de elaboración.
CREATE TABLE IF NOT EXISTS receta_pasos (
    id INTEGER PRIMARY KEY AUTOINCREMENT,

    receta_id INTEGER NOT NULL,
    numero INTEGER NOT NULL,
    instruccion TEXT NOT NULL,
    imagen_url TEXT,

    FOREIGN KEY (receta_id)
        REFERENCES recetas(id)
        ON DELETE CASCADE,

    UNIQUE (receta_id, numero)
);

-- Categorías generales: postres, entrantes, platos principales, etc.
CREATE TABLE IF NOT EXISTS categorias (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    nombre TEXT NOT NULL UNIQUE,
    slug TEXT UNIQUE
);

-- Relación N:M entre recetas y categorías.
CREATE TABLE IF NOT EXISTS receta_categorias (
    receta_id INTEGER NOT NULL,
    categoria_id INTEGER NOT NULL,

    PRIMARY KEY (receta_id, categoria_id),

    FOREIGN KEY (receta_id)
        REFERENCES recetas(id)
        ON DELETE CASCADE,

    FOREIGN KEY (categoria_id)
        REFERENCES categorias(id)
        ON DELETE CASCADE
);

-- Etiquetas flexibles: vegetariano, rápido, horno, italiana, etc.
CREATE TABLE IF NOT EXISTS etiquetas (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    nombre TEXT NOT NULL UNIQUE,
    slug TEXT UNIQUE
);

-- Relación N:M entre recetas y etiquetas.
CREATE TABLE IF NOT EXISTS receta_etiquetas (
    receta_id INTEGER NOT NULL,
    etiqueta_id INTEGER NOT NULL,

    PRIMARY KEY (receta_id, etiqueta_id),

    FOREIGN KEY (receta_id)
        REFERENCES recetas(id)
        ON DELETE CASCADE,

    FOREIGN KEY (etiqueta_id)
        REFERENCES etiquetas(id)
        ON DELETE CASCADE
);

-- Índices para las consultas más habituales.
CREATE INDEX IF NOT EXISTS idx_receta_ingredientes_receta
    ON receta_ingredientes(receta_id);

CREATE INDEX IF NOT EXISTS idx_receta_ingredientes_ingrediente
    ON receta_ingredientes(ingrediente_id);

CREATE INDEX IF NOT EXISTS idx_receta_pasos_receta
    ON receta_pasos(receta_id);

CREATE INDEX IF NOT EXISTS idx_receta_categorias_categoria
    ON receta_categorias(categoria_id);

CREATE INDEX IF NOT EXISTS idx_receta_etiquetas_etiqueta
    ON receta_etiquetas(etiqueta_id);

CREATE INDEX IF NOT EXISTS idx_recetas_archivada_en
    ON recetas(archivada_en);

-- Sesiones propias del panel administrativo. El identificador recibido en la
-- cookie nunca se persiste en texto plano.
CREATE TABLE IF NOT EXISTS admin_sessions (
    id_hash TEXT PRIMARY KEY,
    google_subject TEXT NOT NULL,
    email TEXT NOT NULL,
    nombre TEXT,
    avatar_url TEXT,
    created_at INTEGER NOT NULL,
    expires_at INTEGER NOT NULL,
    last_seen_at INTEGER NOT NULL
);

CREATE INDEX IF NOT EXISTS idx_admin_sessions_expires_at
    ON admin_sessions(expires_at);

-- Estados OIDC de un solo uso para impedir login CSRF y callbacks repetidos.
CREATE TABLE IF NOT EXISTS admin_auth_states (
    state_hash TEXT PRIMARY KEY,
    expires_at INTEGER NOT NULL
);

CREATE INDEX IF NOT EXISTS idx_admin_auth_states_expires_at
    ON admin_auth_states(expires_at);
