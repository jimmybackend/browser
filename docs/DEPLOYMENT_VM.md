# Despliegue manual seguro en VM (sin automatizar producción)

Este documento describe un flujo **manual, conservador y verificable** para actualizar Browser en VM Linux, manteniendo aprobación humana para producción.

## Alcance y reglas

- Ruta de aplicación en VM: `/var/www/browser`.
- Nginx root esperado: `/var/www/browser/public`.
- PHP-FPM socket esperado: `/run/php/php8.5-fpm.sock`.
- Base de datos real esperada: `adbbmis1_browser`.
- El archivo `.env` real está en `/var/www/browser/.env` y **no se debe versionar ni borrar**.
- Nginx queda **fuera del repositorio** (no se modifica desde este repo).

> Importante: no usar `git reset --hard` ni `git clean -fdx` en producción sin revisión humana explícita.

---

## Flujo recomendado de actualización segura

### 1) Verificar usuario operativo y contexto

```bash
cd /var/www/browser
whoami
id
pwd
```

Validar que estás en el proyecto correcto y con el usuario esperado.

### 2) Diagnóstico previo (sin cambios)

```bash
bash scripts/deploy-check.sh
```

Este script solo inspecciona estado de Git, permisos y prerequisitos.

### 3) Revisar permisos antes de `git pull`

Validar dueño/permisos de:

- `/var/www/browser`
- `/var/www/browser/.git`
- `/var/www/browser/.git/index`
- `/var/www/browser/.env`
- `/var/www/browser/storage`

Si hay errores de permisos en `.git` o archivos propiedad de `root`, **detener despliegue** y corregir con usuario admin/root desde consola/panel cloud.

### 4) Respaldar `.env` y configuración Nginx (fuera de repo)

Antes de actualizar código, respaldar:

- `/var/www/browser/.env`
- config activa de Nginx del sitio (por ejemplo `/etc/nginx/sites-available/...`)

Ejemplo (si tienes permisos):

```bash
cp /var/www/browser/.env /var/www/browser/.env.bak.$(date +%F-%H%M%S)
cp /etc/nginx/sites-available/browser.conf /etc/nginx/sites-available/browser.conf.bak.$(date +%F-%H%M%S)
```

Si no tienes sudo/permiso sobre Nginx, solicitar respaldo al usuario administrador.

### 5) Actualizar código

```bash
cd /var/www/browser
git pull origin main
```

No usar comandos destructivos para “forzar” estado en producción.

### 6) Post-pull conservador

```bash
bash scripts/deploy-after-pull.sh
```

Este script:

- instala dependencias con Composer,
- ejecuta migraciones con `php bin/browser migrate`,
- ejecuta chequeos `doctor` y `auth:doctor`,
- crea carpetas `storage/logs`, `storage/cache`, `storage/sessions` si faltan,
- intenta ajustar permisos de `storage` sin sudo (si falla, avisa).

### 7) Recarga de servicios (solo si hay sudo/root)

Si tienes privilegios:

```bash
sudo systemctl reload php8.5-fpm
sudo systemctl reload nginx
```

Si **no** tienes sudo/root, no improvisar: escalar a administración de infraestructura.

---

## Recuperación de errores frecuentes

### Error: `fatal: Unable to create '/var/www/browser/.git/index.lock': Permission denied`

Causa probable: permisos/owner incorrectos en `.git` (ej. archivos de root).

Acción:

1. No seguir con el deploy.
2. Verificar owner/grupo de `.git` y `.git/index`.
3. Solicitar corrección con usuario admin/root desde consola/panel cloud.

### Error: `error: cannot open '.git/FETCH_HEAD': Permission denied`

Causa probable: `.git/FETCH_HEAD` sin permisos para usuario operativo.

Acción:

1. Detener despliegue.
2. Corregir ownership/permisos con usuario admin/root.
3. Reintentar `git pull origin main`.

### Caso: usuario sin sudo

Si no tienes sudo, no intentes resolver permisos del sistema desde la app.

- Coordinar corrección desde consola del proveedor cloud o con usuario administrador.
- Reintentar despliegue solo cuando permisos estén saneados.

### Caso: `.env` aparece como `?? .env`

Significa que `.env` local no está siendo ignorado por Git en ese entorno.

Acción segura:

1. **No** hacer `git add .env`.
2. Verificar reglas de ignore del repo.
3. Mantener `.env` solo en VM, fuera de versionado.

### Caso: `composer.lock` aparece como `?? composer.lock`

Acción segura:

1. No asumir que debe commitearse desde VM de producción.
2. Validar política del repositorio y estado de la rama de desarrollo.
3. En producción, priorizar no introducir cambios locales no revisados.

### Caso: `vendor/` aparece como `?? vendor/`

Acción segura:

1. No commitear `vendor/` desde VM.
2. Mantener `vendor/` como artefacto local de `composer install`.

---

## Checklist rápido manual

1. `cd /var/www/browser`
2. `bash scripts/deploy-check.sh`
3. Respaldos de `.env` y Nginx (si permisos lo permiten)
4. `git pull origin main`
5. `bash scripts/deploy-after-pull.sh`
6. Recargar `php8.5-fpm` y `nginx` solo con sudo/root
7. Verificar aplicación y logs

---

## Qué no hacer en producción

- No borrar ni subir `.env`.
- No modificar Nginx desde este repositorio.
- No usar `git reset --hard` ni `git clean -fdx` sin revisión humana.
- No automatizar despliegue a producción sin aprobación humana.
