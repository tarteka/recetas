# Despliegue a Producción

## Flujo Automático

El despliegue a producción es **completamente automático** tras un merge a `main`:

```
git push a main
    ↓
GitHub Actions: CI (validación)
    ↓
GitHub Actions: Build & Publish (GHCR)
    ↓
GitHub Actions: Deploy (VPS automático)
    ↓
Producción actualizada
```

**Tiempo total**: ~10-15 minutos

---

## Requisitos Previos (una sola vez)

### 1. GitHub Secrets Configurados

En **Settings → Secrets and variables → Actions**:

```
VPS_HOST      = IP o dominio del VPS (ej: 192.168.1.100)
VPS_USER      = Usuario SSH (ej: deploy)
VPS_SSH_KEY   = Clave privada SSH (contenido completo de ~/.ssh/id_rsa)
```

### 2. VPS Configurado

```bash
# 1. Crear usuario de despliegue (si no existe)
sudo useradd -m deploy
sudo usermod -aG docker deploy

# 2. Copiar clave pública
mkdir -p ~/.ssh
cat ~/.ssh/authorized_keys  # Debería contener clave pública de GitHub

# 3. Configurar docker login para GHCR
echo $GITHUB_TOKEN | docker login ghcr.io -u USERNAME --password-stdin

# 4. Crear .env en /opt/recetas/.env
# (contiene secretos de aplicación - ver sección Secretos)

# 5. Crear compose.prod.yaml
# (ya actualizado en el repo)
```

### 3. Google OIDC Configurado

En **Google Cloud Console → Credenciales**:

Registrar orígenes autorizados:
```
https://recetas.proyectozero.org
http://localhost:5174  (si desarrollas localmente)
```

Registrar redirect URIs:
```
https://recetas.proyectozero.org/api/admin/auth/google/callback
http://localhost:5174/api/admin/auth/google/callback  (desarrollo)
```

---

## Despliegue Manual en VPS

Si necesitas desplegar manualmente (sin GitHub Actions):

```bash
cd /opt/recetas

# 1. Definir el SHA a desplegar
RELEASE_TAG=a1b2c3d4  # Cambiar a SHA real

# 2. Descargar imágenes
RELEASE_TAG=$RELEASE_TAG docker compose -f compose.prod.yaml pull

# 3. Levantar servicios
RELEASE_TAG=$RELEASE_TAG docker compose -f compose.prod.yaml up -d

# 4. Esperar inicialización
sleep 15

# 5. Verificar
curl -f http://127.0.0.1:8080/salud

# Si todo OK:
echo "✅ Despliegue completado: $RELEASE_TAG"
```

---

## Verificación Post-Despliegue

Checklist de validación:

- [ ] API responde: `curl -f http://127.0.0.1:8080/salud`
- [ ] Web accesible: `https://recetas.proyectozero.org`
- [ ] Admin accesible: `https://recetas.proyectozero.org/admin`
- [ ] Datos persistentes intactos:
  ```bash
  ls -la /opt/recetas/datos/recetas.sqlite
  ls /opt/recetas/datos/imagenes/ | wc -l
  ```
- [ ] OpenClaw puede contactar API
- [ ] Certificado SSL válido (Caddy)

```bash
# Script de validación rápido
curl -f http://127.0.0.1:8080/salud && \
curl -f https://recetas.proyectozero.org/recetas?por_pagina=1 > /dev/null && \
echo "✅ Todo funciona correctamente"
```

---

## Logs y Debugging

Ver logs en tiempo real:

```bash
# Todos los servicios
docker compose -f compose.prod.yaml logs -f

# Solo API
docker compose -f compose.prod.yaml logs -f recetas-api

# Solo web
docker compose -f compose.prod.yaml logs -f recetas-web

# Últimas 50 líneas
docker compose -f compose.prod.yaml logs --tail=50
```

---

## Variables de Entorno en VPS

El archivo `/opt/recetas/.env` debe contener:

```env
# API Token para crear recetas
RECETAS_API_TOKEN=<token_seguro>

# Google OIDC (Producción)
GOOGLE_CLIENT_ID=<prod_client_id>
GOOGLE_CLIENT_SECRET=<prod_client_secret>

# Admin
ADMIN_ALLOWED_EMAILS=tu_email@gmail.com
ADMIN_SESSION_SECRET=<32_caracteres_aleatorios>

# URLs de producción
ADMIN_GOOGLE_REDIRECT_URI=https://recetas.proyectozero.org/api/admin/auth/google/callback
ADMIN_ALLOWED_ORIGINS=https://recetas.proyectozero.org
RECETAS_DOMINIO=recetas.proyectozero.org
```

**Nunca almacenes esto en Git.**

---

## Imágenes Docker

Las imágenes se publican automáticamente en GHCR después de cada merge a `main`:

```
ghcr.io/tarteka/recetas-api:<commit_sha>
ghcr.io/tarteka/recetas-web:<commit_sha>
ghcr.io/tarteka/recetas-admin:<commit_sha>
```

También se publica un tag `latest` que apunta a la versión más reciente de `main`.

Para ver todas las versiones disponibles:
```bash
curl -s https://ghcr.io/v2/tarteka/recetas/recetas-api/tags/list | jq .
```

---

## Troubleshooting

### API no responde
```bash
docker compose -f compose.prod.yaml logs recetas-api
# Verificar que RECETAS_API_TOKEN está configurado correctamente
```

### Admin muestra "Autenticación no configurada"
```bash
# Verificar Google OIDC credentials
echo $GOOGLE_CLIENT_ID
echo $GOOGLE_CLIENT_SECRET
# Revisar logs
docker compose -f compose.prod.yaml logs recetas-admin
```

### Puerto 8080 en uso
```bash
lsof -i :8080
# Si es un contenedor anterior, remover:
docker compose -f compose.prod.yaml down
```

### Datos no persisten
```bash
# Verificar volumen
ls -la /opt/recetas/datos/
docker inspect recetas_datos  # Si usas volumen nombrado
```

---

## Operación Rutinaria

### Ver estado de servicios
```bash
docker compose -f compose.prod.yaml ps
```

### Reiniciar servicios sin perder datos
```bash
RELEASE_TAG=$(git rev-parse HEAD) docker compose -f compose.prod.yaml restart
```

### Actualizar a latest (sin especificar SHA)
```bash
docker compose -f compose.prod.yaml pull
docker compose -f compose.prod.yaml up -d
```

---

## NO HAGAS ESTO ⚠️

```bash
# ❌ NUNCA borres volúmenes
docker compose down -v

# ❌ NUNCA hagas pull de imágenes antiguas
docker system prune -a

# ❌ NUNCA edites la SQLite manualmente desde otra máquina
# (riesgo de corrupción)

# ❌ NUNCA commits secretos a Git
git add .env  # ← NO HAGAS ESTO
```

---

## Para Rollback ver ROLLBACK.md
