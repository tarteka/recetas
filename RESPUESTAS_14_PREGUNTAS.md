# RESPUESTAS A TUS 14 PREGUNTAS

## 1. Arquitectura Actual Encontrada

**Componentes**:
- **recetas-api**: PHP 8.4 + Slim Framework + SQLite
- **recetas-web**: React + TypeScript + Vite (frontend público)
- **recetas-admin**: React-admin + Google OIDC (panel administrativo)
- **Persistencia**: `/opt/recetas/datos/` en VPS (sqlite + imágenes)
- **Orquestación**: Docker Compose (desarrollo y producción)
- **Servidor web**: Caddy 2 (producción)

**Dockerfiles Actuales**:
- ✅ Dockerfile (desarrollo en cada servicio)
- ✅ Dockerfile.prod (producción en cada servicio)
- ✅ Multi-stage en recetas-web y recetas-admin
- ✅ Health checks en compose.prod.yaml

**Repositorio**: GitHub (github.com/tarteka/recetas)
**CI/CD**: NO EXISTE - A CREAR

---

## 2. Cambios Realizados en Esta Sesión

**Completado**:
- ✅ Instalación local en Windows con Docker Compose
- ✅ Volúmenes nombrados para node_modules y vendor
- ✅ Credenciales de desarrollo separadas
- ✅ Google OIDC configurado para localhost:5174
- ✅ Creación de 3 recetas de prueba
- ✅ Validación de funcionamiento local

**NO se ha tocado**:
- Repositorio GitHub (sin commits, sin pushes)
- VPS / producción (datos intactos)
- Dockerfiles (se analizaron, no se modificaron)
- Workflows (no existen aún)

---

## 3. Archivos a Crear

### 3.1 Workflows GitHub (3 archivos)
```
.github/workflows/
├── ci.yml                  # Validación en PRs
├── build-publish.yml       # Build + push GHCR en main
└── deploy.yml              # Deploy automático a VPS en main
```

### 3.2 Scripts VPS (2 archivos - opcionales pero recomendados)
```
scripts/
├── deploy.sh               # Alternativa manual a deploy.yml
└── rollback.sh             # Rollback manual
```

### 3.3 Configuración Compose (modificación existente)
```
compose.prod.yaml (versión GHCR - reemplazar build: con image:)
```

### 3.4 Documentación (4 archivos)
```
DEPLOYMENT.md              # Procedimiento de despliegue
ROLLBACK.md                # Procedimiento de rollback
DEVELOPMENT.md             # Guía de desarrollo local
.github/CONTRIBUTING.md    # Para contribuidores (opcional)
```

---

## 4. Archivos a Modificar

### 4.1 Dockerfiles.prod (3 archivos - cambios mínimos)

**recetas-api/Dockerfile.prod**:
- Agregar LABELs de metadatos (commit SHA)
- No cambios críticos (ya es válido)

**recetas-web/Dockerfile.prod**:
- Agregar LABELs de metadatos
- Multi-stage ✅ Ya existe

**recetas-admin/Dockerfile.prod**:
- Agregar LABELs de metadatos
- Multi-stage ✅ Ya existe

**Label a agregar** (en cada Dockerfile.prod):
```dockerfile
LABEL org.opencontainers.image.source="https://github.com/tarteka/recetas"
LABEL org.opencontainers.image.revision="${BUILD_COMMIT_SHA}"
```

### 4.2 compose.prod.yaml (cambio importante en FASE 4)

**De (actual)**:
```yaml
recetas-api:
  build:
    context: ./recetas-api
    dockerfile: Dockerfile.prod
```

**A (con GHCR)**:
```yaml
recetas-api:
  image: ghcr.io/tarteka/recetas-api:${RELEASE_TAG}
  pull_policy: always  # Asegurar descarga fresca
```

Similar para recetas-web y recetas-admin.

### 4.3 .gitignore (sin cambios)
- ✅ Ya ignora correctamente datos/, *.sqlite, node_modules/, vendor/, .env

### 4.4 README.md (actualizar FASE 7)
- Agregar sección de desarrollo local
- Aclarar CI/CD flow
- Explicar separación local vs producción

