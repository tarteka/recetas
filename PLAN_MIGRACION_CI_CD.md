# PLAN ESTRATÉGICO: MIGRACIÓN A CI/CD + GHCR

## 1. ARQUITECTURA ACTUAL ENCONTRADA

### 1.1 Repositorio
- **URL**: https://github.com/tarteka/recetas
- **Rama principal**: main
- **Flujo actual**: Desarrollo directo en VPS mediante SSH

### 1.2 Estructura del Proyecto
```
recetas/
├── recetas-api/           # PHP 8.4 + Slim Framework
│   ├── Dockerfile         # Desarrollo (php:8.4-cli)
│   ├── Dockerfile.prod    # Producción (php:8.4-apache)
│   ├── composer.json
│   ├── composer.lock
│   ├── src/
│   ├── database/
│   │   └── schema.sql
│   ├── public/
│   ├── docker/
│   │   ├── entrypoint.prod.sh
│   │   ├── apache-vhost.conf
│   │   ├── apache-security.conf
│   │   └── php-production.ini
│   └── vendor/ (en .gitignore)
│
├── recetas-web/           # React + TypeScript + Vite
│   ├── Dockerfile         # Desarrollo (node:24-alpine)
│   ├── Dockerfile.prod    # Producción (2 stages)
│   ├── package.json
│   ├── package-lock.json
│   ├── Caddyfile
│   ├── src/
│   ├── dist/ (en .gitignore)
│   └── node_modules/ (en .gitignore)
│
├── recetas-admin/         # React-admin + TypeScript + Vite
│   ├── Dockerfile         # Desarrollo (node:24-alpine)
│   ├── Dockerfile.prod    # Producción (2 stages)
│   ├── package.json
│   ├── package-lock.json
│   ├── Caddyfile
│   ├── src/
│   ├── dist/ (en .gitignore)
│   └── node_modules/ (en .gitignore)
│
├── compose.yaml           # Desarrollo local (con host mounts)
├── compose.prod.yaml      # Producción actual (con builds locales)
├── .env                   # Configuración (NO en git)
├── .gitignore
├── README.md
└── .github/               # ⚠️ NO EXISTE - CREAR
```

### 1.3 Estado Actual de Dockerfiles

#### Desarrollo (compose.yaml)
- **recetas-api**: `php:8.4-cli` con dev server
- **recetas-web**: `node:24-alpine` con Vite dev server
- **recetas-admin**: `node:24-alpine` con Vite dev server
- Montan volúmenes de código fuente
- Instalan dependencias dentro del contenedor
- Usan volúmenes nombrados para `node_modules` y `vendor`

#### Producción (compose.prod.yaml)
```yaml
# PROBLEMA: Aún ejecuta builds locales
recetas-api:
  build:
    context: ./recetas-api
    dockerfile: Dockerfile.prod   # ← Compila en VPS
  
recetas-web:
  build:
    context: ./recetas-web
    dockerfile: Dockerfile.prod   # ← Compila en VPS
```

**Estado**: Utiliza `Dockerfile.prod` pero **aún no usa imágenes pre-construidas**.

### 1.4 Dockerfiles de Producción Actuales

#### recetas-api/Dockerfile.prod
- Base: `php:8.4-apache`
- Multi-stage: No (stage único)
- Composer: Instala en el build con `--no-dev`
- Entrypoint: `recetas-entrypoint-prod` (inicializa BD)
- Servidor: Apache2-foreground
- **Estado**: ✅ Listo para GHCR (no depende de Vite)

#### recetas-web/Dockerfile.prod
- Multi-stage: ✅ Sí (build + caddy)
  - Stage 1: `node:24-alpine` compila React
  - Stage 2: `caddy:2-alpine` sirve `/dist`
- Servidor: Caddy 2
- **Estado**: ✅ Listo para GHCR (no incluye dev server)

