# Recetario

Recetario web personal con importación automática de recetas mediante OpenClaw.

Permite enviar a OpenClaw la URL de una receta —por ejemplo desde Telegram— para que extraiga y estructure su contenido mediante IA. La receta se almacena automáticamente y queda disponible en la aplicación web.

## Stack

**Frontend**

- React
- TypeScript
- Vite

**Backend**

- PHP
- Slim
- SQLite

**Infraestructura**

- Docker
- Docker Compose
- OpenClaw

## Funcionamiento

```text
Telegram
   │
   ▼
OpenClaw
   │
   │ Extrae y estructura la receta
   ▼
Recetas API
   │
   ▼
SQLite
   ▲
   │
React
```

OpenClaw se encarga de interpretar el contenido de la página y convertirlo al formato esperado por la API.

La API permanece desacoplada del proceso de extracción: recibe datos estructurados, los valida y los persiste.

## Puesta en marcha

### Requisitos

- Docker
- Docker Compose

Clona el repositorio:

```bash
git clone <URL_DEL_REPOSITORIO>
cd recetas
```

Crea la configuración local:

```bash
cp .env.example .env
```

Genera un token para las operaciones de escritura:

```bash
openssl rand -hex 32
```

Configúralo en `.env`:

```env
RECETAS_API_TOKEN=tu_token
```

Levanta los servicios:

```bash
docker compose up -d --build
```

Comprueba el estado:

```bash
docker compose ps
```

## API

| Método | Endpoint | Descripción | Autenticación |
|---|---|---|---|
| `GET` | `/salud` | Estado de la API | No |
| `GET` | `/recetas` | Listado de recetas | No |
| `GET` | `/recetas/{id}` | Detalle de una receta | No |
| `POST` | `/recetas` | Crear una receta | Bearer Token |

Las recetas almacenan información estructurada sobre ingredientes, cantidades, unidades, elaboración, tiempos, categorías y etiquetas.

## Integración con OpenClaw

La instalación y configuración general de OpenClaw y sus canales de comunicación no forman parte de este proyecto.

El repositorio incluye el skill necesario para importar recetas:

```text
openclaw/recetario
```

### 1. Configurar el token

OpenClaw debe disponer del mismo `RECETAS_API_TOKEN` utilizado por la API.

Añádelo a:

```text
~/.openclaw/.env
```

```env
RECETAS_API_TOKEN=tu_token
```

Protege el archivo:

```bash
chmod 600 ~/.openclaw/.env
```

### 2. Instalar el skill

Desde la raíz del repositorio:

```bash
cp -r openclaw/recetario ~/.openclaw/skills/
chmod 700 ~/.openclaw/skills/recetario/scripts/guardar-receta.sh
```

Reinicia OpenClaw:

```bash
openclaw gateway restart
```

Comprueba que el skill está disponible:

```bash
openclaw skills list
```

Debe aparecer `recetario` entre los skills disponibles.

### 3. Uso

Envía al agente una URL de una receta:

```text
Guarda esta receta:
https://ejemplo.com/receta
```

OpenClaw obtiene la página, extrae la receta, normaliza sus datos y ejecuta el script incluido en el skill.

El script envía el resultado a la API utilizando el token configurado en `RECETAS_API_TOKEN`.

## Comunicación con OpenClaw

OpenClaw se ejecuta en el host mientras que la API se ejecuta dentro de Docker.

La API publica su puerto únicamente sobre loopback:

```yaml
ports:
  - "127.0.0.1:8080:8080"
```

Por tanto, OpenClaw puede acceder mediante:

```text
http://127.0.0.1:8080
```

sin exponer directamente la API a Internet.

## Seguridad

- Las operaciones de escritura requieren Bearer Token.
- Los secretos se gestionan mediante variables de entorno.
- `.env` no se versiona.
- La base de datos SQLite no se versiona.
- La API no expone directamente su puerto a Internet.

## Estado

El proyecto permite actualmente:

- importar recetas desde una URL mediante OpenClaw;
- almacenar ingredientes, cantidades y unidades;
- almacenar instrucciones ordenadas;
- clasificar recetas mediante categorías y etiquetas;
- listar las recetas;
- consultar el detalle completo desde la aplicación web.

## Licencia

Proyecto personal.