# Checklist de seguridad

## Secretos

- [ ] No existe archivo `.env` real en el repositorio.
- [ ] No hay claves AWS.
- [ ] No hay tokens de API.
- [ ] No hay llaves privadas.
- [ ] No hay dumps SQL privados.
- [ ] No hay backups con datos reales.

## PHP

- [ ] No se muestran errores internos al usuario final.
- [ ] No hay `var_dump`, `print_r` o `die` de depuración en producción.
- [ ] Las contraseñas usan `password_hash`.
- [ ] La validación de contraseña usa `password_verify`.
- [ ] Las sesiones se manejan de forma segura.

## SQL

- [ ] Todas las consultas con datos de usuario usan prepared statements.
- [ ] No hay concatenación directa de `$_GET`, `$_POST`, `$_REQUEST` o cookies en SQL.
- [ ] Los errores SQL no se muestran al usuario final.

## Formularios

- [ ] Se validan campos requeridos.
- [ ] Se validan tipos de datos.
- [ ] Se escapan salidas HTML.
- [ ] Los formularios sensibles tienen protección CSRF si aplica.

## Accesos

- [ ] Las rutas privadas validan sesión.
- [ ] Las acciones administrativas validan rol/permiso.
- [ ] Logout destruye sesión correctamente.
- [ ] No hay rutas administrativas públicas.

## AWS / Servidor

- [ ] No hay credenciales AWS en código.
- [ ] No hay `.pem` o `.key`.
- [ ] Los permisos de archivos están documentados.
- [ ] El despliegue no depende de archivos locales secretos no documentados.