#### recetas-admin/Dockerfile.prod
- Multi-stage: ✅ Sí (build + caddy)
  - Stage 1: `node:24-alpine` compila React
  - Stage 2: `caddy:2-alpine` sirve `/dist`
- **Estado**: ✅ Listo para GHCR (no incluye dev server)

### 1.5 Composición de Producción

**compose.prod.yaml**:
- Espera variables de entorno en `.env`
- Crea volúmenes para Caddy (data, config)
- Monta `./datos:/datos` para persistencia
- Define networking interno `recetas-interna`
- Health checks en API

### 1.6 Base de Datos y Persistencia

**Ubicación en VPS**: `/opt/recetas/datos/`
- `recetas.sqlite` ← Base de datos SQLite
- `imagenes/` ← Imágenes procesadas

**Inicialización**: Ejecutada por entrypoint en cada start
- Script: `recetas-api/docker/entrypoint.prod.sh`
- Crea tabla si no existe
- Mantiene migraciones

### 1.7 Configuración y Secretos

**En VPS (.env)**:
```
RECETAS_API_TOKEN=...          # Token API (producción)
GOOGLE_CLIENT_ID=...           # Google OIDC (producción)
GOOGLE_CLIENT_SECRET=...       # Google OIDC (producción)
ADMIN_ALLOWED_EMAILS=...
ADMIN_SESSION_SECRET=...
ADMIN_GOOGLE_REDIRECT_URI=https://recetas.proyectozero.org/api/admin/auth/google/callback
ADMIN_ALLOWED_ORIGINS=https://recetas.proyectozero.org
RECETAS_DOMINIO=recetas.proyectozero.org
```

**En Git**: Ninguno (correctamente ignorado en `.gitignore`)

### 1.8 Integración OpenClaw

**Estado**: Ejecuta en VPS con acceso a:
- Puerto 8080 (API interna)
- Token `RECETAS_API_TOKEN` para crear recetas
- Scripts locales: `guardar-receta.sh`, `guardar-imagen.sh`
- Generación de imágenes con IA

**Riesgo**: Necesita acceso al contenedor API en port 8080

---

## 2. CAMBIOS REALIZADOS EN ESTA SESIÓN

### 2.1 En Desarrollo Local (Windows)

✅ **Completado**:
- Crear `.env` local con credenciales de desarrollo
- Instalar `npm install` en `recetas-web` y `recetas-admin` 
- Modificar `compose.yaml` para usar volúmenes nombrados de `node_modules`
- Agregar volumen nombrado de `vendor` para `recetas-api`
- Crear 3 recetas de prueba
- Configurar Google OIDC para desarrollo en `localhost:5174`

✅ **Estado actual**: Proyecto funcionando localmente en Windows con Docker

---

## 3. CAMBIOS NECESARIOS POR FASE

### FASE 1: PREPARAR DESARROLLO LOCAL ✅ COMPLETO

**Objetivo**: Clonar + Docker Compose funcional en Windows

**Completado**:
- ✅ Compose local funciona
- ✅ Credenciales de desarrollo separadas
- ✅ Volúmenes nombrados para dependencias
- ✅ Google OIDC en localhost

**Pendiente**: 
- ⏳ Documentar procedimiento de primera clonación
- ⏳ Actualizar README para desarrollo local

---

### FASE 2: PREPARAR DOCKER PRODUCTION-READY

**Objetivo**: Dockerfiles listos para GHCR (sin builds locales)

**Estado actual de Dockerfiles.prod**: ✅ YA EXISTEN Y SON VÁLIDOS

**Cambios necesarios**:
1. Validar que no dependen de secretos en el build
2. Agregar `LABEL` de metadatos (commit SHA, versión)
3. Optimizar layers si es necesario
4. Crear `.dockerignore` adecuados (ya existen)

**Archivos a modificar**:
- `recetas-api/Dockerfile.prod` (agregar labels, optimizar)
- `recetas-web/Dockerfile.prod` (validar multi-stage)
- `recetas-admin/Dockerfile.prod` (validar multi-stage)

