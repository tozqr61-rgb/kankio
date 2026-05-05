@extends('layouts.admin')

@section('admin-content')
<div class="space-y-6" x-data="oversightPanel()">
    <div>
        <h1 class="text-3xl font-bold tracking-tight text-white">Denetim Erişimi</h1>
        <p class="mt-2 max-w-2xl text-sm text-zinc-400">Gizli oda erişimleri gerekçe ile açılır ve audit log'a kaydedilir. Erişimler görünmez değildir; denetim amaçlıdır.</p>
    </div>

    <div class="grid gap-6 lg:grid-cols-[1fr_0.8fr]">
        <section class="rounded-2xl border p-5" style="background:rgba(255,255,255,0.03);border-color:rgba(255,255,255,0.07)">
            <h2 class="text-sm font-semibold text-zinc-200">Oda Seç</h2>
            <div class="mt-4 space-y-3">
                <select x-model="roomId" class="w-full rounded-xl px-3 py-2 text-sm outline-none" style="background:rgba(255,255,255,0.05);border:1px solid rgba(255,255,255,0.08);color:#fff">
                    <option value="">Oda seç</option>
                    @foreach($rooms as $room)
                        <option value="{{ $room->id }}">#{{ $room->name }} — {{ $room->type }}{{ $room->is_archived ? ' (arşiv)' : '' }}</option>
                    @endforeach
                </select>
                <textarea x-model="reason" rows="4" maxlength="500" placeholder="Denetim gerekçesi"
                          class="w-full rounded-xl px-3 py-2 text-sm resize-none outline-none placeholder-zinc-600"
                          style="background:rgba(255,255,255,0.05);border:1px solid rgba(255,255,255,0.08);color:#fff"></textarea>
                <button @click="startAccess()" :disabled="!roomId || reason.trim().length < 8 || sending"
                        class="w-full rounded-xl py-2.5 text-sm font-semibold disabled:opacity-40"
                        style="background:rgba(16,185,129,0.2);color:#fff">Denetim Erişimi Başlat</button>
                <p class="text-xs text-zinc-500">Gerekçe en az 8 karakter olmalı. Erişim penceresi 30 dakika geçerlidir.</p>
            </div>
        </section>

        <section class="rounded-2xl border p-5" style="background:rgba(255,255,255,0.03);border-color:rgba(255,255,255,0.07)">
            <h2 class="text-sm font-semibold text-zinc-200">Son Denetimler</h2>
            <div class="mt-4 space-y-3">
                @forelse($recentAccesses as $access)
                    <div class="rounded-xl border p-3 text-xs" style="border-color:rgba(255,255,255,0.06);background:rgba(255,255,255,0.03)">
                        <div class="flex items-center justify-between gap-3">
                            <span class="font-semibold text-zinc-200">{{ $access->actor?->username ?? 'Bilinmeyen' }}</span>
                            <span class="text-zinc-500">{{ $access->created_at->diffForHumans() }}</span>
                        </div>
                        <p class="mt-2 text-zinc-400">Oda: {{ $access->payload['room_name'] ?? $access->target_id }}</p>
                        <p class="mt-1 text-zinc-500">{{ $access->payload['reason'] ?? '' }}</p>
                    </div>
                @empty
                    <p class="text-xs text-zinc-500">Henüz denetim erişimi yok.</p>
                @endforelse
            </div>
        </section>
    </div>
</div>
@endsection

@push('admin-scripts')
<script>
function oversightPanel() {
    return {
        roomId: '',
        reason: '',
        sending: false,
        async startAccess() {
            this.sending = true;
            try {
                const r = await fetch('/admin/oversight/access', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
                    body: JSON.stringify({ room_id: this.roomId, reason: this.reason }),
                });
                const data = await r.json();
                if (!r.ok) throw new Error(data.message || 'Denetim erişimi açılamadı');
                window.location.href = data.redirect;
            } catch (e) {
                showToast(e.message || 'Denetim erişimi açılamadı', 'error');
            } finally {
                this.sending = false;
            }
        },
    };
}
</script>
@endpush
