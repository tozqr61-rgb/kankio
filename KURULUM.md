# Kankio Kurulum

## Gereksinimler

- PHP 8.2+
- Composer
- Node.js ve npm
- Desteklenen veritabanı
- Redis veya Laravel cache/session için uygun backend
- Reverb için websocket portu
- LiveKit sunucusu veya LiveKit Cloud hesabı

## Adımlar

```bash
cp .env.example .env
composer install
npm ci
php artisan key:generate
php artisan migrate
php artisan storage:link
php artisan app:create-admin
npm run build
php artisan serve
```

## Reverb

`.env` içinde `REVERB_APP_ID`, `REVERB_APP_KEY`, `REVERB_APP_SECRET`, `REVERB_HOST`, `REVERB_PORT` ve `REVERB_SCHEME` değerlerini production ortamına özel üretin. Örnek değerleri canlıya taşımayın.

## LiveKit

Sesli sohbet için `LIVEKIT_URL`, `LIVEKIT_API_KEY` ve `LIVEKIT_API_SECRET` zorunludur. Eksikse voice join endpoint’i JSON hata döner.

## Bağlantıda Kal

`BAGLANTIKAL_ACCESS_PIN` ve `BAGLANTIKAL_LETTER_PIN` canlı ortamda boş bırakılamaz. Public sayfa içerik preload etmez; içerik sadece doğru PIN sonrası JSON ile alınır.

## Tauri

Desktop build:

```bash
npm run tauri:build
```

Tauri istemci remote shell olarak `https://kank.com.tr` açar. Mikrofon izni Linux/WebKit tarafında yalnızca beklenen chat origin/path için otomatik verilir.