---

### FASE 3: CI - VALIDACIÓN EN PULL REQUESTS

**Objetivo**: GitHub Actions valida código antes del merge

**Archivos a crear**:
- `.github/workflows/ci.yml`

**Flujo CI**:
```
PR opened / push a rama
  ↓
Backend (recetas-api):
  - composer validate
  - sintaxis PHP
  ↓
Frontend (recetas-web, recetas-admin):
  - npm ci
  - npm run lint
  - npm run build
  ↓
Docker:
  - docker build (sin push)
  ↓
Resultado: ✓ PASS o ✗ FAIL (sin desplegar)
```

---

### FASE 4: GHCR - REGISTRY DE IMÁGENES

**Objetivo**: Publicar imágenes construidas en GHCR

**Archivos a crear**:
- `.github/workflows/build-publish.yml`

**Flujo CD Build**:
```
push a main + CI passed
  ↓
Docker Buildx (multi-arquitectura opcional)
  ↓
Tag: ghcr.io/tarteka/recetas-api:${COMMIT_SHA}
      ghcr.io/tarteka/recetas-api:latest
  ↓
Push GHCR
  ↓
Resultado: Imagen lista en GHCR
```

**Imágenes a publicar**:
- `ghcr.io/tarteka/recetas-api:${COMMIT_SHA}`
- `ghcr.io/tarteka/recetas-web:${COMMIT_SHA}`
- `ghcr.io/tarteka/recetas-admin:${COMMIT_SHA}`

Plus opcionales:
- `:latest` (apunta a main)
- `:v1.2.3` (tags de versión semántica si usas releases)

---

### FASE 5: CD AL VPS

**Objetivo**: Desplegar en VPS desde GitHub Actions

**Archivos a crear**:
- `.github/workflows/deploy.yml`

**Cambios en VPS**:
- Crear `~/.ssh/keys/github-deploy` (clave SSH privada)
- Configurar `authorized_keys` en VPS
- Script de despliegue: `/opt/recetas/scripts/deploy.sh`
- Actualizar `compose.prod.yaml` para usar imágenes GHCR

**Flujo CD Deploy**:
```
Imágenes en GHCR (FASE 4 completada)
  ↓
GitHub Actions se conecta al VPS vía SSH
  ↓
VPS ejecuta:
  RELEASE_TAG=<commit_sha> docker compose pull
  RELEASE_TAG=<commit_sha> docker compose up -d
  ↓
Health checks
  ↓
Resultado: ✓ Desplegado en VPS
```

---

### FASE 6: VALIDACIÓN DEL PRIMER DESPLIEGUE

**Objetivo**: Asegurar que todo funciona end-to-end

**Checklist**:
- Imágenes en GHCR funcionan
- VPS puede descargarlas (autenticación)
- Datos persistentes intactos
- API responde en `/salud`
- Admin accesible
- recetas-web cargable
- OpenClaw sigue funcionando

---

### FASE 7: DOCUMENTACIÓN FINAL

**Objetivo**: Procedimientos operativos

**Documentos a crear/actualizar**:
- README.md (desarrollo + despliegue)
- DEPLOYMENT.md (procedimiento detallado)
- ROLLBACK.md (cómo volver a versión anterior)
- .github/CONTRIBUTING.md (guía para contribuidores)

---

## 4. ARCHIVOS A CREAR

### 4.1 Workflows GitHub Actions
```
.github/workflows/
├── ci.yml                  # Validación en PR
├── build-publish.yml       # Build + push GHCR (main)
└── deploy.yml              # Deploy a VPS (main)
```

### 4.2 Scripts del VPS
```
scripts/
├── deploy.sh               # Script de despliegue
└── rollback.sh             # Script de rollback
```

### 4.3 Archivos de configuración
```
docker-compose.override.yaml   # (Opcional) Override local dev
```

### 4.4 Documentación
```
DEPLOYMENT.md
ROLLBACK.md
DEVELOPMENT.md
```

