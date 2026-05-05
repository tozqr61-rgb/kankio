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

## Denetim Erişimi

`admin` ve `oversight_admin` rolleri `/admin/oversight` sayfasından gerekçeli oda erişimi başlatabilir. Bu erişim 30 dakika geçerlidir, `admin_actions` içine `oversight.room_access` olarak yazılır ve websocket channel authorization aynı kayıt üzerinden karar verir. Bu kayıtlar silinmemeli; gerekçe olmadan erişim açılmamalıdır.

## Voice + Music + Game Test Planı

Canlı öncesi en az şu senaryolar gerçek cihazda denenmelidir:

- Desktop Chrome/Linux: voice'a katıl, müzik başlat, İsim-Şehir overlay aç, konuş, mesaj gönder, müzik değiştir, mute/deafen yap, overlay kapat.
- Android Chrome: voice'a katıl, aktif müziği `Müziği Etkinleştir` ile aç, oyun overlay aç, ekranı kilitle/aç, reconnect davranışını kontrol et.
- Tauri desktop: mikrofon izni, voice join, müzik iframe, oyun overlay ve minimize/tray sonrası ses davranışı kontrol edilir.

Beklenen sonuç: voice kopmaz, müzik state sapmaz, oyun state backend'den geri yüklenir, chat seen/unread bozulmaz ve reconnect loop oluşmaz.

## Tauri Ürün Kararı

Desktop istemci şu anda remote-shell modelindedir: uygulama web uygulamasını sarar, offline yerel frontend hedeflemez. Mikrofon izni yalnızca beklenen chat origin/route bağlamında otomatik verilir; farklı origin veya bağlamda varsayılan platform izin akışına bırakılır.

## Release Linkleri

Admin app release formu yalnızca `APP_RELEASE_ALLOWED_HOSTS` içindeki domainleri kabul eder. SHA-256 checksum alanı opsiyoneldir ama production release’lerinde doldurulması önerilir.

## PWA Cache

Service worker `/chat`, `/rooms`, `/admin`, `/baglantikal`, `/login` ve `/register` HTML yanıtlarını cachelemez. Auth içeren yeni route eklendiğinde bypass listesi güncellenmelidir.
