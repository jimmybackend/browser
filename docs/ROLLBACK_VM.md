# ROLLBACK_VM

## Propósito

Documentar un procedimiento de **rollback manual seguro** para la VM (`/var/www/browser`) después de un deploy fallido, manteniendo control humano para producción y base de datos.

## Qué es rollback manual y cuándo usarlo

Un rollback manual es una reversión controlada a un estado de código previo y estable cuando una actualización genera incidentes.

Usarlo cuando, después de `bash scripts/deploy-update.sh`, se detecte alguno de estos síntomas:

- `php bin/browser doctor` o `php bin/browser auth:doctor` falla.
- La app queda inaccesible o con errores severos.
- `composer install` falla y deja el entorno inconsistente.
- Una migración produce comportamiento inesperado en datos.
- Reload de servicios (`php-fpm`/`nginx`) falla y afecta disponibilidad.

## Regla de control humano

El rollback en VM requiere **aprobación humana explícita** antes de ejecutar pasos que cambien código activo o base de datos.

- Sin auto-rollback.
- Sin cambios autónomos en producción por Codex.
- Cambios de base de datos siguen con aprobación humana.

## Comandos prohibidos sin revisión

No ejecutar estos comandos de forma impulsiva:

- `git reset --hard`
- `git clean -fdx`

Solo podrían considerarse con revisión manual exhaustiva del impacto y respaldo previo.

## Checklist previo al rollback

Desde `/var/www/browser`:

1. Confirmar commit actual:
   ```bash
   git log -1 --oneline
   ```
2. Guardar estado de cambios locales:
   ```bash
   git status --short
   ```
3. Respaldar `.env` **sin mostrar contenido** (ejemplo con timestamp):
   ```bash
   cp .env "storage/.env.backup.$(date -u +%Y%m%dT%H%M%SZ)"
   ```
4. Respaldar `storage/logs` si se requiere análisis posterior:
   ```bash
   tar -czf "storage/logs-backup-$(date -u +%Y%m%dT%H%M%SZ).tgz" storage/logs
   ```
5. Si el incidente involucra migraciones o datos, respaldar base de datos antes de cualquier reversión.
6. Revisar el último backup generado por `scripts/deploy-update.sh` (por ejemplo en `/root/browser-backups` o `$HOME/browser-backups`).
7. Ejecutar diagnóstico no destructivo:
   ```bash
   bash scripts/rollback-check.sh
   ```

## Rollback de código conservador (solo con criterio técnico claro)

Si no es posible revertir PR de inmediato en GitHub:

1. Identificar commit anterior seguro (SHA exacto).
2. Crear referencia temporal local antes de moverse (rama o tag local) para trazabilidad.
3. Usar `git checkout <commit>` **solo si se entiende el impacto** (detached HEAD, diferencias con `main`, compatibilidad con dependencias).
4. Validar app y servicios antes de considerar completado el rollback.

> Nota: este camino es de contingencia. La ruta preferida es revertir PR en GitHub.

## Rollback recomendado (ruta preferida)

1. En GitHub, abrir el PR problemático y usar **“Revert pull request”**.
2. Revisar el PR de revert creado.
3. Fusionar manualmente ese PR de revert (sin auto-merge).
4. En VM:
   ```bash
   cd /var/www/browser
   bash scripts/deploy-update.sh
   ```
5. Ejecutar validación post-rollback (sección siguiente).

## Casos de fallo comunes durante incidentes

### Si falló reload de Nginx/PHP-FPM

1. Verificar sintaxis/configuración antes de reintentar reload:
   - `sudo nginx -t`
   - `sudo systemctl status php8.5-fpm --no-pager`
   - `sudo systemctl status nginx --no-pager`
2. Corregir causa raíz con aprobación humana.
3. Reintentar reload manual y volver a validar la app.

### Si falló `composer install`

1. Revisar error exacto (red/proxy, lockfile, permisos, disco).
2. No borrar archivos masivamente con `git clean -fdx`.
3. Resolver conectividad/proxy o dependencias y reintentar:
   ```bash
   composer install --no-interaction --prefer-dist --no-progress
   ```
4. Reejecutar comandos de salud.

### Si falló migración

1. Detener avance y escalar a revisión humana.
2. Evaluar impacto en datos antes de cualquier acción.
3. Si aplica rollback de datos, ejecutar solo con backup confirmado.
4. Registrar incidencia y decisión técnica tomada.

## Migraciones destructivas

Migraciones destructivas (drop/alter con pérdida potencial) requieren:

- plan especial documentado,
- evaluación de impacto,
- plan de reversión,
- aprobación humana explícita.

## Validación post-rollback

Ejecutar:

```bash
php bin/browser doctor
php bin/browser auth:doctor
php bin/browser index:status
sudo -u www-data php bin/browser crawl:status
```

Además:

- Revisar logs de aplicación/crawler (`storage/logs/*`).
- Confirmar que el commit activo corresponde al estado esperado.
- Confirmar que producción quedó estable antes de cerrar incidente.

## Alcance y seguridad

- Este documento no incluye credenciales.
- No cambia Nginx desde Git.
- No modifica migraciones ni estructura de base de datos.
- Mantiene control humano para producción y BD.