---

## 5. Workflows GitHub a Crear

### 5.1 CI Workflow (en cada PR y push a main)
```yaml
# Estructura básica en ci.yml
Backend (recetas-api):
  - composer validate
  - Validar sintaxis PHP
  
Frontend (recetas-web):
  - npm ci
  - npm run lint
  - npm run build
  
Frontend (recetas-admin):
  - npm ci
  - npm run lint
  - npm run build
  
Docker:
  - docker buildx build (sin push)
  
Resultado: ✓ PASS o ✗ FAIL (sin desplegar)
```

### 5.2 Build & Publish (solo en push a main + CI pasado)
```yaml
# Estructura básica en build-publish.yml
1. Conectarse a GHCR (ghcr.io)
2. docker buildx build -push para cada servicio
3. Tags: ${COMMIT_SHA} y latest
4. Resultado: Imágenes en GHCR
```

### 5.3 Deploy (solo en push a main + imágenes en GHCR)
```yaml
# Estructura básica en deploy.yml
1. Conectarse al VPS vía SSH
2. cd /opt/recetas
3. RELEASE_TAG=${COMMIT_SHA} docker compose pull
4. RELEASE_TAG=${COMMIT_SHA} docker compose up -d
5. Health check (curl /salud)
6. Resultado: ✓ Desplegado o ✗ Rollback automático
```

---

## 6. Imágenes GHCR Previstas

Después de implementar CI/CD, publicarás:

```
ghcr.io/tarteka/recetas-api
├── a1b2c3d4 (commit SHA actual)
├── 9f8e7d6c (commit SHA anterior)
├── 8e7d6c5b (commit SHA 2 cambios atrás)
└── latest (apunta al último en main)

ghcr.io/tarteka/recetas-web
├── a1b2c3d4
├── 9f8e7d6c
├── 8e7d6c5b
└── latest

ghcr.io/tarteka/recetas-admin
├── a1b2c3d4
├── 9f8e7d6c
├── 8e7d6c5b
└── latest
```

**Ventaja**: Puedes hacer rollback a cualquier SHA simplemente cambiando `RELEASE_TAG`.

---

## 7. Secrets de GitHub a Configurar

En **GitHub Settings → Secrets and variables → Actions**:

```
VPS_HOST           # IP o dominio del VPS (ej: 192.168.1.100)
VPS_USER           # Usuario SSH (ej: deploy)
VPS_SSH_KEY        # Clave privada SSH (contenido de ~/.ssh/id_rsa o similar)
```

**GHCR Access**:
- Usa automáticamente `GITHUB_TOKEN` (no requiere configuración manual)
- Si el repo es privado, GitHub maneja la autenticación automáticamente

**NO almacenes aquí**:
- RECETAS_API_TOKEN
- GOOGLE_CLIENT_ID / SECRET
- Otros secretos de aplicación (van en .env del VPS)

---

## 8. Variables Exclusivamente en VPS

Estos secretos permanecen **SOLO en el VPS** (`/opt/recetas/.env`):

```
# Autenticación API
RECETAS_API_TOKEN=abc123def456...

# Google OIDC (producción)
GOOGLE_CLIENT_ID=123456789-abc...
GOOGLE_CLIENT_SECRET=GOCSPX-abc...

# Sesión administrativo
ADMIN_ALLOWED_EMAILS=semosa@gmail.com
ADMIN_SESSION_SECRET=random_32_chars_here

# Configuración producción
ADMIN_GOOGLE_REDIRECT_URI=https://recetas.proyectozero.org/api/admin/auth/google/callback
ADMIN_ALLOWED_ORIGINS=https://recetas.proyectozero.org
RECETAS_DOMINIO=recetas.proyectozero.org
```

**Nunca en Git, nunca en GitHub Actions, nunca en imágenes Docker.**

**Variable de despliegue** (se pasa dinámicamente):
```
RELEASE_TAG=a1b2c3d4   # ← Se establece en deploy.yml antes de docker compose
```

---

## 9. Configuración Necesaria para Desarrollo Local

