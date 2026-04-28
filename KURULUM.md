# Kankio - Laravel Kurulum Rehberi

## Gereksinimler
- XAMPP (PHP 8.2+, MySQL, Apache)
- Composer
- Node.js (npm)

## Kurulum Adımları

### 1. Veritabanı Oluştur
XAMPP phpMyAdmin'den `kankio` adında bir veritabanı oluşturun.

### 2. .env Dosyası
```bash
cp .env.example .env
```
`.env` dosyasını açıp kontrol edin (DB_DATABASE=kankio, DB_USERNAME=root).

### 3. Composer Bağımlılıkları
```bash
composer install
```

### 4. Uygulama Anahtarı
```bash
php artisan key:generate
```

### 5. Migrations
```bash
php artisan migrate
```

### 6. Seed (Admin kullanıcı + varsayılan oda + davetiyeler)
```bash
php artisan db:seed
```

### 7. Storage Symlink (avatar yükleme için)
```bash
php artisan storage:link
```

### 8. Apache ile Çalıştırma
XAMPP Apache'yi başlatın. Tarayıcıda açın:
```
http://localhost/proje/Kank/public
```

## Varsayılan Giriş Bilgileri
- **Kullanıcı Adı:** admin
- **Şifre:** admin123

## Kayıt için Davetiye Kodları
- `KNK-ALPHA`
- `KNK-BETA0`
- `KNK-GAMMA`

## Özellikler
- Gerçek zamanlı mesajlaşma (2 saniyelik polling)
- Oda oluşturma (Global / Gizli)
- Çevrimiçi kullanıcı listesi
- Profil resmi yükleme (7 günlük limit)
- Bildirim sesi
- Admin paneli (kullanıcı/oda/davetiye yönetimi)
- Mesaj yanıtlama
- Admin mesaj silme
- Davetiye kodu sistemi
- Yasaklama sistemi

# 1. Bağımlılıkları kur (Arch)
sudo pacman -S webkit2gtk-4.1 gtk3 libayatana-appindicator openssl base-devel

# 2. Rust + Tauri CLI (yoksa)
curl --proto '=https' --tlsv1.2 -sSf https://sh.rustup.rs | sh
cargo install tauri-cli

# 3a. Otomatik build (script ile)
cd src-tauri && ./build-linux.sh

# 3b. Manuel build
cargo tauri build

# 4. Veya Arch paketi olarak kur
cd src-tauri && makepkg -si