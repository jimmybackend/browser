# Protección de rama `main` en GitHub

## Objetivo

Proteger `main` reduce riesgo operativo y de seguridad: evita pushes directos sin revisión, asegura que CI pase antes de integrar cambios y mantiene trazabilidad de decisiones técnicas.

Este repositorio ya trabaja por PR y validaciones en GitHub Actions, por lo que proteger `main` formaliza ese flujo.

## Principio de gobernanza

- Codex puede **crear ramas y pull requests**.
- Jimmy realiza el **merge manual** después de revisión humana.
- **No habilitar auto-merge todavía**.
- Producción y base de datos continúan con **aprobación humana explícita**.

## Reglas recomendadas para `main`

Configurar una branch rule/ruleset apuntando a `main` con:

1. **Require a pull request before merging** (obligatorio).
2. **Require status checks to pass before merging** (obligatorio).
3. Seleccionar como check requerido el workflow de **GitHub Actions CI** del repositorio.
4. **Require branches to be up to date before merging** (recomendado si se busca evitar merges con base desactualizada).
5. **Restrict force pushes** (bloquear force push en `main`).
6. **Restrict deletions** (bloquear borrado de `main`).
7. Mantener política de **no merge directo a `main`**.

## Pasos manuales en GitHub UI

1. Ir al repositorio `jimmybackend/browser`.
2. Abrir **Settings**.
3. Entrar en **Branches**.
4. Crear regla nueva con **Add branch ruleset** (o **Add rule** según la UI disponible).
5. Configurar el patrón/rama objetivo: `main`.
6. Activar las reglas listadas arriba.
7. Guardar cambios.
8. Probar con un PR de documentación para confirmar que:
   - no permite merge sin checks,
   - no permite push directo a `main`.

## Checklist previo a activación

Antes de activar protección estricta en `main`, confirmar:

- [ ] CI corre en GitHub Actions sin fallos inesperados.
- [ ] `composer install` funciona dentro de GitHub Actions.
- [ ] `composer test` ejecuta PHPUnit realmente (sin omisiones silenciosas).
- [ ] `main` está estable (sin incidentes abiertos bloqueantes).
- [ ] La VM puede actualizarse de forma controlada con:
  - `scripts/deploy-check.sh`
  - `scripts/deploy-after-pull.sh`

## Checklist posterior a activación

- [ ] Crear PR de prueba y verificar bloqueo de merge con checks en rojo.
- [ ] Verificar que un usuario sin bypass no pueda pushear directo a `main`.
- [ ] Confirmar que el equipo conoce el flujo: **rama → PR → revisión humana → merge manual**.
- [ ] Confirmar que **no** se activó auto-merge.

## Alcance y límites actuales

La protección de rama mejora calidad del flujo Git, pero **no reemplaza**:

- revisión humana de cambios sensibles,
- validación manual de despliegues,
- aprobación humana para cambios de producción y de base de datos.

Se mantiene explícitamente que todavía **no hay despliegue automático a producción**.