### 9.1 En tu Windows (ya hecho parcialmente)

**Clonar repo**:
```bash
git clone https://github.com/tarteka/recetas.git
cd recetas
```

**Crear .env local** (copiar de .env.example y adaptar):
```env
RECETAS_DOMINIO=localhost:5173
RECETAS_API_TOKEN=dev-token-local

# Google OIDC para desarrollo (credenciales DEV, no producción)
GOOGLE_CLIENT_ID=<dev_client_id>
GOOGLE_CLIENT_SECRET=<dev_client_secret>

# Session
ADMIN_ALLOWED_EMAILS=tu_email@gmail.com
ADMIN_SESSION_SECRET=random_32_chars_dev

# Desarrollo local
ADMIN_GOOGLE_REDIRECT_URI=http://localhost:5174/api/admin/auth/google/callback
ADMIN_ALLOWED_ORIGINS=http://localhost:5174
```

**Levantar proyecto**:
```bash
docker compose up -d
```

**URLs locales**:
- http://localhost:5173 (recetas-web)
- http://localhost:5174 (recetas-admin + Caddy)
- http://localhost:8080 (recetas-api)

### 9.2 Datos Locales

Se crean automáticamente:
- `./datos/recetas.sqlite` (base de datos de desarrollo)
- `./datos/imagenes/` (imágenes de prueba)

**Nunca usar datos de producción en local.**

---

## 10. Redirect URI Google para Desarrollo

### Para Google Cloud Console

**Agregar en Credenciales OAuth**:

```
Authorized JavaScript origins:
  - http://localhost:5174
  - https://recetas.proyectozero.org

Authorized redirect URIs:
  - http://localhost:5174/api/admin/auth/google/callback
  - https://recetas.proyectozero.org/api/admin/auth/google/callback
```

**Tienes dos opciones**:

**Opción A**: Crear credenciales DEV separadas en Google Cloud
- Una credencial para localhost (desarrollo)
- Una credencial para recetas.proyectozero.org (producción)
- Usar diferentes Client ID/Secret en .env local vs VPS

**Opción B**: Usar mismas credenciales pero registrar ambos orígenes
- Un solo Client ID/Secret
- Registrar todos los orígenes permitidos
- Menos seguridad en desarrollo (más simple)

**Recomendación**: Opción A (credenciales separadas por entorno)

---

## 11. Procedimiento del Primer Despliegue

### 11.1 Pre-requisitos

- [ ] Imágenes construidas en GHCR (Workflow build-publish completado)
- [ ] VPS con Docker Compose actualizado
- [ ] SSH key configurada en GitHub Secrets
- [ ] `/opt/recetas` contiene compose.prod.yaml y .env
- [ ] Datos de producción respaldados
- [ ] OpenClaw validado en VPS

### 11.2 Pasos Manuales en VPS

```bash
# 1. Crear / actualizar .env (si no existe)
cat > /opt/recetas/.env << 'EOF'
RECETAS_API_TOKEN=...
GOOGLE_CLIENT_ID=...
GOOGLE_CLIENT_SECRET=...
ADMIN_ALLOWED_EMAILS=...
ADMIN_SESSION_SECRET=...
ADMIN_GOOGLE_REDIRECT_URI=https://recetas.proyectozero.org/api/admin/auth/google/callback
ADMIN_ALLOWED_ORIGINS=https://recetas.proyectozero.org
RECETAS_DOMINIO=recetas.proyectozero.org
EOF

# 2. Configurar docker login para GHCR
echo $GITHUB_TOKEN | docker login ghcr.io -u USERNAME --password-stdin

# 3. Obtener commit SHA actual
COMMIT_SHA=$(git rev-parse HEAD)
echo "Desplegando: $COMMIT_SHA"

# 4. Descargar imágenes
RELEASE_TAG=$COMMIT_SHA docker compose -f compose.prod.yaml pull

# 5. Levantar servicios
RELEASE_TAG=$COMMIT_SHA docker compose -f compose.prod.yaml up -d

# 6. Esperar inicialización
sleep 15

# 7. Verificar salud
curl -f http://127.0.0.1:8080/salud || echo "FAIL: API no responde"

# 8. Revisar logs
docker compose -f compose.prod.yaml logs --tail=50
```

