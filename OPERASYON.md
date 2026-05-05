# Kankio Operasyon

## İlk Kurulum

1. `.env` değerlerini production için üretin.
2. `php artisan migrate --force` çalıştırın.
3. `php artisan app:create-admin` ile ilk admin hesabı oluşturun.
4. Reverb, queue ve scheduler süreçlerini supervisor/cron ile ayağa kaldırın.

## Deploy

`deploy.sh` sırasıyla bakım modunu açar, kodu çeker, PHP bağımlılıklarını kurar, frontend build üretir, migration ve cache adımlarını çalıştırır, supervisor süreçlerini yeniden başlatır ve bakım modunu kapatır.

## Audit

Admin panelinden yapılan yıkıcı veya operasyonel işlemler `admin_actions` tablosuna kaydedilir. İnceleme sırasında `actor_id`, `action`, `target_type`, `target_id`, `payload`, `ip_address` ve timestamp alanları kullanılmalıdır.

## Release Linkleri

Admin app release formu yalnızca `APP_RELEASE_ALLOWED_HOSTS` içindeki domainleri kabul eder. SHA-256 checksum alanı opsiyoneldir ama production release’lerinde doldurulması önerilir.

## PWA Cache

Service worker `/chat`, `/rooms`, `/admin`, `/baglantikal`, `/login` ve `/register` HTML yanıtlarını cachelemez. Auth içeren yeni route eklendiğinde bypass listesi güncellenmelidir.
