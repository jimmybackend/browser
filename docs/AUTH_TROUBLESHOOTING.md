# AUTH Troubleshooting (Registro/Login/Sesión)

## HTTP vs HTTPS y cookies `Secure`

En `APP_ENV=production`, Browser protege la sesión con cookie `HttpOnly` y `SameSite=Lax`, y marca `Secure` **solo si PHP detecta HTTPS en la solicitud actual**.

La detección HTTPS se hace con:

- `$_SERVER['HTTPS']` distinto de vacío/off
- `$_SERVER['SERVER_PORT'] === 443`
- `$_SERVER['HTTP_X_FORWARDED_PROTO'] === https`

Si el usuario entra por `http://` o el proxy no informa HTTPS correctamente, la sesión no se comportará como en HTTPS y puede romper CSRF/login.

## Comportamiento en producción sin HTTPS detectado

Si `APP_ENV=production` y la solicitud llega sin HTTPS detectado, `public/index.php` redirige a `APP_URL` (si empieza con `https://`) preservando path y query string.

Esto evita flujos mezclados HTTP/HTTPS y ayuda a que la cookie de sesión persista correctamente en navegador.

## Diagnóstico seguro

Ejecuta:

```bash
php bin/browser auth:doctor
```

Muestra:

- `APP_ENV`
- `APP_URL`
- `SESSION_NAME`
- si PHP detecta HTTPS
- `session.save_path`
- si `session.save_path` es escribible
- cookie `secure/httponly/samesite`

No imprime contraseñas, tokens CSRF ni secretos.

## Pruebas con curl

### Verificar redirección a HTTPS

```bash
curl -I http://esforzados.com/login
```

Debe responder `302` hacia `https://esforzados.com/login` en producción.

### Verificar cookie de sesión en HTTPS

```bash
curl -k -i https://esforzados.com/login
```

Revisa `Set-Cookie` con:

- nombre configurado (`SESSION_NAME`, por ejemplo `BROWSER_SESSION`)
- `Secure`
- `HttpOnly`
- `SameSite=Lax`

## Verificación rápida en navegador

1. Abrir DevTools → Application/Storage → Cookies.
2. Entrar por URL `https://...` (no `http://...`).
3. Confirmar que la cookie de sesión existe y se actualiza tras login.
4. Revisar que el formulario incluye `_csrf_token` y que no desaparece por cambios de host/protocolo.

## Logs útiles en Ubuntu

```bash
sudo tail -f /var/log/nginx/access.log
sudo tail -f /var/log/nginx/error.log
sudo tail -f /var/log/php8.3-fpm.log
sudo journalctl -u php8.3-fpm -f
```

Si hay proxy/reverse proxy, validar que envía:

- `X-Forwarded-Proto: https`

Y que Nginx pasa esa cabecera a PHP-FPM (`fastcgi_param HTTP_X_FORWARDED_PROTO $scheme;`).
