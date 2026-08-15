#!/usr/bin/env bash

set -euo pipefail

# Comprueba que el token necesario para escribir en la API está disponible.
if [[ -z "${RECETAS_API_TOKEN:-}" ]]; then
    echo "RECETAS_API_TOKEN no está configurado" >&2
    exit 1
fi

if [[ $# -ne 2 ]]; then
    echo "Uso: guardar-imagen.sh <receta_id> <archivo_imagen>" >&2
    exit 1
fi

RECETA_ID="$1"
ARCHIVO="$2"

# Valida que el identificador recibido sea un entero positivo.
if ! [[ "$RECETA_ID" =~ ^[1-9][0-9]*$ ]]; then
    echo "Identificador de receta no válido" >&2
    exit 1
fi

# Comprueba que el archivo existe antes de enviarlo.
if [[ ! -f "$ARCHIVO" ]]; then
    echo "No existe el archivo de imagen: $ARCHIVO" >&2
    exit 1
fi

curl \
    --fail-with-body \
    --silent \
    --show-error \
    -X POST \
    -H "Authorization: Bearer ${RECETAS_API_TOKEN}" \
    -H "Content-Type: application/octet-stream" \
    --data-binary "@${ARCHIVO}" \
    "http://127.0.0.1:8080/recetas/${RECETA_ID}/imagen"

# Envía los bytes de la imagen; la API valida y normaliza el formato.