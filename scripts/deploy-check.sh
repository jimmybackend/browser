#!/usr/bin/env bash
set -u

ROOT_DIR="/var/www/browser"

if [ -d "$ROOT_DIR" ]; then
  cd "$ROOT_DIR" || {
    echo "[ERROR] No se pudo entrar a $ROOT_DIR"
    exit 1
  }
else
  echo "[WARN] $ROOT_DIR no existe en este entorno. Usando directorio actual: $(pwd)"
fi

echo "== Deploy check (solo diagnóstico) =="
echo

echo "[whoami]"
whoami || true

echo

echo "[id]"
id || true

echo

echo "[branch actual]"
git rev-parse --abbrev-ref HEAD 2>/dev/null || echo "N/A (no git repo o sin permisos)"

echo

echo "[último commit]"
git log -1 --oneline 2>/dev/null || echo "N/A (no disponible)"

echo

echo "[git status --short]"
git status --short 2>/dev/null || echo "N/A (sin acceso a git status)"

echo

echo "[permisos ruta/archivos relevantes]"
for target in \
  "/var/www/browser" \
  "/var/www/browser/.git" \
  "/var/www/browser/.git/index" \
  "/var/www/browser/.env" \
  "/var/www/browser/storage"
do
  if [ -e "$target" ]; then
    ls -ld "$target" 2>/dev/null || echo "No se pudo leer permisos: $target"
  else
    echo "No existe: $target"
  fi
done

echo

echo "[escritura en /var/www/browser]"
if [ -d "/var/www/browser" ] && [ -w "/var/www/browser" ]; then
  echo "OK: usuario puede escribir en /var/www/browser"
else
  echo "WARN: usuario NO puede escribir en /var/www/browser"
fi

echo

echo "[escritura en .git]"
if [ -d "/var/www/browser/.git" ] && [ -w "/var/www/browser/.git" ]; then
  echo "OK: usuario puede escribir en /var/www/browser/.git"
else
  echo "WARN: usuario NO puede escribir en /var/www/browser/.git"
fi

echo

echo "[vendor/autoload.php]"
if [ -f "vendor/autoload.php" ]; then
  echo "OK: existe vendor/autoload.php"
else
  echo "WARN: NO existe vendor/autoload.php"
fi

echo

echo "[php -v]"
php -v 2>/dev/null || echo "WARN: php no disponible"

echo

echo "[composer]"
if command -v composer >/dev/null 2>&1; then
  composer --version || true
else
  echo "WARN: composer no está instalado o no está en PATH"
fi

echo

echo "[php bin/browser doctor]"
if [ -f "bin/browser" ] && command -v php >/dev/null 2>&1; then
  php bin/browser doctor || echo "WARN: doctor devolvió error (revisar salida)"
else
  echo "WARN: no se puede ejecutar doctor (faltan php o bin/browser)"
fi

echo

echo "== Fin deploy check =="
