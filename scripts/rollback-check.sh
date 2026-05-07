#!/usr/bin/env bash
set -u

say() { printf '%s\n' "$*"; }
warn() { printf 'WARNING: %s\n' "$*"; }

run_optional() {
  local label="$1"
  shift

  say "[RUN] $label"
  if "$@"; then
    return 0
  fi

  local status=$?
  warn "$label falló con código $status"
  return 0
}

if ! git rev-parse --is-inside-work-tree >/dev/null 2>&1; then
  printf 'ERROR: el directorio actual no es un repositorio Git válido\n' >&2
  exit 1
fi

say "== Rollback check (diagnóstico no destructivo) =="
say "Usuario actual: $(id -un 2>/dev/null || whoami || echo desconocido)"
say "Ruta actual: $(pwd)"
say "Rama actual: $(git rev-parse --abbrev-ref HEAD 2>/dev/null || echo N/A)"
say "Último commit: $(git log -1 --oneline 2>/dev/null || echo N/A)"

say "[RUN] git status --short"
git status --short || warn "No se pudo obtener git status --short"

say "[RUN] últimos 10 commits"
git log -10 --oneline || warn "No se pudo obtener git log -10"

if [ -f .env ]; then
  say "OK: existe .env (contenido oculto)"
else
  warn "No existe .env"
fi

if [ -d storage/logs ]; then
  say "OK: existe storage/logs"
else
  warn "No existe storage/logs"
fi

say "[RUN] backups en /root/browser-backups (si accesible)"
if [ -d /root/browser-backups ]; then
  if ls -1t /root/browser-backups 2>/dev/null | head -n 10; then
    :
  else
    warn "No se pudo listar /root/browser-backups (posibles permisos)"
  fi
else
  warn "No existe /root/browser-backups"
fi

HOME_BACKUPS="${HOME:-}/browser-backups"
say "[RUN] backups en $HOME_BACKUPS (si existen)"
if [ -n "${HOME:-}" ] && [ -d "$HOME_BACKUPS" ]; then
  if ls -1t "$HOME_BACKUPS" 2>/dev/null | head -n 10; then
    :
  else
    warn "No se pudo listar $HOME_BACKUPS"
  fi
else
  warn "No existe $HOME_BACKUPS"
fi

if command -v php >/dev/null 2>&1 && [ -f bin/browser ]; then
  run_optional "php bin/browser doctor" php bin/browser doctor
  run_optional "php bin/browser auth:doctor" php bin/browser auth:doctor
  run_optional "php bin/browser index:status" php bin/browser index:status
else
  warn "No se puede ejecutar bin/browser (php o bin/browser no disponible)"
fi

say "== Rollback check finalizado =="
exit 0
