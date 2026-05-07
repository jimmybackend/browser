# Prompt para Codex: diagnóstico inicial

Lee primero:

- `AGENTS.md`
- `docs/SPEC.md`
- `docs/TASKS.md`
- `docs/VALIDATION.md`

Después analiza el repositorio completo.

Objetivo:

Detectar el estado real del proyecto y crear un diagnóstico técnico sin hacer cambios destructivos.

Tareas:

1. Identifica el stack real del proyecto.
2. Identifica el punto de entrada de la aplicación.
3. Identifica si usa Composer.
4. Identifica si hay pruebas.
5. Identifica la estructura de base de datos disponible.
6. Identifica archivos sensibles o riesgos.
7. Identifica errores evidentes.
8. Identifica qué falta para que el proyecto quede funcional.
9. Propón un plan por fases.
10. Ejecuta validaciones disponibles si es seguro.

Reglas:

- No inventes tablas ni campos.
- No subas secretos.
- No hagas merge a `main`.
- Si falta información, documenta el bloqueo.
- Si haces cambios, que sean mínimos y justificados.

Entrega:

- Resumen del estado actual.
- Lista de problemas detectados.
- Riesgos de seguridad.
- Plan por fases.
- Validaciones ejecutadas.
- Próxima tarea recomendada.