### 11.3 Validación Post-Despliegue

Checklist:
- [ ] `curl http://127.0.0.1:8080/salud` devuelve `{"estado":"ok"}`
- [ ] recetas-web accesible en `https://recetas.proyectozero.org`
- [ ] Admin panel accesible en `https://recetas.proyectozero.org/admin/`
- [ ] SQLite intacto: `ls -la /opt/recetas/datos/`
- [ ] Imágenes persistidas: `ls /opt/recetas/datos/imagenes/`
- [ ] OpenClaw puede contactar API en puerto 8080
- [ ] Certificado SSL válido (Caddy)

### 11.4 Si Algo Falla

```bash
# Ver logs detallados
docker compose -f compose.prod.yaml logs -f

# Detener servicios (preserva datos)
docker compose -f compose.prod.yaml down

# Volver a versión anterior (ver sección 12)
```

---

## 12. Procedimiento de Rollback

### 12.1 Rollback Manual (desde VPS)

```bash
cd /opt/recetas

# 1. Obtener SHAs anteriores
git log --oneline | head -10
# o revisar tags en GHCR

# 2. Seleccionar SHA anterior (ej: 9f8e7d6c)
PREVIOUS_SHA=9f8e7d6c

# 3. Descargar imagen anterior
RELEASE_TAG=$PREVIOUS_SHA docker compose -f compose.prod.yaml pull

# 4. Iniciar con versión anterior
RELEASE_TAG=$PREVIOUS_SHA docker compose -f compose.prod.yaml up -d

# 5. Verificar
sleep 10
curl -f http://127.0.0.1:8080/salud

echo "Rollback completado a $PREVIOUS_SHA"
```

**Tiempo total**: < 2 minutos
**Datos afectados**: Ninguno (persisten en ./datos/)

### 12.2 Rollback Automático con GitHub Actions

Crear workflow adicional `.github/workflows/rollback.yml`:

```yaml
name: Manual Rollback

on:
  workflow_dispatch:
    inputs:
      commit_sha:
        description: 'Commit SHA to rollback to'
        required: true
        type: string

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
            sleep 10
            curl -f http://127.0.0.1:8080/salud || exit 1
```

**Uso**:
1. Ir a GitHub → Actions → Manual Rollback
2. Click "Run workflow"
3. Introducir SHA anterior
4. Ejecutar

---

## 13. Pasos Manuales que Debes Realizar

### 13.1 Antes de Fase 3 (CI)
- [ ] Revisar este plan y aprobar estructura
- [ ] Confirmar que Dockerfiles.prod son correctos

### 13.2 Antes de Fase 4 (GHCR)
- [ ] Probar build local de imágenes
  ```bash
  docker buildx build -f recetas-api/Dockerfile.prod recetas-api
  ```
- [ ] Crear token personal de GitHub (si repo privado)
  - GitHub → Settings → Developer settings → Personal access tokens
  - Scope: `write:packages`, `read:packages`

### 13.3 Antes de Fase 5 (Deploy al VPS) ⚠️ CRÍTICO
- [ ] **Crear par de claves SSH para despliegue**
  ```bash
  ssh-keygen -t ed25519 -f ~/.ssh/github-deploy -N ""
  ```
- [ ] **Copiar clave pública al VPS**
  ```bash
  cat ~/.ssh/github-deploy.pub | ssh user@vps "mkdir -p ~/.ssh && cat >> ~/.ssh/authorized_keys"
  chmod 600 ~/.ssh/authorized_keys  # En el VPS
  ```
- [ ] **Configurar secrets en GitHub**
  - Settings → Secrets → New repository secret
  - VPS_HOST: IP del VPS
  - VPS_USER: usuario SSH (ej: deploy)
  - VPS_SSH_KEY: contenido de ~/.ssh/github-deploy (clave privada)

- [ ] **Configurar docker login en VPS**
  ```bash
  # En el VPS
  echo $GITHUB_TOKEN | docker login ghcr.io -u USERNAME --password-stdin
  ```

