#!/usr/bin/env bash

set -euo pipefail

if [[ $# -ne 2 ]]; then
    echo "Uso: generar-imagen.sh <receta_id> <prompt>" >&2
    exit 1
fi

RECETA_ID="$1"
PROMPT="$2"

if ! [[ "$RECETA_ID" =~ ^[1-9][0-9]*$ ]]; then
    echo "Identificador de receta no válido" >&2
    exit 1
fi

DIRECTORIO_TEMPORAL="$(mktemp -d)"
ARCHIVO_SALIDA="${DIRECTORIO_TEMPORAL}/receta.webp"

# Limpia los archivos temporales al finalizar.
trap 'rm -rf "$DIRECTORIO_TEMPORAL"' EXIT

# Genera una imagen local mediante la CLI headless de OpenClaw.
openclaw infer image generate \
    --model openai/gpt-image-2 \
    --output-format webp \
    --quality medium \
    --aspect-ratio 3:2 \
    --output "$ARCHIVO_SALIDA" \
    --prompt "$PROMPT" \
    --json > "${DIRECTORIO_TEMPORAL}/resultado.json"

if [[ ! -s "$ARCHIVO_SALIDA" ]]; then
    echo "OpenClaw no generó un archivo de imagen válido" >&2
    cat "${DIRECTORIO_TEMPORAL}/resultado.json" >&2
    exit 1
fi

# Envía la imagen generada a nuestra API para su normalización definitiva.
"$(dirname "$0")/guardar-imagen.sh" \
    "$RECETA_ID" \
    "$ARCHIVO_SALIDA"