# Panduan Hosting Hello Store di Orange Cloud VPS

> Panduan ini berlaku untuk VPS Ubuntu 22.04/24.04 LTS.

---

## Daftar Isi

1. [Prasyarat](#1-prasyarat)
2. [Beli & Setup VPS](#2-beli--setup-vps)
3. [Install LEMP Stack](#3-install-lemp-stack)
4. [Setup Database MySQL](#4-setup-database-mysql)
5. [Clone Project Hello Store](#5-clone-project-hello-store)
6. [Konfigurasi .env](#6-konfigurasi-env)
7. [Install Composer Dependencies](#7-install-composer-dependencies)
8. [Build Frontend & Migrate](#8-build-frontend--migrate)
9. [Konfigurasi Nginx](#9-konfigurasi-nginx)
10. [Setup Domain & SSL](#10-setup-domain--ssl)
11. [Storage Link & Permission](#11-storage-link--permission)
12. [Setup Queue & Scheduler](#12-setup-queue--scheduler)
13. [Cara Update Website](#13-cara-update-website)
14. [Tips & Troubleshooting](#14-tips--troubleshooting)

---

## 1. Prasyarat

Sebelum mulai, siapin dulu:

| Barang | Keterangan |
|---|---|
| **VPS** | Minimal 2 GB RAM, 50 GB NVMe, Ubuntu 22.04/24.04 LTS |
| **Domain** | Contoh: `hello-store.com` |
| **Akun GitHub** | Untuk push code dari lokal ke server |
| **Tools** | Terminal (macOS/Linux) atau **PuTTY** (Windows) |

---

## 2. Beli & Setup VPS

1. **Login** ke dashboard Orange Cloud → bagian **VPS / Cloud Server**
2. **Pilih paket** — minimal:
   - RAM: 2 GB
   - CPU: 1-2 Core
   - Storage: 50 GB NVMe
   - OS: **Ubuntu 22.04 LTS** atau **24.04 LTS**
3. **Konfirmasi order** — tunggu VPS aktif (biasanya 1-5 menit)
4. **Cek data login** — dapat email berisi:
   - IP VPS: `xxx.xxx.xxx.xxx`
   - Username: `root`
   - Password: `xxxxxxxx`

---

## 3. Install LEMP Stack

### 3a. SSH ke VPS

**Windows (pakai PuTTY):**
- Download & buka [PuTTY](https://www.putty.org/)
- Isi `Host Name` dengan IP VPS
- Klik `Open` → login sebagai `root` → masukkan password

**macOS / Linux (terminal):**
```bash
ssh root@IP_VPS_ANDA
```

### 3b. Update System

```bash
sudo apt update && sudo apt upgrade -y
```

### 3c. Install Nginx

```bash
sudo apt install nginx -y
```

Cek: buka browser `http://IP_VPS_ANDA` — harusnya muncul halaman welcome Nginx.

### 3d. Install PHP 8.3

```bash
sudo apt install -y php8.3 php8.3-cli php8.3-common php8.3-mbstring \
    php8.3-xml php8.3-bcmath php8.3-curl php8.3-mysql php8.3-zip \
    php8.3-tokenizer php8.3-fileinfo php8.3-fpm php8.3-gd php8.3-intl
```

Cek versi:
```bash
php -v
```

### 3e. Install Composer

```bash
cd /tmp
php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
php composer-setup.php --install-dir=/usr/local/bin --filename=composer
php -r "unlink('composer-setup.php');"
```

Cek:
```bash
composer --version
```

### 3f. Install Git & Redis

```bash
sudo apt install -y git redis-server supervisor
sudo systemctl enable redis-server
sudo systemctl start redis-server
```

### 3g. Install Node.js & NPM

```bash
curl -fsSL https://deb.nodesource.com/setup_22.x | sudo -E bash -
sudo apt install -y nodejs
```

Cek:
```bash
node -v
npm -v
```

---

## 4. Setup Database MySQL

### Install MySQL 8

```bash
sudo apt install -y mysql-server
sudo mysql_secure_installation
```

Ikuti prompt:
- `VALIDATE PASSWORD COMPONENT` → **N** (supaya gak ribet)
- `Remove anonymous users?` → **Y**
- `Disallow root login remotely?` → **Y**
- `Remove test database and access to it?` → **Y**
- `Reload privilege tables now?` → **Y**

### Buat Database & User

```bash
sudo mysql
```

Di dalam MySQL shell, jalankan:
```sql
CREATE DATABASE hello_store_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'hello_store'@'localhost' IDENTIFIED BY 'BuatPasswordKuat123!';
GRANT ALL PRIVILEGES ON hello_store_db.* TO 'hello_store'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

> **Catatan:** Ganti `BuatPasswordKuat123!` dengan password yang kuat. Catat password-nya karena akan dipakai di `.env`.

---

## 5. Clone Project Hello Store

### Setup Git & SSH Key

Biar gampang update, pake SSH key biar bisa `git pull` tanpa password:

```bash
ssh-keygen -t ed25519 -C "email@anda.com"
cat ~/.ssh/id_ed25519.pub
```

Copy output-nya, lalu tambahkan ke **GitHub → Settings → SSH and GPG keys → New SSH key**.

### Clone Repository

```bash
cd /var/www
git clone git@github.com:USERNAME_GITHUB_ANDA/hello-store.git
cd hello-store
```

---

## 6. Konfigurasi .env

### Copy File Environment

```bash
cp .env.example .env
```

### Generate App Key

```bash
php artisan key:generate
```

### Edit .env

```bash
nano .env
```

Sesuaikan isinya:

```ini
APP_NAME="Hello Store"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://hello-store.com           # Ganti dengan domain kamu

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=hello_store_db
DB_USERNAME=hello_store
DB_PASSWORD=BuatPasswordKuat123!          # Password yang tadi dibuat

# Redis (wajib untuk queue)
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379

# Queue
QUEUE_CONNECTION=redis

# Session
SESSION_DRIVER=redis
SESSION_LIFETIME=120

# Cache
CACHE_STORE=redis

# Mail (untuk reset password, dll)
MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=emailkamu@gmail.com
MAIL_PASSWORD=password_app_gmail         # Pakai App Password, bukan password biasa
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=emailkamu@gmail.com
MAIL_FROM_NAME="${APP_NAME}"
```

> **Catatan MAIL_PASSWORD:** Untuk Gmail, jangan pake password akun — buat **App Password** di: Google Account → Security → 2-Step Verification → App Passwords.

> **Catatan APP_URL:** Pastikan pake `https://` karena nanti pake SSL.

Simpan: `Ctrl+X` → `Y` → `Enter`

---

## 7. Install Composer Dependencies

```bash
composer install --no-dev --optimize-autoloader
```

Kalau ada error, biasanya butuh extension yang kurang. Install dulu:
```bash
sudo apt install -y php8.3-gd php8.3-intl
```

---

## 8. Build Frontend & Migrate

### Install NPM & Build

```bash
npm install
npm run build
```

### Cache Konfigurasi

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

### Run Migration & Seeder

```bash
php artisan migrate --seed
```

### Setup Storage Symlink

```bash
php artisan storage:link
```

### Cek App Key

```bash
php artisan key:generate --force
```

---

## 9. Konfigurasi Nginx

### Hapus Default Site

```bash
rm /etc/nginx/sites-enabled/default
```

### Buat Config Baru

```bash
nano /etc/nginx/sites-available/hello-store
```

Isi dengan:

```nginx
server {
    listen 80;
    server_name hello-store.com www.hello-store.com;  # Ganti domain kamu
    root /var/www/hello-store/public;

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";

    index index.php index.html;

    charset utf-8;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    error_page 404 /index.php;

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }

    # Cache static files
    location ~* \.(jpg|jpeg|png|gif|ico|css|js|webp|svg|woff|woff2|ttf|eot)$ {
        expires 30d;
        add_header Cache-Control "public, immutable";
    }

    client_max_body_size 20M;
}
```

### Aktifkan Site

```bash
ln -s /etc/nginx/sites-available/hello-store /etc/nginx/sites-enabled/
nginx -t          # Test config, harusnya "test is successful"
systemctl restart nginx
```

### Buka Firewall (kalau ada)

```bash
sudo ufw allow 'Nginx Full'
sudo ufw enable
```

### Cek Akses

Buka browser: `http://IP_VPS_ANDA` — Hello Store sudah jalan!

---

## 10. Setup Domain & SSL

### 10a. Arahkan Domain

Di **panel domain** kamu, buat 2 DNS record:

| Tipe | Name | Value |
|---|---|---|
| **A** | `@` | `IP_VPS_ANDA` |
| **A** | `www` | `IP_VPS_ANDA` |

Tunggu propagasi DNS (5 menit - 24 jam, biasanya cepet).

### 10b. Update APP_URL di .env

```bash
nano /var/www/hello-store/.env
```

Ganti `APP_URL` jadi domain asli:
```
APP_URL=https://hello-store.com
```

Lalu:
```bash
php artisan config:cache
```

### 10c. Install SSL via Certbot (Let's Encrypt)

```bash
sudo apt install -y certbot python3-certbot-nginx
sudo certbot --nginx -d hello-store.com -d www.hello-store.com
```

Ikuti prompt:
- Masukkan email untuk notifikasi
- `Agree to Terms` → **Y**
- `Share email with EFF` → **N**
- Pilih `2: Redirect` — otomatis redirect HTTP → HTTPS

### 10d. Auto Renewal

Cek apakah cron certbot sudah jalan:
```bash
systemctl status certbot.timer
```

Tes renew (dry run):
```bash
sudo certbot renew --dry-run
```

✅ Sekarang domain kamu sudah HTTPS!

---

## 11. Storage Link & Permission

```bash
cd /var/www/hello-store

# Storage symlink
php artisan storage:link

# Permission
chown -R www-data:www-data storage bootstrap/cache public/storage
chmod -R 775 storage bootstrap/cache public/storage
```

---

## 12. Setup Queue & Scheduler

### 12a. Queue Worker (Supervisor)

Buat config supervisor:

```bash
nano /etc/supervisor/conf.d/hello-store-worker.conf
```

Isi:
```ini
[program:hello-store-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/hello-store/artisan queue:work redis --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/var/www/hello-store/storage/logs/worker.log
stopwaitsecs=3600
```

Jalankan:
```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start all
```

Cek status:
```bash
sudo supervisorctl status
```
Harusnya keliatan `hello-store-worker:hello-store-worker_00 RUNNING` dan `_01 RUNNING`.

### 12b. Cron Scheduler

Buat cron job buat `php artisan schedule:run`:

```bash
crontab -u www-data -e
```

Pilih `1` (nano) kalau pertama kali, lalu tambahin baris ini:
```cron
* * * * * cd /var/www/hello-store && php artisan schedule:run >> /dev/null 2>&1
```

Simpan: `Ctrl+X` → `Y` → `Enter`

Test cron:
```bash
php artisan schedule:run
```

---

## 13. Cara Update Website

Setiap kali ada perubahan code di lokal, lakukan:

### 13a. Update Code & Dependencies

```bash
cd /var/www/hello-store

# Matiin queue worker dulu
sudo supervisorctl stop all

# Pull code terbaru
git pull origin main

# Update PHP dependencies (kalo ada yang berubah)
composer install --no-dev --optimize-autoloader

# Update frontend (kalo ada yang berubah)
npm install
npm run build

# Jalanin migration (kalo ada yang berubah)
php artisan migrate --force

# Clear & refresh cache
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Restart queue worker
sudo supervisorctl start all

# Fix permission
chown -R www-data:www-data storage bootstrap/cache public/storage
chmod -R 775 storage bootstrap/cache public/storage
```

### 13b. Deploy Script (Auto)

Biar gak repot ngetik manual tiap update, bikin script:

```bash
nano /var/www/hello-store/deploy.sh
```

Isi:
```bash
#!/bin/bash
set -e

cd /var/www/hello-store

echo "=== Mematikan queue worker ==="
sudo supervisorctl stop all

echo "=== Pull code terbaru ==="
git pull origin main

echo "=== Update Composer ==="
composer install --no-dev --optimize-autoloader

echo "=== Build Frontend ==="
npm install
npm run build

echo "=== Migration ==="
php artisan migrate --force

echo "=== Cache ==="
php artisan config:cache
php artisan route:cache
php artisan view:cache

echo "=== Permission ==="
chown -R www-data:www-data storage bootstrap/cache public/storage
chmod -R 775 storage/bootstrap/cache public/storage

echo "=== Queue worker dihidupkan ==="
sudo supervisorctl start all

echo "=== DEPLOY SELESAI ==="
```

Jadikan executable:
```bash
chmod +x /var/www/hello-store/deploy.sh
```

Update tinggal jalanin:
```bash
cd /var/www/hello-store && bash deploy.sh
```

---

## 14. Tips & Troubleshooting

### Error 500

Cek log:
```bash
tail -f /var/www/hello-store/storage/logs/laravel.log
```

Sementara aktifkan debug:
```bash
# Di .env, ubah:
APP_DEBUG=true
```
Setelah tau errornya, balikin ke `false`.

### Error "Permission denied"

```bash
chown -R www-data:www-data /var/www/hello-store/storage
chown -R www-data:www-data /var/www/hello-store/bootstrap/cache
chmod -R 775 /var/www/hello-store/storage
chmod -R 775 /var/www/hello-store/bootstrap/cache
```

### Queue Worker Mati

Cek supervisor:
```bash
sudo supervisorctl status
sudo supervisorctl restart all
```

Cek log worker:
```bash
tail -f /var/www/hello-store/storage/logs/worker.log
```

### Migration Error "Access denied"

Cek kredensial database di `.env`:
```bash
mysql -u hello_store -p -e "SELECT 1"
```
Kalau gagal, reset password:
```bash
sudo mysql
ALTER USER 'hello_store'@'localhost' IDENTIFIED BY 'PasswordBaru';
FLUSH PRIVILEGES;
EXIT;
```

### Nginx 502 Bad Gateway

PHP-FPM mungkin mati:
```bash
sudo systemctl restart php8.3-fpm
```

### Reset Database dari Awal

```bash
cd /var/www/hello-store
php artisan migrate:fresh --seed
php artisan storage:link
```

### Backup Database (Rutin)

```bash
# Backup
mysqldump -u hello_store -p hello_store_db > /root/backup/hello-store-$(date +%Y%m%d).sql

# Restore
mysql -u hello_store -p hello_store_db < /root/backup/hello-store-20260630.sql
```

Biar otomatis, tambahin cron:
```bash
crontab -e
```
```cron
0 3 * * * mysqldump -u hello_store -p'Password' hello_store_db > /root/backup/hello-store-$(date +\%Y\%m\%d).sql
```

---

### File yang Harus Diupload dari Lokal Server

| File/Folder | Sumber | Keterangan |
|---|---|---|
| Seluruh project | GitHub | Push ke GitHub dulu, clone di server |
| `vendor/` | Composer | `composer install` di server (JANGAN upload) |
| `.env` | Buat baru | Beda isi dengan lokal! Sesuaikan DB, APP_URL, dll |
| `node_modules/` | NPM | `npm install && npm run build` di server (JANGAN upload) |
| `public/storage` | Artisan | `php artisan storage:link` di server |
| Gambar produk | Upload via admin | Auto tersimpan di `storage/app/public/products/` |

---

### Daftar Perintah Cepat

```bash
# Deploy update
cd /var/www/hello-store && bash deploy.sh

# Restart semua service
sudo systemctl restart nginx php8.3-fpm
sudo supervisorctl restart all

# Cek disk usage
df -h

# Cek RAM
free -h

# Cek log error
tail -f /var/www/hello-store/storage/logs/laravel.log

# Cek log Nginx
tail -f /var/log/nginx/hello-store-error.log

# Update code cepat (tanpa deploy script)
cd /var/www/hello-store && git pull
```

---

### Server Spec Minimum

| Komponen | Minimum | Recommended |
|---|---|---|
| RAM | 2 GB | 4 GB |
| CPU | 1 Core | 2 Core |
| Storage | 50 GB NVMe | 100 GB NVMe |
| Bandwidth | 1 TB | Unlimited |
| OS | Ubuntu 22.04 LTS | Ubuntu 24.04 LTS |

---

**Happy hosting bro!** Kalau ada kendala, tinggal tanya aja.
