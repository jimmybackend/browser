# Prompt para Codex: terminar proyecto

Lee primero:

- `AGENTS.md`
- `docs/SPEC.md`
- `docs/TASKS.md`
- `docs/VALIDATION.md`
- `docs/SECURITY_CHECKLIST.md`

Objetivo:

Completar el proyecto hasta dejarlo funcional, validado y listo para revisión humana.

Modo de trabajo:

1. Analiza el repositorio.
2. Detecta lo que falta.
3. Divide el trabajo en fases pequeñas.
4. Implementa primero la fase más segura y necesaria.
5. Ejecuta validaciones.
6. Corrige errores encontrados.
7. Documenta todo.
8. Deja un PR claro.

Reglas obligatorias:

- No inventes tablas ni campos.
- No modifiques base de datos sin revisar estructura real.
- No subas secretos.
- No hagas merge automático.
- No cambies arquitectura sin justificar.
- No introduzcas dependencias innecesarias.
- No ocultes errores; documéntalos.
- Si algo no puede validarse, explica por qué.

Validación:

Ejecuta:

```bash
bash scripts/validate.sh
```

Si falla, corrige lo corregible y vuelve a ejecutar.

Entrega final:

- Qué estaba roto o incompleto.
- Qué se implementó.
- Archivos modificados.
- Pruebas ejecutadas.
- Resultado de las pruebas.
- Riesgos.
- Pendientes.
- Instrucciones para probar manualmente.