---

## 5. ARCHIVOS A MODIFICAR

### 5.1 Dockerfiles (FASE 2)
- `recetas-api/Dockerfile.prod` - Agregar labels
- `recetas-web/Dockerfile.prod` - Agregar labels
- `recetas-admin/Dockerfile.prod` - Agregar labels

### 5.2 Compose (FASE 4)
- `compose.prod.yaml` - Reemplazar `build:` con `image:`

**De**:
```yaml
recetas-api:
  build:
    context: ./recetas-api
    dockerfile: Dockerfile.prod
```

**A**:
```yaml
recetas-api:
  image: ghcr.io/tarteka/recetas-api:${RELEASE_TAG}
```

### 5.3 .gitignore (Sin cambios necesarios)
- Ya ignora correctamente datos, node_modules, vendor, .env

### 5.4 README.md (FASE 7)
- Agregar sección de desarrollo
- Agregar sección de CI/CD
- Aclarar separación local vs producción

---

## 6. WORKFLOWS GITHUB ACTIONS

### 6.1 CI Workflow (ci.yml)
```yaml
name: CI

on:
  pull_request:
  push:
    branches: [ main ]

jobs:
  backend:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      - uses: php-actions/composer@v6
        with:
          args: validate
      - name: Check PHP syntax
        run: find recetas-api -name "*.php" -exec php -l {} \;
  
  frontend-web:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      - uses: actions/setup-node@v4
        with:
          node-version: 24
      - run: cd recetas-web && npm ci && npm run lint && npm run build
  
  frontend-admin:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      - uses: actions/setup-node@v4
        with:
          node-version: 24
      - run: cd recetas-admin && npm ci && npm run lint && npm run build
  
  docker:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      - uses: docker/setup-buildx-action@v3
      - name: Build images
        run: |
          docker buildx build --file recetas-api/Dockerfile.prod recetas-api
          docker buildx build --file recetas-web/Dockerfile.prod recetas-web
          docker buildx build --file recetas-admin/Dockerfile.prod recetas-admin
```

### 6.2 Build & Publish Workflow (build-publish.yml)
```yaml
name: Build & Publish

on:
  push:
    branches: [ main ]

jobs:
  build-publish:
    runs-on: ubuntu-latest
    permissions:
      contents: read
      packages: write
    
    steps:
      - uses: actions/checkout@v4
      - uses: docker/setup-buildx-action@v3
      - uses: docker/login-action@v3
        with:
          registry: ghcr.io
          username: ${{ github.actor }}
          password: ${{ secrets.GITHUB_TOKEN }}
      
      - name: Build and push recetas-api
        uses: docker/build-push-action@v5
        with:
          context: ./recetas-api
          file: ./recetas-api/Dockerfile.prod
          push: true
          tags: |
            ghcr.io/${{ github.repository }}/recetas-api:${{ github.sha }}
            ghcr.io/${{ github.repository }}/recetas-api:latest
          labels: |
            org.opencontainers.image.source=${{ github.server_url }}/${{ github.repository }}
            org.opencontainers.image.revision=${{ github.sha }}
      
      # Similar para recetas-web y recetas-admin...
```

### 6.3 Deploy Workflow (deploy.yml)
```yaml
name: Deploy to Production

on:
  push:
    branches: [ main ]

jobs:
  deploy:
    runs-on: ubuntu-latest
    needs: build-publish
    
    steps:
      - uses: actions/checkout@v4
      
      - name: Deploy to VPS
        uses: appleboy/ssh-action@master
        with:
          host: ${{ secrets.VPS_HOST }}
          username: ${{ secrets.VPS_USER }}
          key: ${{ secrets.VPS_SSH_KEY }}
          script: |
            cd /opt/recetas
            RELEASE_TAG=${{ github.sha }} docker compose -f compose.prod.yaml pull
            RELEASE_TAG=${{ github.sha }} docker compose -f compose.prod.yaml up -d
            
            # Health check
            sleep 5
            curl -f http://127.0.0.1:8080/salud || exit 1
```

