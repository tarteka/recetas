#!/usr/bin/env bash
# Apply one immutable release tag transactionally without changing persistent data.
set -Eeuo pipefail

service=${1:?Uso: apply-release.sh <servicio|all> <tag>}
release_tag=${2:?Uso: apply-release.sh <servicio|all> <tag>}
timeout=${RECETAS_HEALTH_TIMEOUT:-180}
interval=${RECETAS_HEALTH_INTERVAL:-5}
compose=(docker compose -f compose.prod.yaml)

case "$service" in
  recetas-api) targets=(recetas-api); api_tag=$release_tag; web_tag=; admin_tag= ;;
  recetas-web) targets=(recetas-web); api_tag=; web_tag=$release_tag; admin_tag= ;;
  recetas-admin) targets=(recetas-admin); api_tag=; web_tag=; admin_tag=$release_tag ;;
  all) targets=(recetas-api recetas-web recetas-admin); api_tag=$release_tag; web_tag=$release_tag; admin_tag=$release_tag ;;
  *) echo "Servicio no válido: $service" >&2; exit 2 ;;
esac

[[ $release_tag =~ ^[0-9a-f]{40}-[0-9]+$ ]] || {
  echo "Tag inmutable no válido" >&2
  exit 2
}
[[ -f .env ]] || { echo "Falta /opt/recetas/.env" >&2; exit 2; }

backup=$(mktemp .env.release-backup.XXXXXX)
cp -p .env "$backup"
transaction_open=false

write_target_env() {
  local temporary
  temporary=$(mktemp .env.release-next.XXXXXX)
  awk -v api="$api_tag" -v web="$web_tag" -v admin="$admin_tag" '
    ($0 ~ /^RECETAS_API_TAG=/ && api != "") ||
    ($0 ~ /^RECETAS_WEB_TAG=/ && web != "") ||
    ($0 ~ /^RECETAS_ADMIN_TAG=/ && admin != "") { next }
    { print }
  ' .env > "$temporary"
  [[ -z $api_tag ]] || printf 'RECETAS_API_TAG=%s\n' "$api_tag" >> "$temporary"
  [[ -z $web_tag ]] || printf 'RECETAS_WEB_TAG=%s\n' "$web_tag" >> "$temporary"
  [[ -z $admin_tag ]] || printf 'RECETAS_ADMIN_TAG=%s\n' "$admin_tag" >> "$temporary"
  chmod --reference=.env "$temporary"
  mv -f "$temporary" .env
}

wait_for_health() {
  local target=$1 deadline now container state health
  deadline=$(( $(date +%s) + timeout ))
  while :; do
    container=$("${compose[@]}" ps -q "$target" 2>/dev/null || true)
    if [[ -n $container ]]; then
      state=$(docker inspect --format '{{.State.Status}}' "$container" 2>/dev/null || true)
      health=$(docker inspect --format '{{if .State.Health}}{{.State.Health.Status}}{{else}}none{{end}}' "$container" 2>/dev/null || true)
      case "$health" in
        healthy) echo "✅ $target healthy"; return 0 ;;
        unhealthy) echo "❌ $target unhealthy" >&2; return 1 ;;
      esac
      if [[ $state == exited || $state == dead ]]; then
        echo "❌ $target terminó con estado $state" >&2
        return 1
      fi
    fi
    now=$(date +%s)
    if (( now >= deadline )); then
      echo "❌ Timeout esperando el healthcheck de $target" >&2
      "${compose[@]}" ps "$target" >&2 || true
      "${compose[@]}" logs --tail=50 "$target" >&2 || true
      return 1
    fi
    sleep "$interval"
  done
}

recover() {
  local status=$?
  if [[ $transaction_open == true ]]; then
    echo "⚠️  Falló la release; restaurando tags previos y contenedores afectados..." >&2
    mv -f "$backup" .env
    "${compose[@]}" pull "${targets[@]}" || true
    "${compose[@]}" up -d --no-deps "${targets[@]}" || true
    transaction_open=false
  fi
  rm -f "$backup"
  exit "$status"
}
trap recover ERR INT TERM

# Persist first, atomically, so a later Compose operation cannot lose the intended release.
write_target_env
transaction_open=true
"${compose[@]}" pull "${targets[@]}"
"${compose[@]}" up -d --no-deps "${targets[@]}"

for target in "${targets[@]}"; do
  wait_for_health "$target"
done

if [[ " ${targets[*]} " == *" recetas-api "* ]]; then
  curl --fail --silent --show-error http://127.0.0.1:8080/salud >/dev/null
fi

transaction_open=false
rm -f "$backup"
trap - ERR INT TERM
echo "✅ Release $release_tag aplicada a: ${targets[*]}"
