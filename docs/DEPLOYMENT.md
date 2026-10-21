# Despliegue a Producción

## Flujo automático

Un `push` a `main` ejecuta CI. Solo si CI finaliza correctamente se publican los
tres servicios y se inicia el despliegue automático. Cada imagen recibe un tag
inmutable `<sha-completo>-<run-id>`; no se usa `latest` para desplegar.

El despliegue automático se serializa con los rollbacks y rechaza una release
automática anterior al commit que ya está activo en el VPS.

## Reconstrucción manual sin despliegue

En **Actions → Build & Publish - GHCR → Run workflow**, elige uno de:

- `recetas-api`
- `recetas-web`
- `recetas-admin`
- `all`

La ejecución manual publica solo los servicios seleccionados y termina sin
desplegar. El resumen del run muestra el tag de release. Una reconstrucción de
`recetas-api` consulta de nuevo `php:8.4-apache`, conserva caché útil y valida la
versión de PHP de la imagen final para AMD64 y ARM64.

## Despliegue manual explícito

En **Actions → Deploy - Producción → Run workflow**, elige el servicio y pega el
tag inmutable publicado. Este es el único modo de desplegar una publicación
manual. Para API, por ejemplo:

```text
service: recetas-api
release_tag: <sha-completo>-<run-id>
```

El procedimiento descarga y recrea exclusivamente el servicio elegido con
`docker compose up -d --no-deps`. No reinicia Caddy, Web o Admin durante una
actualización de API.

## Primera migración desde `RELEASE_TAG`

Antes del primer despliegue, conserva el `RELEASE_TAG` existente y los secretos
en `/opt/recetas/.env`. Las variables específicas tienen fallback a ese valor,
por lo que la migración no requiere editar el `.env` manualmente:

```env
RELEASE_TAG=<tag-anterior>
RECETAS_API_TOKEN=<secreto-existente>
```

El primer despliegue exitoso añade el tag específico del servicio actualizado:

```env
RECETAS_API_TAG=<sha>-<run-id>
RECETAS_WEB_TAG=<sha>-<run-id>
RECETAS_ADMIN_TAG=<sha>-<run-id>
```

Mantén `RELEASE_TAG` hasta que los tres tags específicos estén presentes. Nunca
edites ni borres `datos/`, y no uses `docker compose down` ni `down -v` en una
actualización ordinaria.

## Recuperación ante fallo

El script de release guarda una copia de `.env`, escribe atómicamente el tag
objetivo, descarga la imagen y espera el healthcheck Docker de cada servicio
afectado. El timeout predeterminado es 180 segundos y puede ajustarse con
`RECETAS_HEALTH_TIMEOUT`. Si falla cualquier paso, restaura `.env` y recrea solo
los servicios afectados con sus tags previos. Revisa el log del workflow y:

```bash
cd /opt/recetas
docker compose -f compose.prod.yaml ps
docker compose -f compose.prod.yaml logs --tail=50 recetas-api
grep '^RECETAS_.*_TAG=' .env
```

## Comprobaciones operativas

```bash
cd /opt/recetas
docker compose -f compose.prod.yaml images
docker compose -f compose.prod.yaml ps
grep '^RECETAS_.*_TAG=' .env
curl --fail http://127.0.0.1:8080/salud
```

El healthcheck de Docker confirma la disponibilidad del contenedor. Para API se
comprueba además `/salud`; para Web y Admin, verifica también las URLs públicas
cuando el cambio afecte a su configuración o al enrutamiento de Caddy.

## Secretos del VPS

`/opt/recetas/.env` contiene secretos y nunca se versiona. Debe incluir como
mínimo `RECETAS_API_TOKEN`, las credenciales Google y los secretos de Admin. El
VPS debe tener acceso de lectura a las imágenes privadas de GHCR.