---

## 7. IMÁGENES GHCR PREVISTAS

Al completar todas las fases, tendrás en GHCR:

```
ghcr.io/tarteka/recetas-api
├── v1.0.0          (opcional, si usas semantic versioning)
├── a1b2c3d...      (commit SHA actual)
├── 9f8e7d6c...     (commit SHA anterior)
└── latest          (apunta al último de main)

ghcr.io/tarteka/recetas-web
├── a1b2c3d...
├── 9f8e7d6c...
└── latest

ghcr.io/tarteka/recetas-admin
├── a1b2c3d...
├── 9f8e7d6c...
└── latest
```

Ventaja: Puedes hacer rollback simplemente usando un SHA anterior.

---

## 8. SECRETS DE GITHUB

Necesitarás configurar en **Settings → Secrets and variables → Actions**:

### 8.1 Secretos de Despliegue
```
VPS_HOST              # IP o dominio del VPS
VPS_USER              # Usuario SSH (ej: deploy)
VPS_SSH_KEY           # Clave privada SSH (copiar contenido de ~/.ssh/id_rsa)
```

### 8.2 Acceso a GHCR
```
(Usará automáticamente GITHUB_TOKEN si el repositorio es público/privado)
```

**Nota**: No almacenes secretos de aplicación en GitHub Actions. Permanecen en el VPS (.env).

---

## 9. VARIABLES EN EL VPS

### 9.1 Permanecen SOLO en VPS (/opt/recetas/.env)
```
RECETAS_API_TOKEN=...
GOOGLE_CLIENT_ID=...
GOOGLE_CLIENT_SECRET=...
ADMIN_ALLOWED_EMAILS=...
ADMIN_SESSION_SECRET=...
ADMIN_GOOGLE_REDIRECT_URI=https://recetas.proyectozero.org/api/admin/auth/google/callback
ADMIN_ALLOWED_ORIGINS=https://recetas.proyectozero.org
RECETAS_DOMINIO=recetas.proyectozero.org
```

### 9.2 Variable de Despliegue (se pasa en despliegue)
```
RELEASE_TAG=<commit_sha>   # ← Se establece en deploy.yml
```

---

## 10. CONFIGURACIÓN GOOGLE OAUTH

### 10.1 Desarrollo Local (Windows)
```
Redirect URI: http://localhost:5174/api/admin/auth/google/callback
JavaScript Origin: http://localhost:5174
```

**En .env local**:
```
GOOGLE_CLIENT_ID=<dev_credentials>
GOOGLE_CLIENT_SECRET=<dev_credentials>
ADMIN_GOOGLE_REDIRECT_URI=http://localhost:5174/api/admin/auth/google/callback
ADMIN_ALLOWED_ORIGINS=http://localhost:5174
```

### 10.2 Producción (VPS)
```
Redirect URI: https://recetas.proyectozero.org/api/admin/auth/google/callback
JavaScript Origin: https://recetas.proyectozero.org
```

**En .env VPS** (NO en git):
```
GOOGLE_CLIENT_ID=<prod_credentials>
GOOGLE_CLIENT_SECRET=<prod_credentials>
ADMIN_GOOGLE_REDIRECT_URI=https://recetas.proyectozero.org/api/admin/auth/google/callback
ADMIN_ALLOWED_ORIGINS=https://recetas.proyectozero.org
```

---

## 11. PROCEDIMIENTO DEL PRIMER DESPLIEGUE

### 11.1 Pre-requisitos
1. ✅ Imágenes construidas en GHCR (FASE 4)
2. ✅ VPS accesible por SSH
3. ✅ Secrets configurados en GitHub
4. ✅ VPS tiene acceso a GHCR (clave de despliegue)
5. ✅ `/opt/recetas` contiene `compose.prod.yaml` y `.env`

