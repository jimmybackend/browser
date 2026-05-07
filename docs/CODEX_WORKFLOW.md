# CODEX_WORKFLOW

## Principio operativo

Este repositorio sigue un flujo estricto:

- **1 tarea = 1 chat nuevo = 1 PR = 1 merge manual**.
- No reutilizar ramas.
- No actualizar PRs viejos para una tarea nueva.
- No hacer merge automático.
- Jimmy revisa y fusiona manualmente.

Codex puede proponer e implementar cambios, pero **producción y base de datos requieren aprobación humana explícita**.

## Qué plantilla usar

- **Bug de comportamiento actual** → `.github/ISSUE_TEMPLATE/bug_report.yml`
- **Nueva funcionalidad o mejora** → `.github/ISSUE_TEMPLATE/feature_request.yml`
- **Tarea operativa para delegar a Codex** → `.github/ISSUE_TEMPLATE/codex_task.yml`
- **Tarea sensible de base de datos** → `.github/ISSUE_TEMPLATE/database_sensitive_task.yml`

## Qué debe incluir una buena tarea

Como mínimo:

- objetivo claro y contexto de negocio/técnico,
- alcance y fuera de alcance,
- archivos/rutas permitidas y prohibidas,
- reglas de seguridad (sin secretos, sin `.env`, sin cambios no autorizados en Nginx/BD),
- validaciones esperadas (`bash scripts/validate.sh` y pruebas manuales),
- criterios de aceptación verificables.

## Si CI falla

1. Revisar logs del workflow (`.github/workflows/ci.yml`).
2. Ejecutar localmente `bash scripts/validate.sh`.
3. Si el fallo depende del entorno (red/proxy/herramientas), documentar:
   - comando ejecutado,
   - error exacto,
   - causa probable,
   - mitigación propuesta.
4. No marcar la tarea como "completa" sin evidencia de validación.

## Si `composer install` falla por red/proxy

Si aparece error de Packagist (por ejemplo `curl error 56` o `CONNECT tunnel failed, response 403`):

1. Documentar el bloqueo tal cual en el PR.
2. Confirmar que el fallo es de conectividad del entorno y no del código.
3. Reintentar en un entorno con salida de red válida o mirror/proxy corporativo correcto.
4. Ejecutar nuevamente validaciones cuando haya conectividad.

## Si la tarea involucra base de datos

1. Revisar SQL real (`database/migrations/*.sql` y/o dump autorizado).
2. No inventar tablas ni campos.
3. Documentar impacto, riesgos y plan de rollback.
4. Mantener aprobación humana antes de aplicar en producción.

## Si la tarea involucra VM, Nginx o `.env`

- La VM de referencia usa `/var/www/browser`.
- `.env` real existe en VM y **no debe rastrearse ni subirse**.
- No modificar Nginx ni configuración de producción sin aprobación humana explícita.
- Si se requiere un cambio operativo, abrir issue/PR específico con revisión manual.

## Referencias

- `docs/DEPLOYMENT_VM.md`
- `docs/BRANCH_PROTECTION.md`
