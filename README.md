# Recetas

> Aplicación de gestión de recetas con Docker, CI/CD automático y **integración con OpenClaw + Telegram**.

![CI](https://github.com/tarteka/recetas/actions/workflows/ci.yml/badge.svg)
![Build & Publish](https://github.com/tarteka/recetas/actions/workflows/build-publish.yml/badge.svg)
![Deploy](https://github.com/tarteka/recetas/actions/workflows/deploy.yml/badge.svg)

🍳 **En vivo:** https://recetas.proyectozero.org

## Lo Especial

✨ **Agrega recetas desde Telegram** — Envía una URL a OpenClaw y automáticamente:
- Extrae los datos de la receta
- Procesa la imagen (normaliza a WebP 1200×800)
- Genera imagen con IA si falta
- Guarda en la base de datos

Perfecto para coleccionar recetas sin salir de Telegram.

## Tech Stack

- **Backend:** PHP 8.4 + Slim Framework + SQLite
- **Frontend:** React 18 + TypeScript + Vite
- **Web:** Caddy 2 (HTTPS automático)
- **CI/CD:** GitHub Actions → GHCR → VPS (SSH)
- **Contenedores:** Docker Compose (desarrollo y producción)

## Inicio Rápido

```bash
# Desarrollo local
git clone https://github.com/tarteka/recetas.git && cd recetas
cp .env.example .env
docker compose up -d

# Web en http://localhost:5173
# Admin en http://localhost:5174
# API en http://localhost:8080
# Documentación de la API (Swagger UI) en http://localhost:8081
```

Ver **[DEVELOPMENT.md](docs/DEVELOPMENT.md)** para detalles.

## Documentación

- **[DEVELOPMENT.md](docs/DEVELOPMENT.md)** — Setup local con Docker y Google OIDC
- **[DEPLOYMENT.md](docs/DEPLOYMENT.md)** — Producción, backup y health checks
- **[openapi.yaml](recetas-api/openapi.yaml)** — Spec OpenAPI 3.x de la API (bearer token para OpenClaw, sesión + CSRF para el panel admin). En desarrollo, navegable en http://localhost:8081 (Swagger UI, ver `docker compose up`)
- **[ROLLBACK.md](docs/ROLLBACK.md)** — Rollback automático y manual

## Seguridad

### Buenas Prácticas

```bash
# Verificar antes de commitear
git status --short

# Confirmar que .gitignore protege secretos
git check-ignore -v .env datos/

# Generar tokens seguros
openssl rand -hex 32
```

### Secretos en Producción

Configurar via `.env` en VPS únicamente:

```dotenv
RECETAS_API_TOKEN=<token-fuerte-aleatorio>
GOOGLE_CLIENT_ID=<oauth-client-id>
GOOGLE_CLIENT_SECRET=<oauth-secret>
ADMIN_SESSION_SECRET=<session-key>
```

Nunca agregar a Git, ni siquiera en `.env.example`.

## Contribución

1. **Fork** el repositorio
2. **Crear rama** (`git checkout -b feature/mi-feature`)
3. **Hacer cambios** y probar localmente (`docker compose up -d`)
4. **Commit claro** (`git commit -m "feat: agregar X"`)
5. **Push** y **crear Pull Request**

Todos los PRs ejecutan:
- Validación de sintaxis PHP
- Lint y build de React/TypeScript
- Validación de construcción Docker

Si todos los checks pasan, mergear a `main` dispara despliegue automático.

## Backup y Recuperación

```bash
# Backup (en VPS)
mkdir -p backups
sudo docker compose -f compose.prod.yaml stop recetas-api
sudo tar -czf "backups/recetas-$(date +%F-%H%M).tar.gz" datos/
sudo docker compose -f compose.prod.yaml start recetas-api

# Restaurar
sudo docker compose -f compose.prod.yaml stop recetas-api
sudo tar -xzf backups/recetas-YYYY-MM-DD-HHMM.tar.gz
sudo docker compose -f compose.prod.yaml start recetas-api
```

Guardar backups fuera del VPS y verificar restauración regularmente.

Para automatizarlo (rotación de backups diarios/semanales + subida a un
destino externo vía rclone), ver `scripts/backup-sqlite.sh` y la sección
"Backups Automáticos" de [DEPLOYMENT.md](docs/DEPLOYMENT.md).

## Licencia

Apache License 2.0 — Ver [LICENSE](LICENSE)

---

**Estado:** Production-ready con CI/CD automatizado