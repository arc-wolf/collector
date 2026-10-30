# Collector - LAN Student Submission System (LAMP)

Apache2 + PHP 8.1+ + MariaDB application for closed-network file submissions.

## 1) Install packages (Ubuntu 22.04)

```bash
sudo apt update
sudo apt install -y apache2 mariadb-server php php-cli php-mysql php-zip php-mbstring php-intl
```

## 2) Create directories outside webroot

```bash
sudo mkdir -p /srv/collector/uploads /srv/collector/tmp /etc/collector
sudo chown -R www-data:www-data /srv/collector/uploads /srv/collector/tmp
sudo chmod 750 /srv/collector/uploads /srv/collector/tmp
sudo mkdir -p /var/log/collector
sudo touch /var/log/collector/app.log
sudo chown -R www-data:www-data /var/log/collector
sudo chmod 640 /var/log/collector/app.log
```

## 3) Configure app

1. Copy `config.example.php` to `/etc/collector/config.php`.
2. Set a unique MariaDB password and update `DB_DSN`, `DB_USER`, and `DB_PASS`.
3. Set the allowed client LAN CIDRs.
4. Set `upload_dir` and `temp_dir` to absolute paths outside webroot.
5. Keep the configuration file readable only by the deployment administrator and the Apache account that needs it.

Apache will read config via environment variable:

```apache
SetEnv APP_CONFIG /etc/collector/config.php
```

## 4) Database and first administrator

```bash
mysql -u root -p -e "CREATE DATABASE collector CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql -u root -p collector < db/schema.sql
```

Create the initial administrator with a unique password. Do not put the password in shell history; enter it through a protected environment mechanism or secret manager:

```bash
export APP_CONFIG=/etc/collector/config.php
export COLLECTOR_ADMIN_USERNAME=admin
read -rsp "New Collector admin password: " COLLECTOR_ADMIN_PASSWORD
echo
export COLLECTOR_ADMIN_PASSWORD
php bin/create_admin.php
unset COLLECTOR_ADMIN_PASSWORD
```

The command requires at least 14 characters, stores a PHP `password_hash()` result, and refuses to overwrite an existing account. There is no default administrator password and `db/seeds.sql` intentionally does not create one.

## 5) Apache vhost example

```apache
<VirtualHost *:80>
    ServerName collector.local
    DocumentRoot /var/www/collector/public

    <Directory /var/www/collector/public>
        AllowOverride All
        Require all granted
    </Directory>

    SetEnv APP_CONFIG /etc/collector/config.php

    ErrorLog ${APACHE_LOG_DIR}/collector_error.log
    CustomLog ${APACHE_LOG_DIR}/collector_access.log combined
</VirtualHost>
```

Enable and reload:

```bash
sudo a2enmod rewrite
sudo a2ensite collector.conf
sudo systemctl reload apache2
```

## 6) Permissions

```bash
sudo chown -R www-data:www-data /var/www/collector
sudo chmod -R 750 /var/www/collector
sudo chmod 640 /var/www/collector/logs/app.log
```

## 7) php.ini (recommended)

Set these in `/etc/php/8.1/apache2/php.ini` (or your installed PHP version):

```ini
file_uploads = On
upload_max_filesize = 20M
post_max_size = 22M
max_file_uploads = 5
session.cookie_httponly = 1
session.cookie_samesite = Lax
expose_php = Off
```

Restart Apache:

```bash
sudo systemctl restart apache2
```

## 8) CLI ZIP generation

```bash
APP_CONFIG=/etc/collector/config.php php bin/generate_zip.php --from=2026-01-01 --to=2026-01-31
APP_CONFIG=/etc/collector/config.php php bin/generate_zip.php --ip=192.168.1.20 --output=/srv/collector/tmp/custom.zip
```

## Security notes

- Keep `config.php`, uploads, and ZIP temp folders outside webroot.
- `X-Forwarded-For` is disabled by default. Only enable when a trusted reverse proxy is explicitly configured.
- Enable HTTPS on LAN (self-signed/internal CA) for credential protection.
- The login regenerates the PHP session ID after successful authentication.
- Optional malware scan hook is marked in `public/submit.php` for ClamAV integration.
