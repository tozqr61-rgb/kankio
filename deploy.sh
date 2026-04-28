#!/bin/bash

# ─────────────────────────────────────────────────────────────
# Kankio — Otomatik Deploy Scripti
# Kullanım: ./deploy.sh
# VPS'te /var/www/kankio/ içinde çalıştırılır
# ─────────────────────────────────────────────────────────────

set -e  # Hata olursa dur

echo "🚀 Deploy başlıyor..."

# 1. Bakım modunu aç (kullanıcılar güzel bir sayfa görür)
php artisan down --refresh=15 --retry=10

# 2. GitHub'dan son kodu çek
git pull origin main

# 3. PHP bağımlılıklarını güncelle (sadece production)
composer install --no-dev --optimize-autoloader --no-interaction

# 4. Veritabanı migration (yeni tablolar varsa)
php artisan migrate --force

# 5. Cache'leri temizle ve yeniden oluştur
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

# 6. Storage link + dizinler
php artisan storage:link 2>/dev/null || true
mkdir -p storage/app/public/avatars
mkdir -p storage/app/public/voice_messages

# 7. Supervisor ile çalışan Reverb + Queue'yu yeniden başlat
sudo supervisorctl restart kankio-reverb
sudo supervisorctl restart kankio-queue

# 8. Scheduler cron kontrolü (yoksa ekle)
CRON_JOB="* * * * * cd /var/www/kankio && php artisan schedule:run >> /dev/null 2>&1"
(crontab -l 2>/dev/null | grep -qF 'schedule:run') || (crontab -l 2>/dev/null; echo "$CRON_JOB") | crontab -

# 9. Bakım modunu kapat
php artisan up

echo "✅ Deploy tamamlandı!"
echo "🌐 Site: https://kank.com.tr"
