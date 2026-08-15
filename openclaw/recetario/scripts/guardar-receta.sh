#!/bin/sh
set -eu

# Recibe el JSON completo de la receta por stdin.
JSON="$(cat)"

if [ -z "${RECETAS_API_TOKEN:-}" ]; then
    echo "RECETAS_API_TOKEN no está configurado" >&2
    exit 1
fi

curl \
    --fail-with-body \
    --silent \
    --show-error \
    --connect-timeout 5 \
    --max-time 15 \
    -X POST \
    -H "Content-Type: application/json" \
    -H "Authorization: Bearer ${RECETAS_API_TOKEN}" \
    --data-binary "$JSON" \
    http://127.0.0.1:8080/recetas

# Envía a Slim exclusivamente el JSON recibido por stdin.
