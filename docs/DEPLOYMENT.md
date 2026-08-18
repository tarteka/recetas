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

## Fijar RELEASE_TAG (obligatorio)

`compose.prod.yaml` **no tiene versión por defecto**: `RELEASE_TAG` es una variable
obligatoria (`${RELEASE_TAG:?...}`), igual que `RECETAS_API_TOKEN`. Si no está
definida, `docker compose` falla explícitamente en vez de arrancar silenciosamente
la última imagen que hubiera en caché o un `latest` que podría no ser el que
esperas desplegar.

### Cómo obtener el tag correcto

Cada push a `main` publica en GHCR una imagen etiquetada con el **SHA del commit**
(y también `latest`, pero ya no se usa por defecto). El workflow **Build & Publish
- GHCR** es la fuente de verdad:

- En GitHub → **Actions → Build & Publish - GHCR**, el run correspondiente al
  commit que quieres desplegar muestra el SHA usado como tag.
- O, con el repo actualizado localmente: `git rev-parse HEAD` (o el SHA del commit
  concreto que quieras fijar) apunta al mismo valor que usa el workflow
  (`${{ github.sha }}`).
- Las imágenes publicadas se pueden listar en **GHCR → paquete → Tags**
  (`ghcr.io/tarteka/recetas/recetas-api`, `recetas-web`, `recetas-admin`).

### Fijarlo de forma persistente en el VPS

Añade `RELEASE_TAG` a `/opt/recetas/.env` (Docker Compose lo carga automáticamente
de ahí si no se exporta explícitamente en el shell):

```env
RELEASE_TAG=<sha_del_commit>
```

Así, cualquier `docker compose -f compose.prod.yaml pull|up|restart` que se
ejecute manualmente en el VPS usa ese valor sin tener que repetirlo cada vez.
**Recuerda actualizarlo tras cada despliegue** que quieras fijar como base para
operaciones manuales posteriores (reinicios, etc.) — el despliegue automático
(`deploy.yml`) no depende de `.env` porque exporta `RELEASE_TAG` él mismo con el
SHA que está desplegando.

## Despliegue Manual en VPS

Si necesitas desplegar manualmente (sin GitHub Actions):

```bash
cd /opt/recetas

# 1. Definir el SHA a desplegar (ver "Cómo obtener el tag correcto" arriba)
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

> Si ya fijaste `RELEASE_TAG` en `/opt/recetas/.env` (ver arriba), puedes omitir el
> prefijo `RELEASE_TAG=$RELEASE_TAG` en estos comandos — Compose lo toma del
> `.env` automáticamente. El prefijo explícito sigue siendo útil para desplegar
> puntualmente un SHA distinto sin tocar el `.env`.

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

## Backups Automáticos

`scripts/backup-sqlite.sh` automatiza el backup ya descrito en el README (parar
`recetas-api`, empaquetar `datos/`, reanudarla) y además:

- rota los backups locales, conservando los **7 diarios** y **4 semanales**
  más recientes (el backup del domingo cuenta también como semanal, así que
  una sola ejecución diaria basta para ambas rotaciones);
- si hay un remoto [rclone](https://rclone.org/) configurado, sincroniza
  `backups/` con él (`rclone sync`), reflejando la misma rotación fuera del
  VPS sin subir credenciales a ningún sitio — rclone las guarda en su propia
  config (`~/.config/rclone/rclone.conf`), nunca en variables de entorno ni
  en este repositorio.

### 1. Instalar y configurar rclone (una sola vez)

```bash
curl https://rclone.org/install.sh | sudo bash
rclone config   # crea un remoto interactivamente (S3, Backblaze B2, Drive...)
```

### 2. Copiar el script al VPS

```bash
mkdir -p /opt/recetas/scripts
cp scripts/backup-sqlite.sh /opt/recetas/scripts/
chmod +x /opt/recetas/scripts/backup-sqlite.sh
```

### 3. Configurar el destino remoto

Añade a `/opt/recetas/.env` (junto al resto de secretos, nunca en Git):

```env
RECETAS_BACKUP_RCLONE_REMOTE=midestino:recetas-backups
```

Prueba manualmente antes de automatizar nada:

```bash
cd /opt/recetas
RECETAS_BACKUP_RCLONE_REMOTE=midestino:recetas-backups ./scripts/backup-sqlite.sh
```

### 4. Programarlo con un systemd timer

`/etc/systemd/system/recetas-backup.service`:

```ini
[Unit]
Description=Backup de datos de Recetas (SQLite + imágenes)
After=docker.service
Requires=docker.service

[Service]
Type=oneshot
User=deploy
WorkingDirectory=/opt/recetas
EnvironmentFile=/opt/recetas/.env
ExecStart=/opt/recetas/scripts/backup-sqlite.sh
```

`/etc/systemd/system/recetas-backup.timer`:

```ini
[Unit]
Description=Ejecuta el backup de Recetas todos los días

[Timer]
OnCalendar=*-*-* 03:30:00
Persistent=true
RandomizedDelaySec=10min

[Install]
WantedBy=timers.target
```

Activarlo:

```bash
sudo cp recetas-backup.service recetas-backup.timer /etc/systemd/system/
sudo systemctl daemon-reload
sudo systemctl enable --now recetas-backup.timer

# Comprobar la próxima ejecución programada
systemctl list-timers recetas-backup.timer

# Ver el resultado de la última ejecución
journalctl -u recetas-backup.service --since today
```

`Persistent=true` hace que, si el VPS estaba apagado a la hora programada
(reinicio, mantenimiento...), el backup se ejecute en cuanto vuelva a estar
disponible en vez de saltarse ese día.

### Alternativa: cron

Si prefieres cron en vez de systemd:

```bash
# crontab -e (usuario deploy)
30 3 * * * /opt/recetas/scripts/backup-sqlite.sh >> /var/log/recetas-backup.log 2>&1
```

En este caso, exporta `RECETAS_BACKUP_RCLONE_REMOTE` (y el resto de variables
opcionales) desde el propio crontab o cárgalas al principio del script, ya
que cron no lee `/opt/recetas/.env` automáticamente como sí hace
`EnvironmentFile=` en systemd.

---

## Variables de Entorno en VPS

El archivo `/opt/recetas/.env` debe contener:

```env
# Versión a desplegar (obligatoria, ver "Fijar RELEASE_TAG" arriba)
RELEASE_TAG=<sha_del_commit>

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
ghcr.io/tarteka/recetas/recetas-api:<commit_sha>
ghcr.io/tarteka/recetas/recetas-web:<commit_sha>
ghcr.io/tarteka/recetas/recetas-admin:<commit_sha>
```

También se publica un tag `latest` que apunta a la versión más reciente de
`main`, pero `compose.prod.yaml` ya no lo usa como valor por defecto — hay que
fijar siempre el SHA explícitamente vía `RELEASE_TAG` (ver "Fijar RELEASE_TAG"
arriba), para que el despliegue sea siempre reproducible y no dependa de qué
sea "lo último" en el momento de tirar de la imagen.

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

### Actualizar a la última versión de main

Ya no existe un `latest` implícito: hay que fijar `RELEASE_TAG` al SHA que
quieras desplegar (ver "Fijar RELEASE_TAG" arriba). Actualiza `RELEASE_TAG` en
`/opt/recetas/.env` con el SHA nuevo y luego:
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
