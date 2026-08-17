# Desarrollo Local

Guía para desarrollar localmente en Windows con Docker Compose.

---

## Requisitos Previos

- **Windows 11**
- **Docker Desktop** (con WSL 2 o Hyper-V)
- **Git**
- **VS Code** (opcional)
- **Node.js 24** (opcional, para lint local)

---

## Configuración Inicial

### 1. Clonar Repositorio

```bash
git clone https://github.com/tarteka/recetas.git
cd recetas
```

### 2. Crear .env Local

```bash
# Copiar template
copy .env.example .env

# Editar .env y configurar para desarrollo local:
```

Contenido de `.env` para desarrollo:

```env
# Dominio local
RECETAS_DOMINIO=localhost:5173

# Token API (cualquier valor para desarrollo)
RECETAS_API_TOKEN=dev-token-local-123

# Google OIDC (Credenciales de DESARROLLO, no producción)
# Registrar en Google Cloud Console con:
#   - Origin: http://localhost:5174
#   - Callback: http://localhost:5174/api/admin/auth/google/callback
GOOGLE_CLIENT_ID=<dev_client_id>
GOOGLE_CLIENT_SECRET=<dev_client_secret>

# Admin (desarrollo local)
ADMIN_ALLOWED_EMAILS=tu_email@gmail.com
ADMIN_SESSION_SECRET=dev-secret-32-chars-min-local

ADMIN_GOOGLE_REDIRECT_URI=http://localhost:5174/api/admin/auth/google/callback
ADMIN_ALLOWED_ORIGINS=http://localhost:5174
```

**⚠️ Nunca commits .env a Git**

### 3. Levantar Proyecto

```bash
docker compose up -d
```

Esperar ~30 segundos a que se inicialice.

### 4. Verificar que Funciona

```bash
# API
curl http://localhost:8080/salud
# Respuesta: {"estado":"ok"}

# Web
curl http://localhost:5173
# Respuesta: código HTML

# Admin
curl http://localhost:5174
# Respuesta: código HTML
```

---

## URLs Locales

| Servicio | URL | Descripción |
|----------|-----|-------------|
| **Web pública** | http://localhost:5173 | Listado de recetas |
| **Admin** | http://localhost:5174 | Panel administrativo |
| **API** | http://localhost:8080 | REST API |

---

## Flujo de Desarrollo

### 1. Crear Rama Feature

```bash
git checkout -b feature/mi-cambio
```

### 2. Hacer Cambios

El código se refleja automáticamente en los contenedores:

- **recetas-api**: Cambios en `recetas-api/src/` se ven inmediatamente
- **recetas-web**: Cambios en `recetas-web/src/` se compilan con Vite
- **recetas-admin**: Cambios en `recetas-admin/src/` se compilan con Vite

### 3. Validar Localmente

```bash
# Lint
cd recetas-web && npm run lint
cd recetas-admin && npm run lint

# Build
cd recetas-web && npm run build
cd recetas-admin && npm run build

# Verificar API
curl http://localhost:8080/recetas
```

### 4. Commit y Push

```bash
git add .
git commit -m "feat: descripción del cambio"
git push origin feature/mi-cambio
```

### 5. Crear Pull Request

- Ir a GitHub
- Click "New Pull Request"
- Crear PR contra `main`
- GitHub Actions validará automáticamente

### 6. Merge a Main

Una vez aprobada la PR:
```bash
# En GitHub: Click "Merge"
# O local:
git checkout main
git merge feature/mi-cambio
git push
```

**GitHub Actions deployará automáticamente a producción.**

---

## Datos de Desarrollo

Se crean automáticamente en:

```
datos/
├── recetas.sqlite      # Base de datos (desarrollo)
└── imagenes/           # Imágenes de recetas
```

**Nunca uses datos de producción localmente.**

### Resetear Datos Locales

```bash
# Borrar base de datos local
rm datos/recetas.sqlite

# Limpiar imágenes
rm -rf datos/imagenes/*

# Reiniciar API
docker compose restart recetas-api
```

---

## Logs

Ver logs en tiempo real:

```bash
# Todos
docker compose logs -f

# Solo API
docker compose logs -f recetas-api

# Solo web
docker compose logs -f recetas-web

# Últimas 50 líneas
docker compose logs --tail=50
```

---

## Detener/Reiniciar

```bash
# Detener todo (preserva datos)
docker compose down

# Reiniciar
docker compose up -d

# Limpiar todo incluyendo volúmenes (⚠️ borra datos)
docker compose down -v
```

---

## Google OIDC en Desarrollo

### Crear Credenciales DEV en Google Cloud Console

1. Ir a https://console.cloud.google.com/
2. **APIs & Services → Credentials**
3. Click **+ Create Credentials → OAuth 2.0 Client ID**
4. Type: **Web application**
5. Authorized origins:
   ```
   http://localhost:5174
   http://localhost:4173
   ```
6. Authorized redirect URIs:
   ```
   http://localhost:5174/api/admin/auth/google/callback
   ```
7. Crear y copiar **Client ID** y **Client Secret**
8. Agregar a `.env`:
   ```env
   GOOGLE_CLIENT_ID=<copiar aquí>
   GOOGLE_CLIENT_SECRET=<copiar aquí>
   ```
9. Reiniciar contenedor:
   ```bash
   docker compose restart recetas-api recetas-admin
   ```

### Acceder al Admin

1. Ir a http://localhost:5174/admin
2. Click "Iniciar sesión con Google"
3. Usar cuenta Google local (la email de `ADMIN_ALLOWED_EMAILS`)

---

## Estructura del Proyecto

