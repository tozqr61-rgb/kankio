# Kankio

Kankio; arkadaş grupları için gerçek zamanlı sohbet, özel/genel odalar, LiveKit tabanlı sesli görüşme, senkron müzik, oda bağlı İsim-Şehir oyunu ve küçük PWA/Tauri istemci desteği olan bir Laravel uygulamasıdır.

## Teknoloji Yığını

- Laravel 12, Blade, Alpine.js, Vite
- Reverb/Echo ile gerçek zamanlı olaylar
- LiveKit ile sesli sohbet
- YouTube Data API/oEmbed ile müzik metadata
- Tauri 2 ile desktop shell
- Queue, scheduler ve supervisor tabanlı production çalışma düzeni

## Local Kurulum

```bash
cp .env.example .env
composer install
npm ci
php artisan key:generate
php artisan migrate
php artisan app:create-admin
npm run build
php artisan serve
```

Geliştirme sırasında Reverb/queue/log/Vite süreçlerini birlikte başlatmak için:

```bash
composer run dev
```

## Önemli Env Değişkenleri

- `APP_KEY`, `APP_URL`
- `REVERB_APP_ID`, `REVERB_APP_KEY`, `REVERB_APP_SECRET`
- `LIVEKIT_URL`, `LIVEKIT_API_KEY`, `LIVEKIT_API_SECRET`
- `BAGLANTIKAL_ACCESS_PIN`, `BAGLANTIKAL_LETTER_PIN`
- `YOUTUBE_API_KEY`
- `APP_RELEASE_ALLOWED_HOSTS`

Production ortamında kritik secret veya PIN değerleri boş/`change-me` ise uygulama fail-fast davranır.

## Operasyon

- İlk admin kullanıcı `php artisan app:create-admin` ile oluşturulur.
- Default production credential veya sabit invite kodu seed edilmez.
- Admin panelindeki yıkıcı işlemler `admin_actions` tablosuna yazılır.
- İsim-Şehir oyun state’i DB’de saklanır; oyun sayfası açıldığında state backend’den hydrate edilir.
- Deploy script `composer install`, `npm ci`, `npm run build`, migration, cache ve supervisor restart adımlarını çalıştırır.

Detaylar için [KURULUM.md](KURULUM.md), [MIMARI.md](MIMARI.md) ve [OPERASYON.md](OPERASYON.md) dosyalarına bakın.
