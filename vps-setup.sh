#!/bin/bash
# ═══════════════════════════════════════════════════════════════
# Kankio — VPS Tek Seferlik Kurulum Scripti
# IP: 77.83.203.179  |  Domain: kank.com.tr
# Çalıştırma: bash vps-setup.sh
# ═══════════════════════════════════════════════════════════════
set -e

DOMAIN="kank.com.tr"
PROJECT_DIR="/var/www/kankio"
GITHUB_REPO="https://github.com/tozqr61-rgb/kankio.git"

echo "════════════════════════════════════════"
echo "  Kankio VPS Kurulum Başlıyor"
echo "  Domain: $DOMAIN"
echo "════════════════════════════════════════"

# ── 1. Sistem güncelleme ────────────────────────────────────────
echo "[1/10] Sistem güncelleniyor..."
apt update -y && apt upgrade -y
apt install -y curl wget git unzip software-properties-common

# ── 2. Swap (RAM sigortası) ─────────────────────────────────────
echo "[2/10] Swap oluşturuluyor (1GB)..."
if [ ! -f /swapfile ]; then
    fallocate -l 1G /swapfile
    chmod 600 /swapfile
    mkswap /swapfile
    swapon /swapfile
    echo '/swapfile none swap sw 0 0' >> /etc/fstab
    echo "vm.swappiness=10" >> /etc/sysctl.conf
fi

# ── 3. Nginx ────────────────────────────────────────────────────
echo "[3/10] Nginx kuruluyor..."
apt install -y nginx
systemctl enable nginx

# ── 4. PHP 8.3 ─────────────────────────────────────────────────
echo "[4/10] PHP 8.3 kuruluyor..."
add-apt-repository -y ppa:ondrej/php
apt update -y
apt install -y php8.3-fpm php8.3-mysql php8.3-xml php8.3-curl \
               php8.3-mbstring php8.3-zip php8.3-gd php8.3-bcmath \
               php8.3-redis php8.3-pcov php8.3-intl

# PHP ayarları
sed -i 's/upload_max_filesize = .*/upload_max_filesize = 10M/' /etc/php/8.3/fpm/php.ini
sed -i 's/post_max_size = .*/post_max_size = 10M/' /etc/php/8.3/fpm/php.ini
sed -i 's/memory_limit = .*/memory_limit = 256M/' /etc/php/8.3/fpm/php.ini
systemctl enable php8.3-fpm

# ── 5. MySQL 8 ──────────────────────────────────────────────────
echo "[5/10] MySQL kuruluyor..."
apt install -y mysql-server
systemctl enable mysql

# Veritabanı ve kullanıcı oluştur
DB_PASS=$(openssl rand -base64 24 | tr -dc 'a-zA-Z0-9' | head -c 20)
mysql -e "CREATE DATABASE IF NOT EXISTS kankio CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql -e "CREATE USER IF NOT EXISTS 'kankio'@'localhost' IDENTIFIED BY '$DB_PASS';"
mysql -e "GRANT ALL PRIVILEGES ON kankio.* TO 'kankio'@'localhost';"
mysql -e "FLUSH PRIVILEGES;"
echo "DB_PASSWORD=$DB_PASS" > /root/kankio_db_credentials.txt
echo "✅ Veritabanı şifresi /root/kankio_db_credentials.txt dosyasına kaydedildi"

# ── 6. Composer ─────────────────────────────────────────────────
echo "[6/10] Composer kuruluyor..."
curl -sS https://getcomposer.org/installer | php
mv composer.phar /usr/local/bin/composer
chmod +x /usr/local/bin/composer

# ── 7. Supervisor ───────────────────────────────────────────────
echo "[7/10] Supervisor kuruluyor..."
apt install -y supervisor
systemctl enable supervisor

cat > /etc/supervisor/conf.d/kankio.conf << 'EOF'
[program:kankio-reverb]
command=php /var/www/kankio/artisan reverb:start --host=0.0.0.0 --port=8080
directory=/var/www/kankio
user=www-data
autostart=true
autorestart=true
redirect_stderr=true
stdout_logfile=/var/log/kankio-reverb.log
stdout_logfile_maxbytes=10MB

[program:kankio-queue]
command=php /var/www/kankio/artisan queue:work --sleep=3 --tries=3 --max-time=3600
directory=/var/www/kankio
user=www-data
autostart=true
autorestart=true
redirect_stderr=true
stdout_logfile=/var/log/kankio-queue.log
stdout_logfile_maxbytes=10MB
EOF

# ── 8. Certbot (SSL) ────────────────────────────────────────────
echo "[8/10] Certbot kuruluyor..."
apt install -y certbot python3-certbot-nginx

# ── 9. Proje dizini ─────────────────────────────────────────────
echo "[9/10] Proje dizini hazırlanıyor..."
mkdir -p $PROJECT_DIR
chown -R www-data:www-data /var/www

if [ -n "$GITHUB_REPO" ]; then
    git clone "$GITHUB_REPO" "$PROJECT_DIR"
    echo "✅ Kod GitHub'dan çekildi"