```
recetas/
├── recetas-api/
│   ├── src/               ← Editar aquí
│   ├── composer.json
│   ├── Dockerfile         (desarrollo)
│   ├── Dockerfile.prod    (producción)
│   └── database/
│
├── recetas-web/
│   ├── src/               ← Editar aquí
│   ├── package.json
│   ├── Dockerfile         (desarrollo)
│   ├── Dockerfile.prod    (producción)
│   └── Caddyfile
│
├── recetas-admin/
│   ├── src/               ← Editar aquí
│   ├── package.json
│   ├── Dockerfile         (desarrollo)
│   ├── Dockerfile.prod    (producción)
│   └── Caddyfile
│
├── compose.yaml           (desarrollo ← usar este)
├── compose.prod.yaml      (producción)
├── .env                   (ignorado, crear localmente)
├── .gitignore
├── README.md
├── DEVELOPMENT.md         (este archivo)
├── DEPLOYMENT.md          (para producción)
├── ROLLBACK.md            (si algo falla)
└── .github/
    └── workflows/
        ├── ci.yml                  (CI - validación)
        ├── build-publish.yml       (Build - GHCR)
        ├── deploy.yml              (Deploy - VPS)
        └── rollback.yml            (Rollback manual)
```

---

## Troubleshooting

### Puerto ya en uso

```bash
# Encontrar qué ocupa el puerto
netstat -ano | findstr :5173

# O con PowerShell
Get-NetTCPConnection -LocalPort 5173

# Cambiar puerto en compose.yaml o matar proceso
```

### Contenedor no inicia

```bash
docker compose logs recetas-api
# Ver el error específico

# Reconstruir
docker compose down
docker compose build --no-cache
docker compose up
```

### NPM no encuentra módulos

```bash
# Reinstalar
cd recetas-web
npm ci
npm run build
```

### API no responde

```bash
# Revisar logs
docker compose logs recetas-api

# Reiniciar
docker compose restart recetas-api

# Verificar puerto
curl http://127.0.0.1:8080/salud
```

### Datos corruptos

```bash
# Limpiar y recrear
rm datos/recetas.sqlite
docker compose restart recetas-api
sleep 5
curl http://localhost:8080/salud
```

---

## Comandos Útiles

```bash
# Ver estado de contenedores
docker compose ps

# Ver imágenes
docker compose images

# Ejecutar comando en contenedor
docker compose exec recetas-api php -v

# Acceder a shell de contenedor
docker compose exec recetas-api sh

# Ver volúmenes
docker volume ls

# Limpiar imágenes no usadas
docker image prune -a

# Actualizar dependencias
cd recetas-web && npm update

# Lint solo (sin build)
cd recetas-web && npm run lint
```

---

## CI/CD en Desarrollo

### Validar Localmente antes de Push

```bash
# Backend
cd recetas-api
composer validate
composer install
composer run stan   # PHPStan
composer run test   # PHPUnit
```

> **PHP nativo de Windows:** si instalas PHP directamente en Windows (por ejemplo
> con Scoop) para correr `composer run stan`/`composer run test` sin Docker,
> `phpstan.neon` fija `phpVersion: 80400` para que PHPStan analice siempre como
> PHP 8.4 (el de producción/CI), aunque tu PHP local sea otra versión. Para
> paridad total con CI (misma versión de PHP y extensiones), también puedes
> correr los tests dentro de un contenedor `php:8.4-cli` con `gd`, `pdo_sqlite`
> y `mbstring`.

```bash
# Frontend web
cd recetas-web
npm ci
npm run lint
npm run build

# Frontend admin
cd recetas-admin
npm ci
npm run lint
npm run build
```

### Ver Workflows en GitHub

Después de hacer push:

1. Ir a **GitHub → Actions**
2. Ver que CI valida automáticamente
3. Si CI ✓, el merge desencadena Build
4. Build ✓ desencadena Deploy a producción

---

## Separación Local vs Producción

| Aspecto | Local | Producción |
|---------|-------|------------|
| **Datos** | `datos/` en host | `/opt/recetas/datos/` en VPS |
| **Credenciales** | `.env` local (ignorado) | `.env` en VPS (secreto) |
| **Google OIDC** | Credenciales DEV | Credenciales PROD |
| **Dominio** | http://localhost | https://recetas.proyectozero.org |
| **Servidor** | dev Caddy | Apache + Caddy |
| **Imágenes** | Builds locales | GHCR (pre-construidas) |

**Nunca uses secretos de producción localmente.**

---

## Preguntas Frecuentes

### ¿Puedo editar la BD mientras funciona?

No recomendado. Si necesitas, acceder al contenedor:
```bash
docker compose exec recetas-api sqlite3 /datos/recetas.sqlite
```

### ¿Cómo deshago cambios?

```bash
git checkout .            # Deshacer cambios locales
git clean -fd             # Limpiar archivos no tracked
```

### ¿Cómo creo recetas de prueba?

```bash
curl -X POST http://localhost:8080/recetas \
  -H "Authorization: Bearer dev-token-local-123" \
  -H "Content-Type: application/json" \
  -d '{
    "titulo": "Mi receta",
    "ingredientes": [{"nombre": "Harina", "texto_original": "500g"}],
    "pasos": [{"instruccion": "Mezclar"}]
  }'
```

### ¿Puedo usar datos de producción localmente?

No. Crear datos de desarrollo locales. Si necesitas migrar datos, contactar al equipo.

---

## Siguiente Paso

1. ✅ Clonar repo
2. ✅ Crear `.env` local
3. ✅ `docker compose up`
4. ✅ Hacer cambios
5. ✅ `git push` rama feature
6. ✅ Crear PR
7. ✅ Merge → Deploy automático

¡Bienvenido al flujo CI/CD! 🚀
