#!/usr/bin/env bash
set -u

ROOT_DIR="/var/www/browser"

if [ -d "$ROOT_DIR" ]; then
  cd "$ROOT_DIR" || {
    echo "[ERROR] No se pudo entrar a $ROOT_DIR"
    exit 1
  }
fi

echo "== Deploy post-pull (conservador, sin git pull) =="

if ! command -v php >/dev/null 2>&1; then
  echo "[ERROR] php no está disponible en PATH"
  exit 1
fi

if ! command -v composer >/dev/null 2>&1; then
  echo "[ERROR] composer no está disponible en PATH"
  exit 1
fi

echo "[1/6] composer install"
composer install --no-interaction --prefer-dist --no-progress || {
  echo "[ERROR] composer install falló"
  exit 1
}

echo "[2/6] asegurar directorios storage"
for dir in storage/logs storage/cache storage/sessions; do
  if [ ! -d "$dir" ]; then
    mkdir -p "$dir" || echo "[WARN] No se pudo crear $dir"
  fi
done

echo "[3/6] ajuste de permisos en storage (sin sudo)"
if [ -d "storage" ]; then
  chmod -R u+rwX,g+rwX storage 2>/dev/null || echo "[WARN] No se pudo ajustar permisos en storage (sin sudo)"
else
  echo "[WARN] No existe directorio storage"
fi

echo "[4/6] php bin/browser migrate"
php bin/browser migrate || {
  echo "[ERROR] migrate falló"
  exit 1
}

echo "[5/6] php bin/browser doctor"
php bin/browser doctor || {
  echo "[ERROR] doctor falló"
  exit 1
}

echo "[6/6] php bin/browser auth:doctor"
php bin/browser auth:doctor || {
  echo "[ERROR] auth:doctor falló"
  exit 1
}

echo "[INFO] Si necesitas recargar php-fpm/nginx y no tienes sudo/root, coordinar con administración."
echo "== Fin deploy post-pull =="
