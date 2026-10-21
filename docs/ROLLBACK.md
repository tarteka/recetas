# Rollback de Producción

Las releases usan tags inmutables `<sha-completo>-<run-id>`. Un rollback nunca
reconstruye una imagen, no ejecuta `docker compose down` y no modifica `datos/`.

## Rollback selectivo

En **Actions → Rollback - Manual → Run workflow**:

1. Selecciona `recetas-api`, `recetas-web`, `recetas-admin` o `all`.
2. Introduce el tag inmutable de una imagen publicada.
3. Revisa el log hasta confirmar que el healthcheck del servicio seleccionado es
   `healthy`.

Usa `all` únicamente con un tag de una publicación automática que haya generado
los tres componentes. Un tag de una reconstrucción manual de API no existe para
Web/Admin y el rollback conjunto fallará de forma segura durante el `pull`.

El rollback registra el tag solicitado de forma atómica, recrea solo el servicio
elegido con `--no-deps` y confirma sus healthchecks. La API se valida además con
`/salud`. Si cualquier etapa falla, se restaura el `.env` anterior y los
contenedores afectados se recrean con sus tags previos.

## Comprobar la versión activa

```bash
cd /opt/recetas
grep '^RECETAS_.*_TAG=' .env
docker compose -f compose.prod.yaml images
docker compose -f compose.prod.yaml ps
```

## Limitaciones

Las imágenes se recuperan correctamente, pero un rollback de aplicación no puede
deshacer una migración de datos incompatible. Antes de desplegar cambios de
esquema, realiza y prueba una copia de seguridad de `datos/` y documenta una
estrategia de reversión compatible.
