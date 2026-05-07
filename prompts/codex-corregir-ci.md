# Prompt para Codex: corregir CI

Lee primero:

- `AGENTS.md`
- `docs/VALIDATION.md`

Objetivo:

Corregir los errores del workflow de GitHub Actions o de `scripts/validate.sh`.

Tareas:

1. Revisa el error del CI.
2. Identifica si el problema es del código o del workflow.
3. Corrige la causa raíz.
4. Ejecuta validación local si es posible.
5. Actualiza documentación si cambia la forma de probar.

Reglas:

- No desactives pruebas solo para que el CI pase.
- No ignores errores reales.
- No elimines validaciones de seguridad sin justificar.
- Si una herramienta no existe en el proyecto, ajusta el workflow para saltarla de forma clara y documentada.

Entrega:

- Error encontrado.
- Causa.
- Corrección aplicada.
- Validación ejecutada.
- Resultado.