else
    echo "⚠️  GitHub repo adresi girilmedi. Kodu manuel yüklemen gerekiyor."
    echo "   Sonra şunu çalıştır: bash /root/kankio_finalize.sh"
fi

# ── 10. Nginx Config ────────────────────────────────────────────
echo "[10/10] Nginx yapılandırılıyor..."
cat > /etc/nginx/sites-available/kankio << 'NGINXEOF'
server {
    listen 80;
    listen [::]:80;
    server_name kank.com.tr www.kank.com.tr;
    return 301 https://kank.com.tr$request_uri;
}

server {
    listen 443 ssl http2;
    listen [::]:443 ssl http2;
    server_name kank.com.tr www.kank.com.tr;

    root /var/www/kankio/public;
    index index.php;

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";
    gzip on;
    gzip_types text/plain text/css application/json application/javascript;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ ^/(app|apps|broadcasting) {
        proxy_pass         http://127.0.0.1:8080;
        proxy_http_version 1.1;
        proxy_set_header   Upgrade $http_upgrade;
        proxy_set_header   Connection "Upgrade";
        proxy_set_header   Host $host;
        proxy_set_header   X-Real-IP $remote_addr;
        proxy_read_timeout 60s;
    }

    location ~ \.php$ {
        fastcgi_pass   unix:/run/php/php8.3-fpm.sock;
        fastcgi_index  index.php;
        include        fastcgi_params;
        fastcgi_param  SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
    }

    location ~* \.(css|js|png|jpg|jpeg|gif|ico|svg|woff2)$ {
        expires 1y;
        add_header Cache-Control "public, immutable";
    }

    location ~ /\. { deny all; }
    client_max_body_size 10M;
    charset utf-8;
}
NGINXEOF

ln -sf /etc/nginx/sites-available/kankio /etc/nginx/sites-enabled/
rm -f /etc/nginx/sites-enabled/default
nginx -t && systemctl reload nginx

# ── Finalize scripti ────────────────────────────────────────────
cat > /root/kankio_finalize.sh << 'FINALEOF'
#!/bin/bash
# Kodu yükledikten sonra bu scripti çalıştır
set -e
PROJECT_DIR="/var/www/kankio"
DB_PASS=$(grep DB_PASSWORD /root/kankio_db_credentials.txt | cut -d= -f2)

cd $PROJECT_DIR

# Composer
composer install --no-dev --optimize-autoloader --no-interaction

# .env oluştur
if [ ! -f .env ]; then
    cp .env.example .env
    sed -i "s/DB_USERNAME=.*/DB_USERNAME=kankio/" .env
    sed -i "s/DB_PASSWORD=.*/DB_PASSWORD=$DB_PASS/" .env
    sed -i "s/DB_DATABASE=.*/DB_DATABASE=kankio/" .env
    sed -i "s|APP_URL=.*|APP_URL=https://kank.com.tr|" .env
fi

php artisan key:generate --force
php artisan migrate --force
php artisan db:seed --force
php artisan storage:link
php artisan config:cache
php artisan route:cache
php artisan view:cache

# İzinler
chown -R www-data:www-data $PROJECT_DIR
chmod -R 755 $PROJECT_DIR/storage
chmod -R 755 $PROJECT_DIR/bootstrap/cache

# Supervisor başlat
supervisorctl reread
supervisorctl update
supervisorctl start kankio-reverb
supervisorctl start kankio-queue

# SSL
certbot --nginx -d kank.com.tr -d www.kank.com.tr --non-interactive --agree-tos -m admin@kank.com.tr

# Cron (Laravel Scheduler)
(crontab -l 2>/dev/null; echo "* * * * * cd $PROJECT_DIR && php artisan schedule:run >> /dev/null 2>&1") | crontab -

nginx -t && systemctl reload nginx

echo ""
echo "╔══════════════════════════════════════╗"
echo "║   ✅  Kankio Kurulumu Tamamlandı!    ║"
echo "║   🌐  https://kank.com.tr            ║"
echo "╚══════════════════════════════════════╝"
FINALEOF

chmod +x /root/kankio_finalize.sh

# ── Güvenlik duvarı ─────────────────────────────────────────────
ufw allow OpenSSH
ufw allow 'Nginx Full'
ufw --force enable

echo ""
echo "╔══════════════════════════════════════════════════════╗"
echo "║   ✅  Altyapı kurulumu tamamlandı!                   ║"
echo "║                                                      ║"
echo "║   Sonraki adım:                                      ║"
echo "║   1. Kodu /var/www/kankio/ klasörüne yükle           ║"
echo "║      (GitHub: git clone ... /var/www/kankio)         ║"
echo "║      (SFTP: FileZilla ile yükle)                     ║"
echo "║   2. bash /root/kankio_finalize.sh çalıştır          ║"
echo "║                                                      ║"
echo "║   DB şifresi: /root/kankio_db_credentials.txt        ║"
echo "╚══════════════════════════════════════╝"