### 11.2 Pasos Manuales Previos

En el VPS:
```bash
# 1. Crear clave de despliegue para GHCR (si no existe)
cat ~/.ssh/id_rsa.pub | xclip
# Copiar a https://github.com/settings/keys (Deploy keys)

# 2. Configurar docker login para GHCR
echo $GITHUB_TOKEN | docker login ghcr.io -u <usuario> --password-stdin

# 3. Verificar que datos actuales están respaldados
ls -la /opt/recetas/datos/
```

### 11.3 Primer Despliegue (Manual en el VPS)

```bash
cd /opt/recetas

# 1. Descargar imágenes
RELEASE_TAG=<commit_sha_actual> docker compose -f compose.prod.yaml pull

# 2. Levantar servicios
RELEASE_TAG=<commit_sha_actual> docker compose -f compose.prod.yaml up -d

# 3. Esperar a que los servicios estén listos
sleep 10

# 4. Verificar salud
curl -f http://127.0.0.1:8080/salud

# 5. Revisar logs
docker compose -f compose.prod.yaml logs
```

### 11.4 Validación Post-Despliegue

- ✓ API responde en `/salud`
- ✓ recetas-web accesible en producción
- ✓ Admin accesible
- ✓ Datos persistentes intactos (sqlite, imágenes)
- ✓ OpenClaw puede contactar API en puerto 8080

---

## 12. PROCEDIMIENTO DE ROLLBACK

### 12.1 Rollback Manual (si fue necesario)

```bash
cd /opt/recetas

# 1. Obtener SHA anterior
git log --oneline | head -5
# ó revisar tags recientes en GHCR

# 2. Cambiar RELEASE_TAG
RELEASE_TAG=<commit_sha_anterior> docker compose -f compose.prod.yaml pull
RELEASE_TAG=<commit_sha_anterior> docker compose -f compose.prod.yaml up -d

# 3. Verificar
sleep 10
curl -f http://127.0.0.1:8080/salud
```

### 12.2 Rollback Automático (con GitHub Actions)

Crear workflow `rollback.yml`:
```yaml
name: Rollback to Previous Version

on:
  workflow_dispatch:
    inputs:
      commit_sha:
        description: 'Commit SHA to rollback to'
        required: true

jobs:
  rollback:
    runs-on: ubuntu-latest
    steps:
      - uses: appleboy/ssh-action@master
        with:
          host: ${{ secrets.VPS_HOST }}
          username: ${{ secrets.VPS_USER }}
          key: ${{ secrets.VPS_SSH_KEY }}
          script: |
            cd /opt/recetas
            RELEASE_TAG=${{ github.event.inputs.commit_sha }} \
              docker compose -f compose.prod.yaml pull
            RELEASE_TAG=${{ github.event.inputs.commit_sha }} \
              docker compose -f compose.prod.yaml up -d
```

Uso:
- Ir a GitHub → Actions → Rollback
- Introducir SHA anterior
- Ejecutar

---

## 13. PASOS MANUALES QUE DEBE REALIZAR

### 13.1 Antes de Fase 3 (CI)
- [ ] Revisar plan y confirmar diseño
- [ ] No hay pasos previos, solo configuración en Git

### 13.2 Antes de Fase 4 (GHCR)
- [ ] Crear token de GitHub para GHCR (si repositorio privado)
  - Settings → Developer settings → Personal access tokens
  - Scope: `write:packages`, `read:packages`
- [ ] Probar build local de imágenes
  ```bash
  docker buildx build -f recetas-api/Dockerfile.prod recetas-api
  ```

### 13.3 Antes de Fase 5 (Deploy)
- [ ] Crear par de claves SSH para despliegue
  ```bash
  ssh-keygen -t ed25519 -f ~/.ssh/github-deploy
  ```
- [ ] Copiar clave pública al VPS
  ```bash
  cat ~/.ssh/github-deploy.pub | ssh user@vps "cat >> ~/.ssh/authorized_keys"
  ```
