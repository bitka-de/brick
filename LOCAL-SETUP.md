# Brick Framework - Local Development Setup

## 🌐 Domain-Konfiguration für brick.test

### Option 1: Lokale Hosts-Datei (Einfach)
```bash
# /etc/hosts Datei editieren:
sudo nano /etc/hosts

# Diese Zeile hinzufügen:
127.0.0.1 brick.test
```

### Option 2: Apache Virtual Host
```apache
# /etc/apache2/sites-available/brick.test.conf
<VirtualHost *:80>
    ServerName brick.test
    DocumentRoot /Users/jp.behrens/Workspace/brick/public
    
    <Directory /Users/jp.behrens/Workspace/brick/public>
        AllowOverride All
        Require all granted
    </Directory>
    
    ErrorLog ${APACHE_LOG_DIR}/brick.test_error.log
    CustomLog ${APACHE_LOG_DIR}/brick.test_access.log combined
</VirtualHost>
```

```bash
# Site aktivieren:
sudo a2ensite brick.test.conf
sudo systemctl reload apache2
```

### Option 3: Nginx Configuration
```nginx
# /etc/nginx/sites-available/brick.test
server {
    listen 80;
    server_name brick.test;
    root /Users/jp.behrens/Workspace/brick/public;
    index index.php index.html;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.4-fpm.sock;
        fastcgi_index index.php;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    }
}
```

```bash
# Site aktivieren:
sudo ln -s /etc/nginx/sites-available/brick.test /etc/nginx/sites-enabled/
sudo systemctl reload nginx
```

### Option 4: PHP Development Server mit Custom Host
```bash
# Für schnelle Entwicklung:
php -S brick.test:8000 -t public

# Oder mit Port 80 (benötigt sudo):
sudo php -S brick.test:80 -t public
```

### Option 5: Docker Development Environment
```yaml
# docker-compose.yml
version: '3.8'
services:
  web:
    image: php:8.4-apache
    ports:
      - "80:80"
    volumes:
      - .:/var/www/html
    environment:
      - APACHE_DOCUMENT_ROOT=/var/www/html/public
```

### Option 6: Laravel Valet (macOS)
```bash
# Valet installieren:
composer global require laravel/valet
valet install

# Im Projekt-Verzeichnis:
cd /Users/jp.behrens/Workspace/brick
valet link brick
```
Dann läuft die App automatisch unter `http://brick.test`

## 🚀 Aktuelle Schnelle Lösung

### Hosts-Datei manuell editieren:
1. Terminal öffnen:
```bash
sudo nano /etc/hosts
```

2. Diese Zeile hinzufügen:
```
127.0.0.1 brick.test
```

3. Speichern und schließen (Ctrl+X, Y, Enter)

4. PHP Server auf Port 80 starten:
```bash
sudo php -S brick.test:80 -t public
```

### Oder ohne sudo auf anderem Port:
```bash
php -S brick.test:8000 -t public
```
Dann erreichbar unter: `http://brick.test:8000`

## ✅ Testen
Nach der Konfiguration sollte `http://brick.test` funktionieren!