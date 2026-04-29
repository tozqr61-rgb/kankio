@extends('layouts.admin')

@section('admin-content')
<div class="space-y-6" x-data="adminDashboard()">
    <h1 class="text-3xl font-bold tracking-tight text-white">Overview</h1>

    <div class="grid gap-4 md:grid-cols-3">
        <div class="rounded-2xl border p-6" style="background:rgba(255,255,255,0.03);border-color:rgba(255,255,255,0.07)">
            <p class="text-sm font-medium text-zinc-400 mb-2">Total Users</p>
            <div class="text-3xl font-bold text-white">{{ $userCount }}</div>
        </div>
        <div class="rounded-2xl border p-6" style="background:rgba(255,255,255,0.03);border-color:rgba(255,255,255,0.07)">
            <p class="text-sm font-medium text-zinc-400 mb-2">Total Messages</p>
            <div class="text-3xl font-bold text-white">{{ $msgCount }}</div>
        </div>
        <div class="rounded-2xl border p-6" style="background:rgba(255,255,255,0.03);border-color:rgba(255,255,255,0.07)">
            <p class="text-sm font-medium text-zinc-400 mb-2">Active Rooms</p>
            <div class="text-3xl font-bold text-white">{{ $roomCount }}</div>
        </div>
    </div>

    <!-- Duyuru Yönetimi -->
    <div class="mt-8 pt-6 border-t border-white/10">
        <h2 class="text-xl font-bold tracking-tight mb-4 text-white">📢 Sistem Duyurusu</h2>
        <div class="max-w-xl p-6 border rounded-2xl space-y-4" style="background:rgba(255,255,255,0.03);border-color:rgba(255,255,255,0.07)">

            {{-- Mevcut aktif duyuru --}}
            @php $active = \App\Models\Announcement::active(); @endphp
            @if($active)
            <div class="flex items-start gap-3 p-3 rounded-xl text-sm"
                 style="{{ $active->type === 'danger' ? 'background:rgba(239,68,68,0.12);border:1px solid rgba(239,68,68,0.3);color:rgba(252,165,165,1)' : ($active->type === 'warning' ? 'background:rgba(245,158,11,0.12);border:1px solid rgba(245,158,11,0.3);color:rgba(253,211,77,1)' : 'background:rgba(59,130,246,0.12);border:1px solid rgba(59,130,246,0.3);color:rgba(147,197,253,1)') }}">
                <span class="shrink-0 mt-0.5">{{ $active->type === 'danger' ? '🚨' : ($active->type === 'warning' ? '⚠️' : 'ℹ️') }}</span>
                <div class="flex-1 min-w-0">
                    <p class="font-medium mb-0.5">Aktif Duyuru</p>
                    <p class="opacity-80 break-words">{{ $active->message }}</p>
                    @if($active->expires_at)
                    <p class="text-xs opacity-50 mt-1">Bitiş: {{ $active->expires_at->format('d.m.Y H:i') }}</p>
                    @endif
                </div>
                <button @click="clearAnnouncement()"
                    class="shrink-0 px-2 py-1 rounded-lg text-xs font-medium transition-all"
                    style="background:rgba(255,255,255,0.1)" onmouseover="this.style.background='rgba(255,255,255,0.2)'" onmouseout="this.style.background='rgba(255,255,255,0.1)'">
                    Kaldır
                </button>
            </div>
            @else
            <p class="text-xs text-zinc-500">Şu an aktif duyuru yok.</p>
            @endif

            {{-- Yeni duyuru formu --}}
            <div class="space-y-3">
                <textarea x-model="ann.message" rows="3" maxlength="500"
                    placeholder="Duyuru metnini buraya yazın... (maks 500 karakter)"
                    class="w-full rounded-xl px-3 py-2 text-sm resize-none outline-none placeholder-zinc-600"
                    style="background:rgba(255,255,255,0.05);border:1px solid rgba(255,255,255,0.08);color:#fff"></textarea>

                <div class="flex items-center gap-3">
                    <select x-model="ann.type" class="flex-1 rounded-xl px-3 py-2 text-sm outline-none"
                        style="background:rgba(255,255,255,0.05);border:1px solid rgba(255,255,255,0.08);color:#fff">
                        <option value="info">ℹ️ Bilgi (Mavi)</option>
                        <option value="warning">⚠️ Uyarı (Sarı)</option>
                        <option value="danger">🚨 Acil (Kırmızı)</option>
                    </select>
                    <input type="datetime-local" x-model="ann.expires_at"
                        class="flex-1 rounded-xl px-3 py-2 text-sm outline-none"
                        style="background:rgba(255,255,255,0.05);border:1px solid rgba(255,255,255,0.08);color:#fff;color-scheme:dark"
                        title="Bitiş tarihi (opsiyonel)">
                </div>

                <button @click="postAnnouncement()" :disabled="!ann.message.trim()"
                    class="w-full py-2 rounded-xl text-sm font-semibold transition-all disabled:opacity-30"
                    style="background:rgba(255,255,255,0.08);color:#fff"
                    onmouseover="if(!this.disabled)this.style.background='rgba(255,255,255,0.14)'" onmouseout="this.style.background='rgba(255,255,255,0.08)'">
                    <span x-show="!ann.sending">📢 Duyuruyu Yayınla</span>
                    <span x-show="ann.sending">Yayınlanıyor...</span>
                </button>
            </div>
        </div>
    </div>

    <!-- Uygulama Yayınlama -->
    <div class="mt-8 pt-6 border-t border-white/10">
        <h2 class="text-xl font-bold tracking-tight mb-4 text-white">📱 Android Uygulama Yayınla</h2>
        <div class="max-w-xl p-6 border rounded-2xl space-y-4" style="background:rgba(255,255,255,0.03);border-color:rgba(255,255,255,0.07)">
            @php $latestApp = \App\Models\AppRelease::latest()->first(); @endphp
            @if($latestApp)
            <div class="flex items-center gap-3 p-3 rounded-xl text-sm" style="background:rgba(16,185,129,0.12);border:1px solid rgba(16,185,129,0.3);color:rgba(167,243,208,1)">
                <span class="shrink-0 mt-0.5">✅</span>
                <div class="flex-1 min-w-0">
                    <p class="font-medium mb-0.5">Aktif Sürüm: v{{ $latestApp->version }}</p>
                    <p class="opacity-80 break-words text-xs">Drive: {{ $latestApp->drive_link }}</p>
                </div>
            </div>
            @endif

            <div class="space-y-3">
                <input type="text" x-model="app.version" placeholder="Sürüm (Örn: 1.0.0)"
                    class="w-full rounded-xl px-3 py-2 text-sm outline-none placeholder-zinc-600"
                    style="background:rgba(255,255,255,0.05);border:1px solid rgba(255,255,255,0.08);color:#fff">

                <input type="url" x-model="app.drive_link" placeholder="Google Drive Linki (https://drive.google.com/file/d/.../view)"
                    class="w-full rounded-xl px-3 py-2 text-sm outline-none placeholder-zinc-600"
                    style="background:rgba(255,255,255,0.05);border:1px solid rgba(255,255,255,0.08);color:#fff">

                <textarea x-model="app.notes" rows="2" placeholder="Sürüm Notları (Yenilikler vb.)"
                    class="w-full rounded-xl px-3 py-2 text-sm resize-none outline-none placeholder-zinc-600"
                    style="background:rgba(255,255,255,0.05);border:1px solid rgba(255,255,255,0.08);color:#fff"></textarea>

                <button @click="postAppRelease()" :disabled="!app.version.trim() || !app.drive_link.trim()"
                    class="w-full py-2 rounded-xl text-sm font-semibold transition-all disabled:opacity-30"
                    style="background:rgba(16,185,129,0.2);color:#fff"
                    onmouseover="if(!this.disabled)this.style.background='rgba(16,185,129,0.3)'" onmouseout="if(!this.disabled)this.style.background='rgba(16,185,129,0.2)'">
                    <span x-show="!app.sending">🚀 Uygulamayı Güncelle</span>
                    <span x-show="app.sending">Yükleniyor...</span>
                </button>
            </div>
        </div>
    </div>

    <!-- Maintenance -->
    <div class="mt-8 pt-6 border-t border-white/10">
        <h2 class="text-xl font-bold tracking-tight mb-4 text-red-500">Sistem Temizliği</h2>
        <div class="max-w-md p-6 border border-red-500/20 rounded-2xl" style="background:rgba(239,68,68,0.04)">
            <div class="space-y-4">
                <div>
                    <h3 class="text-sm font-medium text-zinc-300 mb-1">Bakım</h3>
                    <p class="text-xs text-zinc-500 mb-3">Sistemi rahatlatmak için eski mesajları arşivle/sil.</p>
                    <button @click="cleanOld()"
                        class="px-4 py-2 rounded-xl border text-sm font-medium transition-all"
                        style="border-color:rgba(245,158,11,0.4);color:rgba(251,191,36,1)"
                        onmouseover="this.style.background='rgba(245,158,11,0.08)'" onmouseout="this.style.background=''">
                        <span x-show="!cleaningOld">🧹 24h+ Mesajları Temizle</span>
                        <span x-show="cleaningOld">Temizleniyor...</span>
                    </button>
                </div>
                <div class="h-px bg-white/10"></div>
                <div>
                    <h3 class="text-sm font-medium text-red-300 mb-1">Acil Durum: Sıfırla</h3>
                    <p class="text-xs mb-3" style="color:rgba(252,165,165,0.5)">Tüm veri tabanını (mesajları) komple siler.</p>
                    <button @click="cleanAll()"
                        class="px-4 py-2 rounded-xl border text-sm font-medium transition-all"
                        style="border-color:rgba(239,68,68,0.4);color:rgba(248,113,113,1)"
                        onmouseover="this.style.background='rgba(239,68,68,0.1)'" onmouseout="this.style.background=''">
                        <span x-show="!cleaningAll">⚠️ Tüm Mesajları Sil</span>
                        <span x-show="cleaningAll">Siliniyor...</span>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('admin-scripts')
