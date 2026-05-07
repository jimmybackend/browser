# AGENTS.md

## Propósito

Este repositorio debe ser completado con ayuda de Codex de forma ordenada, segura y verificable.

El objetivo es que Codex pueda analizar el proyecto, detectar pendientes, implementar funcionalidades, corregir errores, ejecutar validaciones y dejar pull requests listos para revisión humana.

## Reglas obligatorias

- No inventar tablas, campos, rutas, variables de entorno ni estructuras que no existan.
- Antes de modificar código, revisar la estructura real del proyecto.
- Antes de modificar SQL, revisar modelos, migraciones, dumps, documentación o queries existentes.
- No eliminar código funcional sin explicar por qué.
- No subir credenciales, tokens, llaves privadas, archivos `.env`, dumps privados ni backups sensibles.
- No hacer cambios destructivos en base de datos sin documentarlos.
- No hacer merge automático a `main`.
- No preparar despliegues a producción sin revisión humana.

## Estilo de trabajo

Codex debe trabajar por pasos:

1. Leer este archivo.
2. Leer `docs/SPEC.md`.
3. Leer `docs/TASKS.md`.
4. Leer `docs/VALIDATION.md`.
5. Revisar la estructura del repositorio.
6. Identificar el stack real del proyecto.
7. Detectar pendientes y riesgos.
8. Implementar cambios mínimos, seguros y verificables.
9. Ejecutar validaciones.
10. Documentar lo hecho.

## PHP / Backend

- Usar PDO para acceso a base de datos.
- Usar consultas preparadas.
- No concatenar directamente datos del usuario en SQL.
- Validar entradas del usuario.
- Escapar salidas HTML cuando aplique.
- Usar `password_hash()` para guardar contraseñas.
- Usar `password_verify()` para validar contraseñas.
- Manejar errores sin exponer detalles sensibles al usuario final.
- Mantener compatibilidad con la versión de PHP detectada en el proyecto.

## MySQL / Base de datos

- No asumir nombres de tablas ni campos.
- Revisar primero el esquema real disponible.
- Si falta información de base de datos, documentar el bloqueo.
- Si se propone una migración, explicar:
  - tabla afectada
  - columnas afectadas
  - impacto esperado
  - forma de revertir

## Seguridad

Revisar especialmente:

- SQL injection
- XSS
- CSRF en formularios sensibles
- sesiones
- login/logout
- recuperación de contraseña
- autorización por rol o permiso
- exposición de errores
- archivos sensibles en el repositorio
- rutas públicas que deberían ser privadas
- permisos de archivos
- configuración de producción

## Frontend

- Mantener la estructura visual existente.
- No introducir frameworks nuevos salvo que el proyecto ya los use.
- Validar formularios del lado cliente solo como apoyo; la validación real debe estar en backend.
- Evitar romper rutas o assets existentes.

## AWS / Producción

- No escribir claves AWS en el código.
- No subir archivos `.pem`, `.key`, `.env`, credenciales ni perfiles locales.
- No modificar scripts de despliegue sin explicar impacto.
- Separar configuración local, staging y producción.
- Documentar variables de entorno necesarias.

## Validaciones recomendadas

Ejecutar lo que aplique:

```bash
bash scripts/validate.sh
```

Si el script no aplica, ejecutar manualmente:

```bash
find . -name "*.php" -not -path "./vendor/*" -print0 | xargs -0 -n1 php -l
composer validate --no-check-publish
composer install --no-interaction --prefer-dist --no-progress
vendor/bin/phpunit
vendor/bin/phpstan analyse
```

Si algún comando falla porque no existe herramienta o configuración, reportar claramente:

- comando ejecutado
- error recibido
- causa probable
- alternativa usada para validar

## Entrega esperada en cada PR

Cada pull request debe incluir:

- resumen de cambios
- archivos modificados
- pruebas ejecutadas
- resultado de las pruebas
- riesgos detectados
- pendientes
- pasos para probar manualmente
- confirmación de que no se subieron secretos
