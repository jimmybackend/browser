## Resumen del cambio

- Describe qué cambia y por qué.

## Archivos modificados

- Lista de archivos y breve motivo por archivo.

## Tipo de cambio

Marca lo que aplique:

- [ ] docs
- [ ] test
- [ ] fix
- [ ] feat
- [ ] chore

## Checklist de seguridad y alcance

- [ ] No se tocó `.env` ni se agregó ningún archivo de entorno sensible.
- [ ] No se subieron credenciales, tokens, llaves privadas ni secretos.
- [ ] No se modificó configuración de Nginx.
- [ ] No se modificó código funcional de la aplicación (si aplica para PR documental).
- [ ] No se hizo merge automático.

## Base de datos / SQL

- [ ] No se tocaron migraciones ni estructura de base de datos.
- [ ] Se tocó base de datos o migraciones (completar sección siguiente).

Si se tocó BD, detallar obligatoriamente:

- Tablas reales revisadas:
- Campos reales revisados:
- SQL real revisado (ruta exacta, por ejemplo `database/migrations/*.sql` o dump real autorizado):
- Impacto esperado:
- Plan de rollback:

## Pruebas ejecutadas

Incluye comando exacto y resultado.

- `bash scripts/validate.sh`
- Otras pruebas/manual checks:

## Limitaciones reales del entorno

Documenta bloqueos reales (por ejemplo Packagist/proxy/red) con error literal y causa probable.

## Riesgos detectados

- Riesgo 1:
- Riesgo 2:

## Pasos de validación manual

1.
2.
3.

## Confirmaciones finales

- [ ] Esta tarea mantiene aprobación humana para cambios de producción.
- [ ] Esta tarea mantiene aprobación humana para cambios de base de datos.
- [ ] El PR está listo para revisión y merge manual por Jimmy.