- [ ] Configurar secrets en GitHub (VPS_HOST, VPS_USER, VPS_SSH_KEY)
- [ ] Configurar docker login en VPS
  ```bash
  echo $GITHUB_TOKEN | docker login ghcr.io -u username --password-stdin
  ```
- [ ] Crear `/opt/recetas/compose.prod.yaml` (versión GHCR)

### 13.4 Verificaciones Previas
- [ ] Clonar repo en Windows, ejecutar `docker compose up`
- [ ] Probar que CI funciona en rama feature
- [ ] Revisar que imágenes se publican en GHCR
- [ ] Simular despliegue local con imágenes GHCR

---

## 14. RIESGOS DETECTADOS

### 14.1 Riesgos Críticos

| Riesgo | Impacto | Mitigación |
|--------|---------|-----------|
| **Pérdida de datos SQLite** | Catastrófico | No usar `docker compose down -v`. Backups en VPS antes de despliegue |
| **OpenClaw pierde acceso a API** | Alto | Puerto 8080 debe permanecer en `127.0.0.1:8080`. Validar en health check |
| **Credenciales en imágenes Docker** | Crítico | No hacer COPY de .env. Usar entrypoints para inyectar via variables |
| **GHCR no accesible desde VPS** | Alto | Configurar docker login, crear deploy key en GitHub |

### 14.2 Riesgos Medios

| Riesgo | Impacto | Mitigación |
|--------|---------|-----------|
| **Google OIDC mismatch** | Medio | Registrar URIs correctas en Google Cloud Console |
| **GitHub Actions timeout** | Medio | Establecer timeouts apropiados, monitorear logs |
| **Imágenes muy grandes** | Medio | Usar `.dockerignore`, limpiar caches de build |
| **SSH key expuesta** | Medio | Usar formato ed25519, short-lived si es posible |

### 14.3 Riesgos Bajos

| Riesgo | Impacto | Mitigación |
|--------|---------|-----------|
| **Desincronización .env local vs VPS** | Bajo | Documentar claramente en README |
| **Falsos positivos en CI** | Bajo | Escribir tests mínimos, validar locally first |
| **Compose.prod.yaml obsoleto en checkout** | Bajo | Borrar checkout local en VPS después de validación |

---

## 15. RESUMEN EJECUTIVO

### Flujo Propuesto
```
Developer (Windows)
    ↓
git push rama feature
    ↓
GitHub → CI Workflow
    ├─ composer validate
    ├─ npm lint/build
    ├─ docker build
    └─ RESULTADO: ✓ o ✗
    ↓
Pull Request & Code Review
    ↓
git merge a main
    ↓
GitHub → Build & Publish Workflow
    ├─ docker buildx build
    ├─ push ghcr.io/tarteka/recetas-*
    └─ RESULTADO: Imágenes en GHCR
    ↓
GitHub → Deploy Workflow
    ├─ ssh VPS
    ├─ docker compose pull
    ├─ docker compose up -d
    ├─ health checks
    └─ RESULTADO: ✓ Desplegado
    ↓
Production VPS
    ├─ recetas-api en Apache
    ├─ recetas-web + recetas-admin en Caddy
    ├─ datos persistentes
    └─ OpenClaw accesible
```

### Beneficios
- ✅ Desarrollo separado de producción
- ✅ Imágenes reproducibles (SHA-tagged)
- ✅ Rollback sencillo
- ✅ Auditoría completa en GitHub
- ✅ Datos persistentes seguros
- ✅ Sin secretos en imágenes
- ✅ CI/CD totalmente automatizado

### Fase Crítica
**FASE 5 (CD al VPS)** es la más delicada:
- Requiere acceso SSH seguro
- Interactúa con producción en vivo
- Importante validar con datos de prueba primero

---

**Documento de análisis completado el 2026-08-16**
**Próximo paso**: Revisar este plan y confirmar para proceder a implementación
