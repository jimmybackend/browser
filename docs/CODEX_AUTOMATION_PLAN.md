# CODEX_AUTOMATION_PLAN

## Objetivo

Definir una ruta segura y gradual para automatizar tareas con Codex sin perder control humano en merge, producción y base de datos.

## Estado actual

**Estado actual estimado: entre Fase 3 y Fase 4.**

- PRs creados por Codex con revisión humana.
- Merge manual por Jimmy.
- Actualización en VM posterior al merge con `scripts/deploy-update.sh`.
- Producción y BD todavía bajo aprobación humana.

## Fases de automatización

### Fase 0: Manual total

- Desarrollo, validación, merge y despliegue 100% manual.
- Sin ayuda operativa de Codex.

### Fase 1: Codex crea PRs, Jimmy fusiona

- Codex implementa cambios y propone PR.
- Jimmy revisa y hace merge manual.
- Sin auto-merge.

### Fase 2: Branch protection + CI obligatorio

- Protección de `main` activa.
- CI obligatorio para fusionar.
- Reglas de revisión humana mantenidas.

### Fase 3: `deploy-update` manual después de merge

- Tras merge manual, despliegue manual en VM:
  - `bash scripts/deploy-update.sh`
- Verificación operativa post-deploy con comandos de diagnóstico.

### Fase 4: Semi-automatización con `workflow_dispatch`

- Automatización parcial por ejecución manual de workflow.
- Mantener gatillo humano explícito.
- No habilitar despliegue automático por push todavía.

### Fase 5: Auto-deploy limitado (condicionado)

- Solo considerar auto-deploy después de:
  - CI en verde,
  - merge humano,
  - condiciones de seguridad y rollback verificadas.
- Mantener aprobación humana para operaciones sensibles de BD.

## Condiciones para avanzar a Fase 4

Deben cumplirse todas:

- CI estable durante un periodo continuo.
- PHPUnit corriendo en GitHub Actions de forma consistente.
- Branch protection activa y validada.
- VM estable (sin incidentes operativos recurrentes).
- `deploy-update.sh` probado repetidamente con resultado confiable.

## Condiciones para avanzar a Fase 5

Deben cumplirse todas:

- Backups probados y restaurables.
- Rollback documentado paso a paso.
- Sin migraciones destructivas automáticas.
- Producción con aprobación humana para cambios de base de datos.

## Reglas no negociables (por ahora)

- Codex **no** debe hacer merge automático todavía.
- Codex puede proponer Issues/PRs, pero **no debe desplegar solo en producción**.
- Producción y base de datos siguen bajo aprobación humana.

## Riesgos de automatizar demasiado pronto

- Despliegues con fallos por validaciones incompletas o inestables.
- Riesgo de cambios de BD sin contexto operativo suficiente.
- Pérdida de trazabilidad en incidentes si no hay gates humanos.
- Mayor impacto ante errores de red/proxy/dependencias externas (Composer/Packagist).

## Recomendación operativa

Mantener el esquema actual (entre Fase 3 y Fase 4) hasta estabilizar CI, pruebas y rollback; luego avanzar en pasos pequeños, medibles y reversibles.

## Fase 4 inicial: validación manual pre-deploy (workflow_dispatch)

Se agrega el workflow `.github/workflows/manual-predeploy-validation.yml` como primer paso real de Fase 4.

- Es un workflow **manual** (GitHub UI -> Actions -> *Manual pre-deploy validation* -> *Run workflow*).
- Ejecuta validaciones técnicas antes de tocar la VM.
- **No hace deploy**, **no corre migraciones en producción**, **no usa secretos** y **no se conecta a la VM**.
- Jimmy mantiene el control: sigue revisando/mergeando PRs manualmente y sigue ejecutando manualmente `bash scripts/deploy-update.sh` en la VM.
