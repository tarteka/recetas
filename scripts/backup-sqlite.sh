#!/usr/bin/env bash
#
# Backup de datos/ (SQLite + imágenes): para recetas-api, empaqueta datos/,
# la reanuda, rota los backups locales y (si hay un remoto rclone
# configurado) sincroniza esa misma rotación fuera del VPS.
#
# Config vía variables de entorno (todas opcionales, con valores por
# defecto pensados para /opt/recetas en el VPS):
#
#   RECETAS_BACKUP_COMPOSE_FILE   Ruta a compose.prod.yaml
#   RECETAS_BACKUP_DATOS_DIR      Directorio datos/ a respaldar
#   RECETAS_BACKUP_DIR            Directorio donde guardar los .tar.gz
#   RECETAS_BACKUP_RCLONE_REMOTE  Remoto rclone (ej: midestino:recetas-backups).
#                                  Vacío = solo backup local, sin subir.
#   RECETAS_BACKUP_DIARIOS        Nº de backups diarios a conservar (por defecto 7)
#   RECETAS_BACKUP_SEMANALES      Nº de backups semanales a conservar (por defecto 4)
#
# Las credenciales del remoto viven en la config propia de rclone
# (~/.config/rclone/rclone.conf), nunca en variables de entorno de este
# script ni en el repositorio.
#
# El backup del domingo se conserva también como "semanal", además de
# como diario del día — así una sola ejecución diaria basta para ambas
# rotaciones, sin necesitar una entrada de cron/systemd distinta.

set -euo pipefail

COMPOSE_FILE="${RECETAS_BACKUP_COMPOSE_FILE:-/opt/recetas/compose.prod.yaml}"
DATOS_DIR="${RECETAS_BACKUP_DATOS_DIR:-/opt/recetas/datos}"
BACKUP_DIR="${RECETAS_BACKUP_DIR:-/opt/recetas/backups}"
RCLONE_REMOTE="${RECETAS_BACKUP_RCLONE_REMOTE:-}"
DIARIOS_A_CONSERVAR="${RECETAS_BACKUP_DIARIOS:-7}"
SEMANALES_A_CONSERVAR="${RECETAS_BACKUP_SEMANALES:-4}"

if [ ! -d "$DATOS_DIR" ]; then
    echo "El directorio de datos '$DATOS_DIR' no existe" >&2
    exit 1
fi

fecha="$(date +%F-%H%M)"
dia_semana="$(date +%u)" # 1=lunes ... 7=domingo

mkdir -p "$BACKUP_DIR/diarios" "$BACKUP_DIR/semanales"

archivo_diario="$BACKUP_DIR/diarios/recetas-$fecha.tar.gz"

api_detenida=0
reanudar_api() {
    if [ "$api_detenida" = "1" ]; then
        echo "Reanudando recetas-api..."
        docker compose -f "$COMPOSE_FILE" start recetas-api || true
    fi
}
trap reanudar_api EXIT

echo "Deteniendo recetas-api..."
docker compose -f "$COMPOSE_FILE" stop recetas-api
api_detenida=1

echo "Empaquetando $DATOS_DIR..."
tar -czf "$archivo_diario" -C "$(dirname "$DATOS_DIR")" "$(basename "$DATOS_DIR")"

echo "Reanudando recetas-api..."
docker compose -f "$COMPOSE_FILE" start recetas-api
api_detenida=0

if [ "$dia_semana" = "7" ]; then
    archivo_semanal="$BACKUP_DIR/semanales/recetas-$fecha.tar.gz"
    cp "$archivo_diario" "$archivo_semanal"
    echo "Backup semanal: $archivo_semanal"
fi

echo "Rotando backups locales (conservar $DIARIOS_A_CONSERVAR diarios, $SEMANALES_A_CONSERVAR semanales)..."
find "$BACKUP_DIR/diarios" -maxdepth 1 -name '*.tar.gz' -printf '%T@ %p\n' 2>/dev/null \
    | sort -rn | tail -n "+$((DIARIOS_A_CONSERVAR + 1))" | cut -d' ' -f2- | xargs -r rm -v
find "$BACKUP_DIR/semanales" -maxdepth 1 -name '*.tar.gz' -printf '%T@ %p\n' 2>/dev/null \
    | sort -rn | tail -n "+$((SEMANALES_A_CONSERVAR + 1))" | cut -d' ' -f2- | xargs -r rm -v

if [ -n "$RCLONE_REMOTE" ]; then
    echo "Sincronizando $BACKUP_DIR con $RCLONE_REMOTE..."
    # rclone sync deja el remoto idéntico a $BACKUP_DIR: sube lo nuevo y
    # borra en remoto lo que la rotación acaba de borrar en local. Así la
    # rotación solo se implementa una vez, no por duplicado en cada sitio.
    rclone sync "$BACKUP_DIR" "$RCLONE_REMOTE" --create-empty-src-dirs
else
    echo "RECETAS_BACKUP_RCLONE_REMOTE no está definido: backup solo local, sin subir fuera del VPS."
fi

echo "✅ Backup completado: $archivo_diario"
