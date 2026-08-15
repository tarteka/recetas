# Recetario

Aplicación web personal para almacenar y consultar recetas de cocina.

El proyecto permite guardar recetas manualmente mediante su API o automatizar la importación desde una URL utilizando OpenClaw. Las recetas se almacenan de forma estructurada junto con sus ingredientes, pasos, categorías, etiquetas e imágenes.

## Tecnologías

### Backend

- PHP 8.4
- Slim Framework
- SQLite
- GD
- Composer

### Frontend

- React
- TypeScript
- Vite

### Infraestructura

- Docker
- Docker Compose

### Automatización

- OpenClaw
- OpenAI Image Generation

## Características

- Listado de recetas.
- Vista detallada de cada receta.
- Ingredientes estructurados.
- Pasos de elaboración ordenados.
- Categorías y etiquetas.
- Tiempos de preparación y cocción.
- API REST.
- Autenticación mediante Bearer Token para operaciones de escritura.
- Importación automática de recetas desde una URL mediante OpenClaw.
- Extracción y normalización automática de recetas.
- Descarga y validación de la imagen original.
- Generación automática de una imagen cuando la original no existe o no es adecuada.
- Normalización de imágenes a WebP 1200 × 800.
- Almacenamiento local y persistente de imágenes.

## Instalación

Clona el repositorio:

```bash
git clone <URL_DEL_REPOSITORIO>
cd recetas
```

Crea el archivo de configuración:

```bash
cp .env.example .env
```

Configura las variables necesarias en `.env`.

Por ejemplo:

```dotenv
RECETAS_API_TOKEN=change-me
```

Genera un token seguro:

```bash
openssl rand -hex 32
```

Construye e inicia los contenedores:

```bash
docker compose up -d --build
```

La aplicación utiliza volúmenes persistentes para almacenar la base de datos y las imágenes.

## API

### Estado

```http
GET /salud
```

### Listar recetas

```http
GET /recetas?pagina=1&por_pagina=9&buscar=arroz&categoria=postres&etiqueta=sin-horno
```

La respuesta contiene `datos` con las recetas de la página y un objeto `paginacion` con `pagina`, `por_pagina`, `total` y `total_paginas`. Todos los parámetros salvo la página y el tamaño son opcionales.

### Listar categorías

```http
GET /categorias
```

Devuelve las categorías con su número total de recetas.

### Listar etiquetas

```http
GET /etiquetas
```

Devuelve las etiquetas con su número total de recetas.

### Obtener una receta

```http
GET /recetas/{id}
```

### Crear una receta

```http
POST /recetas
Authorization: Bearer <token>
Content-Type: application/json
```

### Asignar una imagen

```http
POST /recetas/{id}/imagen
Authorization: Bearer <token>
Content-Type: application/octet-stream
```

El cuerpo de la petición contiene directamente los bytes de la imagen.

La API valida la imagen y la normaliza automáticamente a:

- WebP
- 1200 × 800 píxeles
- relación 3:2
- recorte tipo `cover`

La imagen resultante se almacena en el almacenamiento persistente del servidor y la receta guarda únicamente su ruta local.

## Integración con OpenClaw

El repositorio incluye el skill:

```text
openclaw/recetario/
```

OpenClaw debe estar previamente instalado y configurado. La instalación de OpenClaw y la configuración de canales como Telegram quedan fuera del alcance de este proyecto.

### Instalar el skill

Copia el skill al directorio de skills de OpenClaw:

```bash
mkdir -p ~/.openclaw/skills/recetario

cp -R openclaw/recetario/. \
  ~/.openclaw/skills/recetario/
```

Asegura los permisos de los scripts:

```bash
chmod 700 ~/.openclaw/skills/recetario/scripts/*.sh
```

Comprueba que OpenClaw lo reconoce:

```bash
openclaw skills check
```

`recetario` debe aparecer como disponible y visible para el modelo.

### Token de la API

OpenClaw necesita disponer de:

```text
RECETAS_API_TOKEN
```

El valor debe coincidir con el configurado para la API.

No guardes el token directamente en `SKILL.md` ni en los scripts versionados.

## Importación automática

Una vez instalado el skill, se puede solicitar a OpenClaw que guarde una receta indicando simplemente su URL.

Por ejemplo:

```text
Guarda esta receta:
https://ejemplo.com/receta
```

OpenClaw:

1. obtiene la página;
2. identifica la receta;
3. extrae y normaliza sus datos;
4. guarda la receta mediante la API;
5. obtiene su identificador;
6. localiza y analiza la imagen original;
7. utiliza la imagen original si es adecuada;
8. genera una imagen específica si la original no existe o no representa correctamente la receta;
9. envía la imagen a la API;
10. confirma el resultado al usuario.

## Gestión de imágenes

Las imágenes no se almacenan utilizando las URLs de terceros.

El proceso es:

```text
Imagen original
      │
      ├── válida ───────────────┐
      │                         │
      └── no válida             │
             │                  │
             ▼                  │
      generación mediante IA    │
             │                  │
             └─────────┬────────┘
                       ▼
                    API
                       ▼
             normalización WebP
                       ▼
                  1200 × 800
                       ▼
              almacenamiento local
```

Esto evita depender permanentemente de servidores externos y mantiene un formato uniforme para todas las imágenes del recetario.

La generación mediante IA solo se utiliza como alternativa cuando no existe una imagen original adecuada.

## Arquitectura de la API

La API utiliza una arquitectura por capas adaptada a una API JSON:

- `public/index.php` es únicamente el punto de entrada HTTP.
- `bootstrap/app.php` crea Slim y compone repositorios, servicios, controladores y middleware.
- `routes/api.php` declara las rutas y las conecta con sus controladores.
- `src/Controller/` gestiona las peticiones y respuestas HTTP.
- `src/Service/` contiene operaciones de aplicación como el procesamiento de imágenes.
- `src/Repository/` concentra el acceso a SQLite.

La API no necesita una capa de vistas HTML; sus representaciones se entregan como JSON desde los controladores.

## Persistencia

Los datos de ejecución se almacenan fuera de las imágenes Docker.

Entre ellos:

```text
datos/recetas.sqlite
datos/imagenes/
```

Estos archivos no deben incluirse en Git.

## Seguridad

Las operaciones de escritura de la API están protegidas mediante Bearer Token.

No deben versionarse:

- `.env`
- tokens de API
- base de datos SQLite
- imágenes almacenadas
- dependencias instaladas
- archivos temporales

Antes de realizar un commit se recomienda comprobar:

```bash
git status --short
```

y:

```bash
git check-ignore -v \
  .env \
  datos/recetas.sqlite \
  datos/imagenes/
```

## Desarrollo

Para consultar los logs:

```bash
docker compose logs -f
```

Para reconstruir los servicios:

```bash
docker compose up -d --build
```

Para detenerlos:

```bash
docker compose down
```

Los datos persistentes no se eliminan al recrear los contenedores.