<script>
function adminDashboard() {
    return {
        cleaningOld: false, cleaningAll: false,
        ann: { message: '', type: 'info', expires_at: '', sending: false },
        app: { version: '', drive_link: '', notes: '', sending: false },

        async postAppRelease() {
            if (!this.app.version.trim() || !this.app.drive_link.trim()) return;
            this.app.sending = true;
            const r = await fetch(`/admin/app-release`, {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': CSRF, 'Content-Type': 'application/json' },
                body: JSON.stringify({ version: this.app.version, drive_link: this.app.drive_link, notes: this.app.notes }),
            });
            this.app.sending = false;
            if (r.ok) { showToast('Uygulama başarıyla güncellendi'); location.reload(); }
            else { const d = await r.json(); showToast(d.message || 'Hata oluştu', 'error'); }
        },

        async postAnnouncement() {
            if (!this.ann.message.trim()) return;
            this.ann.sending = true;
            const r = await fetch(`/admin/announcement`, {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': CSRF, 'Content-Type': 'application/json' },
                body: JSON.stringify({ message: this.ann.message, type: this.ann.type, expires_at: this.ann.expires_at || null }),
            });
            this.ann.sending = false;
            if (r.ok) { showToast('Duyuru yayınlandı'); this.ann.message = ''; this.ann.expires_at = ''; location.reload(); }
            else showToast('Hata oluştu', 'error');
        },

        async clearAnnouncement() {
            const r = await fetch(`/admin/announcement`, {
                method: 'DELETE',
                headers: { 'X-CSRF-TOKEN': CSRF },
            });
            if (r.ok) { showToast('Duyuru kaldırıldı'); location.reload(); }
        },
        async cleanOld() {
            if (!confirm('24 saatten eski tüm mesajları silmek istiyor musunuz?')) return;
            this.cleaningOld = true;
            const r = await fetch(`/admin/clean/old`, { method: 'POST', headers: { 'X-CSRF-TOKEN': CSRF } });
            const d = await r.json();
            this.cleaningOld = false;
            showToast(d.deleted + ' mesaj silindi');
        },
        async cleanAll() {
            if (!confirm('TÜM mesajları kalıcı olarak silmek üzeresiniz. Emin misiniz?')) return;
            if (!confirm('Son karar: Bu işlem geri alınamaz!')) return;
            this.cleaningAll = true;
            const r = await fetch(`/admin/clean/all`, { method: 'POST', headers: { 'X-CSRF-TOKEN': CSRF } });
            const d = await r.json();
            this.cleaningAll = false;
            showToast(d.deleted + ' mesaj silindi');
        }
    }
}
</script>
@endpush
