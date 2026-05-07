# Paquete de automatización para Codex

Este paquete agrega una base de trabajo para que Codex pueda avanzar en el repositorio con más orden y menos intervención manual.

## Archivos incluidos

```text
AGENTS.md
docs/SPEC.md
docs/TASKS.md
docs/VALIDATION.md
docs/SECURITY_CHECKLIST.md
prompts/codex-diagnostico.md
prompts/codex-terminar-proyecto.md
prompts/codex-corregir-ci.md
scripts/validate.sh
.github/workflows/ci.yml
.github/ISSUE_TEMPLATE/feature.yml
.github/ISSUE_TEMPLATE/bug.yml
.github/pull_request_template.md
```

## Cómo instalarlo

Copia el contenido de esta carpeta en la raíz de tu repositorio.

Ejemplo:

```bash
cp -R codex_repo_automation_pack/* /ruta/de/tu/repositorio/
cp -R codex_repo_automation_pack/.github /ruta/de/tu/repositorio/
```

Luego confirma los cambios:

```bash
git add .
git commit -m "Add Codex automation baseline"
git push
```

## Cómo usarlo con Codex

Después de subir estos archivos al repositorio, abre Codex y usa este prompt:

```text
Lee AGENTS.md, docs/SPEC.md, docs/TASKS.md y docs/VALIDATION.md.
Analiza el repositorio completo.
Detecta qué falta para que la aplicación quede funcional.
Crea un plan por fases.
Implementa la primera fase segura y verificable.
Ejecuta las validaciones disponibles.
Entrega un resumen con archivos modificados, pruebas ejecutadas, errores, riesgos y pendientes.
No hagas merge automático a main.
```

También puedes usar los prompts completos dentro de la carpeta `prompts/`.

## Recomendación de seguridad

No actives merge automático ni despliegue automático a producción todavía.

Primero deja que Codex:

1. Analice.
2. Corrija.
3. Cree PR.
4. Pase CI.
5. Documente.

Después revisas tú y apruebas.
