# Cara Bikin Server Pribadi untuk Hosting Hello Store

> Panduan lengkap buat pemula — dari milih komputer rumahan sampai Hello Store bisa diakses dari luar.

---

## Daftar Isi

1. [Server Pribadi vs VPS — Apa Bedanya?](#1-server-pribadi-vs-vps--apa-bedanya)
2. [Hardware yang Dibutuhkan](#2-hardware-yang-dibutuhkan)
3. [Software yang Harus Diinstall](#3-software-yang-harus-diinstall)
4. [Step-by-Step Setup Server](#4-step-by-step-setup-server)
5. [Biar Bisa Diakses dari Luar (Port Forwarding + DDNS)](#5-biar-bisa-diakses-dari-luar-port-forwarding--ddns)
6. [Security Biar Gak Kena Hack](#6-security-biar-gak-kena-hack)
7. [Maintenance & Monitoring](#7-maintenance--monitoring)
8. [Kelebihan & Kekurangan Server Rumahan](#8-kelebihan--kekurangan-server-rumahan)

---

## 1. Server Pribadi vs VPS — Apa Bedanya?

| | Server Pribadi | VPS (Cloud) |
|---|---|---|
| **Biaya** | Listrik + internet doang | Rp50-200rb/bulan |
| **Performa** | Tergantung spek PC kamu | Tergantung paket |
| **Kontrol** | Full 100% | Full juga |
| **Akses 24 jam** | Wajib nyalain PC terus | Otomatis online 24/7 |
| **Listrik** | ~Rp100-200rb/bulan | Termasuk harga sewa |
| **Internet** | Butuh IP publik + stabil | Udah include |
| **Perawatan** | Urus sendiri fisiknya | Provider yang urus |
| **Kalau mati lampu** | Server mati total | Tetap online |
| **Cocok buat** | Belajar, testing, trafik kecil | Produksi, trafik banyak |

> **Kesimpulan:** Server rumahan cocok buat belajar, testing, atau toko online dengan pengunjung sedikit (≤50 orang/hari). Kalo udah ramai, mending pindah ke VPS aja.

---

## 2. Hardware yang Dibutuhkan

### Komputer Bekas / PC Lama (Rekomendasi)

| Komponen | Minimal | Recommended |
|---|---|---|
| **Processor** | Core 2 Duo / i3 generasi 2 | i5 gen 4+ / Ryzen 3+ |
| **RAM** | 4 GB | 8 GB |
| **Storage** | 120 GB SSD | 256 GB SSD + 500 GB HDD |
| **Network** | Ethernet LAN 100 Mbps | Gigabit LAN 1000 Mbps |
| **UPS** | **WAJIB** biar gak mati pas listrik padam | UPS 600VA+ |
| **Monitor** | Cuma pas setup doang, abis itu gak perlu | — |

### Alternatif — Single Board Computer (Hemat Listrik)

| Device | Harga Baru | RAM | Storage | Daya |
|---|---|---|---|---|
| **Raspberry Pi 4/5** | Rp700k-1.5jt | 4-8 GB | MicroSD 32GB+ | 5V/3A (~15 watt) |
| **Orange Pi Zero 3** | Rp300-500k | 1-4 GB | MicroSD | 5V/2A (~10 watt) |
| **Radxa Zero** | Rp400-600k | 2-4 GB | eMMC | 5V/2A (~10 watt) |

> **Catatan:** Raspberry Pi pake arsitektur ARM, bukan x86. Beberapa software mungkin beda cara installnya. Tapi buat Laravel + Ubuntu Server, jalan kok.

### Listrik & Internet

| Kebutuhan | Estimasi Biaya |
|---|---|
| **Daya listrik PC 24 jam** (PSU 300 watt x 24 jam x 30 hari) | ~Rp100-150rb/bulan (asumsi Rp1.500/kWh) |
| **Daya listrik Raspberry Pi 24 jam** (15 watt) | ~Rp15-20rb/bulan |
| **Internet rumah** | Rp250-500rb/bulan |
| **IP Public statis** (dari ISP) | Rp50-150rb/bulan (opsional) |

---

## 3. Software yang Harus Diinstall

### Sistem Operasi

**Pilihan #1 — Ubuntu Server 22.04 LTS (Rekomendasi)**
- Ringan, community besar, banyak tutorial
- Gak pake GUI desktop — semua lewat command line (SSH)
- Ukuran install cuma ~2GB

**Pilihan #2 — Ubuntu Desktop 22.04 LTS**
- Pake GUI / desktop kaya Windows
- Lebih gampang buat pemula
- Tapi boros RAM (2GB buat desktop doang)

### Stack Software (LEMP = Linux + Nginx + MySQL + PHP)

| Software | Fungsi |
|---|---|
| **Ubuntu Server** | Sistem operasi |
| **Nginx** | Web server (gantiin Apache) |
| **MySQL 8** | Database |
| **PHP 8.3** | Bahasa pemrograman Laravel |
| **Redis** | Cache + queue |
| **Composer** | Dependency manager PHP |
| **Git** | Version control |
| **Node.js + NPM** | Build frontend |
| **Supervisor** | Jaga queue worker tetap hidup |
| **Fail2ban** | Security — blocking IP |
| **UFW** | Firewall |
| **Let's Encrypt / Certbot** | SSL gratis |
| **Netdata** | Monitoring (opsional) |

---

## 4. Step-by-Step Setup Server

### 4a. Install Ubuntu Server

1. **Download Ubuntu Server 22.04 LTS** dari [ubuntu.com/download/server](https://ubuntu.com/download/server)
2. **Bikin bootable USB** pake [Rufus](https://rufus.ie/) (Windows) atau `dd` (Linux/Mac)
3. **Colok USB ke PC server**, boot dari USB (atur di BIOS: F2/DEL pas nyala)
4. **Ikuti installer:**
   - Pilih bahasa: English
   - Keyboard layout: Indonesian
   - Network: pilih pakai DHCP (biar otomatis dapet IP)
   - Proxy: kosongin aja
   - Mirror: `http://archive.ubuntu.com/ubuntu`
   - Storage: pilih "Use An Entire Disk" → centang disk → Done
   - Profile:
     - Your name: `Hello Store Server`
     - Server name: `hello-store`
     - Username: `admin`
     - Password: buat yang kuat (contoh: `H3lloSt0re!2026`)
   - SSH Setup: centang **"Install OpenSSH server"**
   - Featured Server Snaps: jangan centang apa-apa, pilih Done
5. **Tunggu install selesai** → Restart
6. **Login:** pake username & password yang tadi dibuat

### 4b. Setup Dasar Server

Setelah login, langsung jalanin perintah-perintah ini:

```bash
# Update sistem
sudo apt update && sudo apt upgrade -y

# Set timezone ke WIB
sudo timedatectl set-timezone Asia/Jakarta

# Install tools dasar
sudo apt install -y curl wget gnupg software-properties-common \
    ufw git unzip

# Matiin login root via password (lebih aman)
sudo sed -i 's/PermitRootLogin yes/PermitRootLogin prohibit-password/' /etc/ssh/sshd_config
sudo systemctl restart sshd
```

### 4c. Install Nginx

```bash
sudo apt install -y nginx
sudo systemctl enable nginx
sudo systemctl start nginx
```

Cek: buka browser, ketik `http://IP_SERVER_ANDA` — harusnya muncul halaman Welcome Nginx.

### 4d. Install MySQL 8

```bash
sudo apt install -y mysql-server
sudo mysql_secure_installation
```

Ikuti prompt:
- `VALIDATE PASSWORD COMPONENT` → **N** (biar gak ribet)
- `Remove anonymous users?` → **Y**
- `Disallow root login remotely?` → **Y**
- `Remove test database and access to it?` → **Y**
- `Reload privilege tables now?` → **Y**

### 4e. Install PHP 8.3

```bash
sudo add-apt-repository -y ppa:ondrej/php
sudo apt update
sudo apt install -y php8.3 php8.3-cli php8.3-common php8.3-mbstring \
    php8.3-xml php8.3-bcmath php8.3-curl php8.3-mysql php8.3-zip \
    php8.3-tokenizer php8.3-fileinfo php8.3-fpm php8.3-gd php8.3-intl
```

Cek:
```bash
php -v
```

### 4f. Install Composer

```bash
cd /tmp
php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
php composer-setup.php --install-dir=/usr/local/bin --filename=composer
php -r "unlink('composer-setup.php');"
cd ~
composer --version
```

### 4g. Install Redis

```bash
sudo apt install -y redis-server
sudo systemctl enable redis-server
sudo systemctl start redis-server
```

### 4h. Install Node.js & NPM

```bash
curl -fsSL https://deb.nodesource.com/setup_22.x | sudo -E bash -
sudo apt install -y nodejs
node -v
npm -v
```

### 4i. Setup Firewall (UFW)

```bash
sudo ufw allow OpenSSH
sudo ufw allow 'Nginx Full'
sudo ufw --force enable
sudo ufw status
```

### 4j. Setup Database & User

```bash
sudo mysql
```

Di dalam MySQL shell, jalanin:
```sql
CREATE DATABASE hello_store_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'hello_store'@'localhost' IDENTIFIED BY 'BuatPasswordKuat123!';
GRANT ALL PRIVILEGES ON hello_store_db.* TO 'hello_store'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

> **Catat password-nya!** Nanti dipake di file `.env`.

---

### 4k. Clone & Setup Hello Store

```bash
# Pindah ke direktori web
cd /var/www

# Clone project dari GitHub
# (Ganti USERNAME_GITHUB dengan username GitHub kamu)
sudo git clone https://github.com/USERNAME_GITHUB/hello-store.git
# Atau kalo pake SSH:
# sudo git clone git@github.com:USERNAME_GITHUB/hello-store.git

# Kasih permission
sudo chown -R $USER:$USER /var/www/hello-store

# Masuk ke folder project
cd /var/www/hello-store

# Copy environment
cp .env.example .env

# Generate key
php artisan key:generate
```

Edit file `.env`:
```bash
nano .env
```

Ubah isinya jadi:
```ini
APP_NAME="Hello Store"
APP_ENV=production
APP_DEBUG=false
APP_URL=http://IP_SERVER_ANDA

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=hello_store_db
DB_USERNAME=hello_store
DB_PASSWORD=BuatPasswordKuat123!

REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379

QUEUE_CONNECTION=redis
SESSION_DRIVER=redis
CACHE_STORE=redis
```

Simpen: `Ctrl+X` → `Y` → `Enter`

```bash
# Install dependencies
composer install --no-dev --optimize-autoloader

# Build frontend
npm install && npm run build

# Cache Laravel
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Migrate database + seed
php artisan migrate --seed

# Storage link
php artisan storage:link

# Permission
sudo chown -R www-data:www-data storage bootstrap/cache public/storage
sudo chmod -R 775 storage bootstrap/cache public/storage
```

---

### 4l. Konfigurasi Nginx

```bash
sudo nano /etc/nginx/sites-available/hello-store
```

Isi dengan:
```nginx
server {
    listen 80;
    server_name _;  # Ganti dengan domain atau IP kamu
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

    location ~* \.(jpg|jpeg|png|gif|ico|css|js|webp|svg|woff|woff2|ttf|eot)$ {
        expires 30d;
        add_header Cache-Control "public, immutable";
    }

    client_max_body_size 20M;
}
```

Aktifkan:
```bash
sudo ln -s /etc/nginx/sites-available/hello-store /etc/nginx/sites-enabled/
sudo rm /etc/nginx/sites-enabled/default
sudo nginx -t
sudo systemctl restart nginx
```

### 4m. Setup Supervisor (Queue Worker)

```bash
sudo apt install -y supervisor
sudo nano /etc/supervisor/conf.d/hello-store-worker.conf
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
sudo supervisorctl status
```

### 4n. Setup Cron (Scheduler)

```bash
sudo crontab -u www-data -e
```

Pilih `1` (nano), lalu tambah:
```cron
* * * * * cd /var/www/hello-store && php artisan schedule:run >> /dev/null 2>&1
```

Simpen: `Ctrl+X` → `Y` → `Enter`

### 4o. Cek Hasil

Buka browser di komputer lain (masih satu jaringan): `http://IP_SERVER_ANDA`

Kalo muncul Hello Store, **SELAMAT! Server pribadi kamu udah berhasil!**

> Buat tau IP server: jalanin `ip addr show | grep inet` di server, cari yang kayak `192.168.x.x`.

---

## 5. Biar Bisa Diakses dari Luar (Port Forwarding + DDNS)

### 5a. Bikin IP Server Jadi Static

Biar IP server gak berubah-ubah:

```bash
sudo nano /etc/netplan/00-installer-config.yaml
```

Cari bagian `dhcp4: true`, ganti jadi:
```yaml
network:
  ethernets:
    enp2s0:        # Nama interface - cek pake `ip link show`
      dhcp4: no
      addresses:
        - 192.168.1.100/24   # IP static (sesuain dengan jaringan lu)
      gateway4: 192.168.1.1   # IP router
      nameservers:
        addresses: [8.8.8.8, 1.1.1.1]
  version: 2
```

Terapin:
```bash
sudo netplan apply
```

### 5b. Port Forwarding di Router

1. Buka browser, ketik `192.168.1.1` (atau `192.168.0.1`)
2. Login ke admin router (biasanya `admin`/`admin` atau liat stiker di router)
3. Cari menu **Port Forwarding** / **Virtual Server** / **NAT**
4. Tambah rule:
   - Nama: `Hello Store Web`
   - Port eksternal: `80` (HTTP) dan `443` (HTTPS — kalo pake SSL)
   - Port internal: `80`
   - IP tujuan: `192.168.1.100` (IP static server)
   - Protocol: `TCP`
5. Simpen

### 5c. Setup DDNS (Dynamic DNS)

Karena IP publik rumah biasanya berubah-ubah, pake DDNS biar domain tetap mengarah ke server:

**Pilihan DDNS gratis:**

| Layanan | Domain Gratis | Cara Setup |
|---|---|---|
| **DuckDNS** | `namakamu.duckdns.org` | Paling gampang, tinggal curl API |
| **No-IP** | `namakamu.hopto.org` | Perlu konfirmasi tiap 30 hari |
| **Cloudflare** | Pake domain sendiri | Advanced, tapi paling mantap |

**Cara setup DuckDNS (termudah):**

1. Buka [duckdns.org](https://duckdns.org), login pake akun Google/GitHub
2. Bikin subdomain: `hello-store.duckdns.org`
3. Dapetin token

Di server:
```bash
# Bikin script update IP otomatis
sudo nano /usr/local/bin/duckdns.sh
```

Isi:
```bash
#!/bin/bash
echo url="https://www.duckdns.org/update?domains=hello-store&token=TOKEN_ANDA&ip=" | curl -k -o /var/log/duckdns.log -K -
```

```bash
sudo chmod +x /usr/local/bin/duckdns.sh

# Bikin cron jalan tiap 5 menit
sudo crontab -e
```
Tambah:
```cron
*/5 * * * * /usr/local/bin/duckdns.sh
```

### 5d. Setup SSL (Let's Encrypt)

Kalo udah punya domain (atau pake DuckDNS):

```bash
sudo apt install -y certbot python3-certbot-nginx
sudo certbot --nginx -d hello-store.duckdns.org
```

Ikuti prompt — nanti otomatis setup HTTPS.

Update `.env`:
```bash
APP_URL=https://hello-store.duckdns.org
```

```bash
php artisan config:cache
```

### 5e. Cek dari Luar

Matiin WiFi HP → buka browser → ketik `http://IP_PUBLIK_RUMAH_ANDA` atau `https://hello-store.duckdns.org`

Kalo muncul Hello Store, **server rumahan kamu udah bisa diakses dari mana aja!**

> **Cara tau IP publik:** Google aja "what is my ip" atau `curl ifconfig.me` di server.

---

## 6. Security Biar Gak Kena Hack

### 6a. Ganti Port SSH (biar gak kena bot)

```bash
sudo nano /etc/ssh/sshd_config
```

Cari `#Port 22`, ganti jadi:
```
Port 2222
```

```bash
sudo systemctl restart sshd
```

Sekarang SSH pake port 2222:
```bash
ssh admin@IP_SERVER -p 2222
```

Jangan lupa update firewall:
```bash
sudo ufw allow 2222
```

### 6b. Install Fail2ban (anti brute force)

```bash
sudo apt install -y fail2ban
sudo systemctl enable fail2ban
sudo systemctl start fail2ban
```

### 6c. Matiin Root Login SSH

Udah di-setup di awal. Cek:
```bash
sudo grep PermitRootLogin /etc/ssh/sshd_config
```
Harusnya: `PermitRootLogin prohibit-password`

### 6d. Firewall Cuma Buka Yang Diperluin Aja

```bash
sudo ufw status numbered
```
Harusnya cuma ada:
- `OpenSSH` (atau port `2222`)
- `Nginx Full` (port 80 + 443)

### 6e. Auto Update Keamanan

```bash
sudo apt install -y unattended-upgrades
sudo dpkg-reconfigure --priority=low unattended-upgrades
```

Pilih `Yes` untuk auto install security updates.

### 6f. Matiin Ping (biar gak kelihatan)

```bash
sudo nano /etc/ufw/before.rules
```

Cari baris `# ok icmp codes for INPUT`, tambahin di atasnya:
```
# Drop all ICMP
-A ufw-before-input -p icmp --icmp-type echo-request -j DROP
```

```bash
sudo ufw reload
```

### 6g. Password Kuat

Buat semua password: minimal 12 karakter, campur huruf besar, kecil, angka, simbol.

Contoh: `H3ll0St0re!Rumahan#2026`

### 6h. Backup File `.env`

`.env` isinya password DB, APP_KEY, dll. Backup di tempat aman:
```bash
cp /var/www/hello-store/.env ~/backup-env.txt
```
Atau catat manual di buku.

---

## 7. Maintenance & Monitoring

### 7a. Update Berkala (tiap 2 minggu)

```bash
# Update sistem
sudo apt update && sudo apt upgrade -y

# Update project
cd /var/www/hello-store
git pull origin main
composer install --no-dev --optimize-autoloader
npm install && npm run build
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
sudo supervisorctl restart all

# Bersihin file sampah
sudo apt autoremove -y
sudo apt autoclean
```

### 7b. Cek Kesehatan Server

```bash
# Cek disk usage
df -h

# Cek RAM
free -h

# Cek CPU
top -bn1 | head -5

# Cek log error
tail -50 /var/www/hello-store/storage/logs/laravel.log

# Cek queue worker
sudo supervisorctl status

# Cek Nginx
sudo systemctl status nginx --no-pager

# Cek PHP-FPM
sudo systemctl status php8.3-fpm --no-pager
```

### 7c. Backup Database Otomatis

```bash
# Bikin folder backup
sudo mkdir -p /backup/db
sudo mkdir -p /backup/project

# Bikin script backup
sudo nano /usr/local/bin/backup.sh
```

Isi:
```bash
#!/bin/bash
# Backup database
mysqldump -u hello_store -p'BuatPasswordKuat123!' hello_store_db | gzip > /backup/db/hello-store-$(date +%Y%m%d-%H%M).sql.gz

# Hapus backup lebih dari 30 hari
find /backup/db -name "*.sql.gz" -mtime +30 -delete

echo "Backup selesai: $(date)" >> /var/log/backup.log
```

```bash
sudo chmod +x /usr/local/bin/backup.sh

# Cron tiap hari jam 3 pagi
sudo crontab -e
```
Tambah:
```cron
0 3 * * * /usr/local/bin/backup.sh
```

### 7d. Monitoring Sederhana — Netdata

```bash
bash <(curl -Ss https://my-netdata.io/kickstart.sh)
```

Buka browser: `http://IP_SERVER_ANDA:19999` — liat semua metrics (CPU, RAM, disk, network, dll).

### 7e. Restart Service Kalau Bermasalah

Bikin script auto-recovery:
```bash
sudo nano /usr/local/bin/health-check.sh
```

Isi:
```bash
#!/bin/bash
# Cek Nginx
if ! systemctl is-active --quiet nginx; then
    systemctl restart nginx
    echo "$(date): Nginx restart" >> /var/log/health-check.log
fi

# Cek PHP-FPM
if ! systemctl is-active --quiet php8.3-fpm; then
    systemctl restart php8.3-fpm
    echo "$(date): PHP-FPM restart" >> /var/log/health-check.log
fi

# Cek MySQL
if ! systemctl is-active --quiet mysql; then
    systemctl restart mysql
    echo "$(date): MySQL restart" >> /var/log/health-check.log
fi

# Cek Redis
if ! systemctl is-active --quiet redis-server; then
    systemctl restart redis-server
    echo "$(date): Redis restart" >> /var/log/health-check.log
fi

# Cek Supervisor
if ! systemctl is-active --quiet supervisor; then
    systemctl restart supervisor
    echo "$(date): Supervisor restart" >> /var/log/health-check.log
fi
```

```bash
sudo chmod +x /usr/local/bin/health-check.sh

# Cron tiap 5 menit
sudo crontab -e
```
Tambah:
```cron
*/5 * * * * /usr/local/bin/health-check.sh
```

---

## 8. Kelebihan & Kekurangan Server Rumahan

### Kelebihan (+)

| No | Kelebihan |
|---|---|
| 1 | **Gratis bulanan** — cuma bayar listrik + internet |
| 2 | **Spek gede** — PC bekas i5 8GB jauh lebih kenceng dari VPS Rp100rb |
| 3 | **Kontrol full** — mau ganti hardware, upgrade RAM, tinggal colok |
| 4 | **Storage gede** — bisa pake HDD 1TB+ untuk file, backup, dll |
| 5 | **Belajar banyak** — lo bakal ngerti cara kerja server beneran |
| 6 | **Cocok buat development** — testing fitur sebelum deploy ke VPS |

### Kekurangan (-)

| No | Kekurangan |
|---|---|
| 1 | **Listrik mati = server mati** — solusi: pake UPS |
| 2 | **Internet rumah gak stabil** — bisa lemot/mati tiba-tiba |
| 3 | **IP publik ganti-ganti** — harus pake DDNS |
| 4 | **Kecepatan upload terbatas** — ISP rumahan biasanya upload 10-20 Mbps |
| 5 | **Beresiko kena hack** — lo sendiri yang jagain keamanan |
| 6 | **Bising + panas** — PC nyala 24 jam |
| 7 | **Gak ada SLA (jaminan uptime)** — kalo rusak, urus sendiri |

### Kapan Server Rumahan Cocok?

| Situasi | Cocok? |
|---|---|
| **Belajar Laravel / server admin** | ✅ Sangat cocok |
| **Toko online trafik rendah** (< 50 orang/hari) | ✅ Cocok |
| **Testing fitur baru** | ✅ Sangat cocok |
| **Toko online udah ramai** (> 200 orang/hari) | ❌ Pindah ke VPS |
| **Butuh uptime 99.9%** | ❌ Pindah ke cloud |
| **Pelanggan bayar pake kartu kredit** | ❌ Pindah ke VPS+SSL |

---

### Perbandingan Biaya: Server Rumahan vs VPS

| Komponen | Server Rumahan | VPS (Host.ID Lite 2.1) |
|---|---|---|
| **Sewa server** | Rp0 | Rp100.000/bulan |
| **Listrik PC** (300 watt) | ~Rp130.000/bulan | Rp0 |
| **Internet** | Rp350.000/bulan | Rp0 |
| **Domain** | Rp0 (pake DuckDNS) | Rp0 (pake DuckDNS) |
| **UPS** (investasi awal) | Rp500.000 (sekali) | Rp0 |
| **Total per bulan** | **~Rp480.000** | **Rp100.000** |
| **Total setahun** | **~Rp5.760.000** | **Rp1.200.000** |

> Realitanya, server rumahan **lebih mahal** dari VPS kalo diitung listrik + internet.
> Tapi lo dapet spek lebih gede & ilmu lebih banyak.

---

### Tips Hemat Biaya Server Rumahan

1. **Ganti PC dengan laptop bekas** — lebih hemat listrik (~50 watt vs 300 watt)
2. **Atur jadwal mati otomatis** — mati jam 12 malam, hidup jam 6 pagi (kalo gak butuh 24 jam)
3. **Pake Raspberry Pi** — daya cuma 15 watt, setara lampu LED
4. **Pasang panel surya kecil** — untuk server doang (investasi jangka panjang)
5. **Pake DDNS gratis** (DuckDNS) — gak usah bayar domain

---

### Cara Matiin Server Otomatis Biar Hemat Listrik

```bash
# Mati otomatis jam 12 malam
sudo crontab -e
```
Tambah:
```cron
0 0 * * * /sbin/shutdown -h now
```
> Tapi kalo server mati, toko online juga ikutan mati. Jadi pertimbangkan lagi.

---

## Checklist Setup Server Rumahan

| Step | Status |
|---|---|
| PC/Laptop siap dengan spek minimal | ☐ |
| USB bootable Ubuntu Server | ☐ |
| Install Ubuntu Server selesai | ☐ |
| Nginx terinstall & jalan | ☐ |
| MySQL terinstall | ☐ |
| Database & user dibuat | ☐ |
| PHP 8.3 terinstall | ☐ |
| Composer terinstall | ☐ |
| Redis terinstall | ☐ |
| Node.js & NPM terinstall | ☐ |
| Firewall aktif (UFW) | ☐ |
| Project Hello Store ter-clone | ☐ |
| `.env` sudah diisi | ☐ |
| Composer install berhasil | ☐ |
| NPM build berhasil | ☐ |
| Migrate + seed berhasil | ☐ |
| Nginx config hello-store aktif | ☐ |
| Supervisor queue worker jalan | ☐ |
| Cron scheduler jalan | ☐ |
| Server bisa diakses dari browser (lokal) | ☐ |
| IP server static | ☐ |
| Port forwarding di router | ☐ |
| DDNS setup | ☐ |
| SSL (Let's Encrypt) terpasang | ☐ |
| Bisa diakses dari luar | ☐ |
| Fail2ban terinstall | ☐ |
| Auto backup jalan | ☐ |
| Health check script jalan | ☐ |

---

> **Catatan penting :** Server rumahan itu **proyek belajar yang seru**, bukan solusi produksi jangka panjang. Pas toko online udah mulai ramai (≥100 pengunjung/hari), transfer aja ke VPS — panduannya udah ada di file `hosting vps di orange cloud.md` (ganti aja "Orange Cloud" dengan provider beneran kayak Host.ID atau DomaiNesia).