- [ ] **Crear compose.prod.yaml versión GHCR**
  - Modificar `build:` → `image: ghcr.io/tarteka/...`
  - Agregar `pull_policy: always`

### 13.4 Validación Previa
- [ ] Clonar repo en Windows limpio, ejecutar `docker compose up` → ✓
- [ ] Crear rama feature, hacer commit, empujar → ✓
- [ ] Revisar que CI ejecuta en PR → ✓
- [ ] Hacer merge a main → ✓
- [ ] Revisar que build-publish genera imágenes en GHCR → ✓
- [ ] Verificar que GHCR es accesible desde VPS → ✓
- [ ] Simular despliegue manual con imágenes GHCR → ✓
- [ ] Validar que datos persisten después del deploy → ✓

---

## 14. Riesgos Detectados

### 🔴 RIESGOS CRÍTICOS

| Riesgo | Descripción | Mitigación |
|--------|-------------|-----------|
| **Pérdida de SQLite** | `docker compose down -v` borra volúmenes | Prohibir `-v`. Documentar claramente. Backups automáticos |
| **Pérdida de imágenes** | Si datos/ se monta incorrecto | Validar mount en compose antes de ejecutar |
| **Credenciales en imagen** | Si .env se COPY en Dockerfile | Nunca COPY .env. Usar variables de entorno en entrypoint |
| **OpenClaw sin acceso** | Si puerto 8080 cambia | Health check en deploy valida http://127.0.0.1:8080/salud |

### 🟠 RIESGOS ALTOS

| Riesgo | Descripción | Mitigación |
|--------|-------------|-----------|
| **GHCR no accesible en VPS** | docker login falla | Script deploy valida `docker pull` antes de `up` |
| **SSH key comprometida** | Si .pem se expone | Usar ed25519, short-lived tokens, auditar GitHub logs |
| **Google OIDC mismatch** | URIs no registradas en Google Cloud | Documento claro de URIs requeridas. Probar en dev primero |
| **Timeout en health check** | Deploy marca FAIL aunque funciona | Aumentar timeouts, validar que API responde lentamente |

### 🟡 RIESGOS MEDIOS

| Riesgo | Descripción | Mitigación |
|--------|-------------|-----------|
| **Desincronización .env** | Desarrollo vs VPS divergen | Documentar en README. Template .env.example |
| **Imágenes muy grandes** | GHCR storage limitado | Usar `.dockerignore`. Limpiar caches de build |
| **CI timeout** | Tests muy lentos | Establecer timeouts realistas, caché de dependencias |
| **Rollback al SHA incorrecto** | Usuario especifica SHA que no existe en GHCR | Validar en GitHub antes de ejecutar, listar opciones |

### 🟢 RIESGOS BAJOS

| Riesgo | Descripción | Mitigación |
|--------|-------------|-----------|
| **Checkout local en VPS obsoleto** | Git repo viejo en /opt | Documentar que se puede borrar post-validación |
| **Falsos positivos en CI** | Tests flaky | Ejecutar localmente antes de PR |
| **Documentación desactualizada** | Guías no se actualizan | Revisar docs cada despliegue, versionar en README |

---

## SÍNTESIS FINAL

### ¿Qué Hemos Hecho?
✅ Análisis completo de arquitectura
✅ Plan detallado en 7 fases
✅ Identificación de archivos a crear/modificar
✅ Workflows GitHub Actions especificados
✅ Procedimientos de deploy y rollback documentados
✅ Riesgos identificados y mitigados

### ¿Qué Falta?
⏳ Implementación real de los workflows
⏳ Primer despliegue (validado manualmente)
⏳ Documentación final en README

### ¿Cuándo Comenzar?
Después de tu aprobación de este plan, podemos:
1. **Crear workflows** (FASE 3)
2. **Configurar GHCR** (FASE 4)
3. **Despliegue automático** (FASE 5)
4. **Validación end-to-end** (FASE 6)
5. **Documentación** (FASE 7)

### Próximo Paso
**CONFIRMA ESTE PLAN** y podemos comenzar la implementación.

