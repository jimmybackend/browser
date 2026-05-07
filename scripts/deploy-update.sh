#!/usr/bin/env bash
set -u

ROOT_DIR="/var/www/browser"
NOW_UTC="$(date -u +%Y%m%dT%H%M%SZ)"

say() { printf '%s\n' "$*"; }
warn() { printf 'WARNING: %s\n' "$*"; }
fail() { printf 'ERROR: %s\n' "$*"; exit 1; }

run_required() {
  local label="$1"
  shift

  say "[RUN] $label"
  "$@"
  local status=$?
  if [ "$status" -ne 0 ]; then
    fail "$label falló con código $status"
  fi
}

if [ -d "$ROOT_DIR" ]; then
  cd "$ROOT_DIR" || fail "No se pudo entrar a $ROOT_DIR"
else
  warn "$ROOT_DIR no existe en este entorno. Se mantiene directorio actual: $(pwd)"
fi

if ! git rev-parse --is-inside-work-tree >/dev/null 2>&1; then
  fail "El directorio actual no es un repositorio Git válido"
fi

say "== Deploy update manual seguro (post-merge) =="
say "Usuario actual: $(id -un 2>/dev/null || whoami || echo desconocido)"
say "Rama actual: $(git rev-parse --abbrev-ref HEAD 2>/dev/null || echo N/A)"
say "Último commit: $(git log -1 --oneline 2>/dev/null || echo N/A)"

if [ -f .env ]; then
  env_backup="storage/.env.backup.${NOW_UTC}"
  if mkdir -p storage >/dev/null 2>&1 && cp .env "$env_backup" >/dev/null 2>&1; then
    say "OK: backup de .env creado en $env_backup (contenido oculto)"
  else
    warn "No se pudo crear backup de .env (sin exponer contenido)"
  fi
else
  warn "No existe .env para backup"
fi

nginx_conf="/etc/nginx/sites-available/browser"
if [ -f "$nginx_conf" ]; then
  nginx_backup="storage/nginx-browser.backup.${NOW_UTC}.conf"
  if [ -r "$nginx_conf" ] && mkdir -p storage >/dev/null 2>&1 && cp "$nginx_conf" "$nginx_backup" >/dev/null 2>&1; then
    say "OK: backup de Nginx creado en $nginx_backup"
  else
    warn "Sin permisos para backup de $nginx_conf (se omite)"
  fi
else
  warn "No existe $nginx_conf (se omite backup)"
fi

run_required "git fetch origin" git fetch origin
run_required "git pull --ff-only origin main" git pull --ff-only origin main
run_required "composer install --no-interaction --prefer-dist --no-progress" composer install --no-interaction --prefer-dist --no-progress

say "[RUN] asegurar directorios de storage"
for dir in storage/logs storage/cache storage/sessions; do
  if [ ! -d "$dir" ]; then
    if mkdir -p "$dir"; then
      say "OK: creado $dir"
    else
      warn "No se pudo crear $dir"
    fi
  fi
done

say "[RUN] ajuste de permisos en storage (solo si hay permisos)"
if [ -d storage ]; then
  if chmod -R u+rwX,g+rwX storage >/dev/null 2>&1; then
    say "OK: permisos de storage ajustados"
  else
    warn "Sin permisos para chmod en storage (se omite)"
  fi
else
  warn "No existe directorio storage"
fi

run_required "php bin/browser migrate" php bin/browser migrate
run_required "php bin/browser doctor" php bin/browser doctor
run_required "php bin/browser auth:doctor" php bin/browser auth:doctor

say "[RUN] php bin/browser index:status"
php bin/browser index:status || warn "index:status devolvió error"

say "[RUN] bash scripts/crawler-cron-check.sh"
if bash scripts/crawler-cron-check.sh; then
  say "OK: crawler-cron-check finalizó"
else
  warn "crawler-cron-check reportó warnings/errores"
fi

say "[RUN] verificación crontab local (si es accesible)"
if crontab -l >/dev/null 2>&1; then
  if crontab -l | grep -q 'crawl:run --limit=1'; then
    say "OK: se detectó línea de cron con crawl:run --limit=1"
  else
    warn "No se detectó línea esperada de cron para crawler"
  fi
else
  warn "No se pudo leer crontab del usuario actual"
fi

say "[RUN] recarga de servicios (solo si hay permisos)"
if command -v systemctl >/dev/null 2>&1 && [ "$(id -u)" -eq 0 ]; then
  run_required "systemctl reload php8.5-fpm" systemctl reload php8.5-fpm
  run_required "systemctl reload nginx" systemctl reload nginx
else
  warn "No hay permisos suficientes para recargar servicios automáticamente"
  say "Ejecutar manualmente:"
  say "  systemctl reload php8.5-fpm"
  say "  systemctl reload nginx"
fi

say "== Deploy update completado =="
