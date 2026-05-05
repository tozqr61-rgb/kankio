# Kankio Mimari Notları

## Ana Akışlar

- Chat sayfası Blade shell ve Alpine tabanlı istemci mantığıyla çalışır.
- Reverb gerçek zamanlı mesaj, typing, read receipt, voice participant ve music state eventlerini taşır.
- Poll/bootstrap endpoint’leri realtime bağlantı zayıfladığında fallback sağlar.

## Yetkilendirme

Oda erişimi `RoomAccessService` üzerinden merkezileştirilir:

- Private oda: üye veya admin.
- Global/announcements oda: authenticated kullanıcı.
- Global oda oluşturma: yalnızca admin.
- Private oda oluşturma: authenticated kullanıcı, kota ve throttle ile.
- Voice join/moderation: oda erişimi ve oda üyelik/rol kurallarına bağlıdır.

## Ses ve Müzik

- LiveKit token üretimi sunucuda yapılır.
- Voice runtime Alpine reactive state dışında tutulur.
- Music state `room_music_states` tablosunda tutulur.
- YouTube metadata istekleri timeout/retry/cache ile yapılır; scraping sadece fallback’tir.

## Oyunlar

- İsim-Şehir oyun state’i DB kaynaklıdır: `game_sessions`, `game_participants`, `game_rounds`, `game_submissions`.
- Frontend yalnızca `/rooms/{room}/games/{session}/state` yanıtını görüntüleme cache’i olarak kullanır.
- Round deadline, submit lock ve puanlama backend servislerinde ilerler; client timer sadece görsel sayaçtır.
- Oyun güncellemeleri `room.{roomId}.game` private broadcast kanalından yayınlanır.

## Frontend Yapısı

`resources/views/chat/room.blade.php` shell görevi görür. Parçalar `resources/views/chat/partials/` altında, ana Alpine mantığı `resources/js/chat-room.js` içindedir.

## Güvenlik

- Login/register throttle altındadır.
- Auth sonrası session regenerate edilir.
- BaglantiKal public initial payload taşımaz.
- Service worker hassas HTML route’ları cachelemez.
- Admin yıkıcı aksiyonları audit tablosuna yazılır.
