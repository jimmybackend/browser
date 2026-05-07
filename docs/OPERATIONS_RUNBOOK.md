# OPERATIONS_RUNBOOK

## Propósito

Guía operativa diaria para mantener `jimmybackend/browser` estable en VM, con despliegues manuales y control humano de producción y base de datos.

## Alcance y límites

- Este runbook es **operativo** (no cambia código funcional).
- No modifica Nginx desde Git.
- No modifica migraciones ni estructura de base de datos.
- No automatiza producción de forma autónoma.
- Mantiene aprobación humana para cambios de producción y BD.

## Checklist diario (VM)

Ejecutar en `/var/www/browser`:

1. Verificar repo limpio:
   ```bash
   git status --short
   ```
2. Salud general de app:
   ```bash
   php bin/browser doctor
   ```
3. Salud de autenticación:
   ```bash
   php bin/browser auth:doctor
   ```
4. Estado de indexación:
   ```bash
   php bin/browser index:status
   ```
5. Estado de crawler con usuario real del cron:
   ```bash
   sudo -u www-data php bin/browser crawl:status
   ```
6. Revisar log reciente del crawler:
   ```bash
   tail -n 100 storage/logs/crawler.log
   ```
7. Confirmar cron de `www-data`:
   ```bash
   sudo crontab -u www-data -l
   ```

## Checklist post-merge (despliegue manual)

Después de fusionar PR manualmente en GitHub:

```bash
cd /var/www/browser
bash scripts/deploy-update.sh
php bin/browser index:status
sudo -u www-data php bin/browser crawl:status
```

## Si falla el crawler

1. Revisar estado operativo:
   ```bash
   sudo -u www-data php bin/browser crawl:status
   ```
2. Revisar errores en log:
   ```bash
   tail -n 200 storage/logs/crawler.log
   ```
3. Verificar cron/configuración:
   ```bash
   bash scripts/crawler-cron-check.sh
   ```
4. Revisar permisos de `storage`:
   ```bash
   ls -ld storage storage/logs storage/cache storage/sessions
   ```
5. Revisar `.env` local en VM sin exponer secretos (solo presencia/formato de claves).

## Si falla `deploy-update.sh`

1. Revisar estado del repositorio:
   ```bash
   git status --short
   ```
2. Revisar permisos de escritura/ejecución del proyecto y `storage`.
3. Reintentar dependencias:
   ```bash
   composer install --no-interaction --prefer-dist --no-progress
   ```
4. Revisar estado de migraciones (sin aplicar migraciones no aprobadas).
5. Ejecutar diagnóstico de aplicación:
   ```bash
   php bin/browser doctor
   php bin/browser auth:doctor
   ```

## Comandos prohibidos en producción sin revisión

- `git reset --hard`
- `git clean -fdx`
- Borrar `.env`
- Modificar Nginx desde Git
- Correr migraciones manuales no revisadas

## Qué sí se puede ejecutar normalmente

- Comandos de diagnóstico:
  - `php bin/browser doctor`
  - `php bin/browser auth:doctor`
  - `php bin/browser index:status`
  - `sudo -u www-data php bin/browser crawl:status`
- Verificación de cron:
  - `bash scripts/crawler-cron-check.sh`
- Despliegue manual post-merge:
  - `bash scripts/deploy-update.sh`
- Revisión de logs:
  - `tail -n 100 storage/logs/crawler.log`

## Escalamiento

Escalar a revisión humana inmediata cuando ocurra cualquiera de estos casos:

- Fallos repetidos de `doctor` / `auth:doctor`.
- Crawler detenido por más de un ciclo operativo.
- Errores de permisos persistentes en `storage`.
- Dudas sobre migraciones o posibles impactos en datos de producción.

## Paso opcional pre-deploy (GitHub Actions)

Antes de actualizar la VM, se puede ejecutar validación manual pre-deploy:

1. Ir a **GitHub -> Actions -> Manual pre-deploy validation**.
2. Click en **Run workflow**.
3. Completar:
   - `reason`: motivo corto de la ejecución.
   - `target_ref`: rama/ref a validar (por defecto `main`).
4. Esperar estado verde.

Si pasa, continuar con despliegue manual en VM:

```bash
cd /var/www/browser
bash scripts/deploy-update.sh
```

## Rollback manual

Ante deploy fallido o incidente post-actualización:

1. Ejecutar diagnóstico no destructivo:
   ```bash
   bash scripts/rollback-check.sh
   ```
2. Seguir el procedimiento documentado en:
   - `docs/ROLLBACK_VM.md`
3. Mantener aprobación humana para rollback en producción y cualquier acción sobre base de datos.
4. Priorizar revert de PR en GitHub + `bash scripts/deploy-update.sh` en VM como ruta recomendada.
