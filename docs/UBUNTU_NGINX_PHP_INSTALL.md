# Browser: instalación en Ubuntu sin Docker (Nginx + PHP-FPM + MySQL remoto)

Esta guía prepara **Browser** para producción en Ubuntu usando Nginx, PHP-FPM y una base MySQL remota.

## 1) Actualizar sistema

```bash
sudo apt update
sudo apt upgrade -y
```

## 2) Instalar Nginx, PHP y extensiones

> Ajusta la versión de PHP según tu Ubuntu (ejemplo con PHP 8.3).

```bash
sudo apt install -y nginx
sudo apt install -y php8.3-fpm php8.3-cli php8.3-mysql php8.3-mbstring php8.3-xml php8.3-curl php8.3-zip php8.3-intl
```

Verificar módulos:

```bash
php -m | grep -E 'pdo_mysql|mbstring|xml|curl|zip|intl'
```

## 3) Instalar Composer

```bash
sudo apt install -y composer
composer --version
```

## 4) Clonar Browser e instalar dependencias

```bash
git clone https://github.com/jimmybackend/browser.git
cd browser
composer install --no-dev --optimize-autoloader
```

## 5) Crear `.env` para servidor real

```bash
cp .env.example .env
```

Edita `.env` y define valores reales de servidor:

- `APP_ENV=production`
- `APP_DEBUG=false`
- `APP_URL=https://tu-dominio`
- `DB_HOST=host-remoto-mysql`
- `DB_PORT=3306`
- `DB_DATABASE=nombre_base`
- `DB_USERNAME=usuario_base`
- `DB_PASSWORD=password_seguro`

> No publiques ni compartas este archivo.

## 6) Probar conectividad a MySQL remoto

```bash
php bin/browser doctor
```

## 7) Ejecutar migraciones y seeders

```bash
php bin/browser migrate
php bin/browser seed
```

## 8) Crear o promover admin inicial

```bash
php bin/browser admin:create
```

Si el correo ya existe, lo promueve a admin. Si no existe, pedirá username/email/password.

## 9) Configurar Nginx apuntando a `/public`

Archivo ejemplo: `/etc/nginx/sites-available/browser`

```nginx
server {
    listen 80;
    server_name tu-dominio.com www.tu-dominio.com;

    root /var/www/browser/public;
    index index.php index.html;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

Activar sitio:

```bash
sudo ln -s /etc/nginx/sites-available/browser /etc/nginx/sites-enabled/browser
sudo nginx -t
```

## 10) Permisos de `storage`

```bash
sudo mkdir -p /var/www/browser/storage
sudo chown -R www-data:www-data /var/www/browser/storage
sudo chmod -R 775 /var/www/browser/storage
```

## 11) Recargar servicios

```bash
sudo systemctl reload nginx
sudo systemctl reload php8.3-fpm
```

## 12) Checklist final

```bash
php bin/browser doctor
php bin/browser migrate
php bin/browser seed
php bin/browser admin:create
```

Con eso, Browser queda listo para correr sin Docker en Ubuntu.
