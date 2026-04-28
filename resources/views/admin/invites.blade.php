@extends('layouts.admin')

@section('admin-content')
<div x-data="adminInvites()" class="space-y-4">
    <h1 class="text-3xl font-bold tracking-tight text-white">Davetiyeler</h1>

    <button @click="createCode()"
        class="flex items-center gap-2 px-4 py-2.5 rounded-xl font-medium text-sm transition-all"
        style="background:rgba(16,185,129,1);color:#000"
        onmouseover="this.style.background='rgba(5,150,105,1)'" onmouseout="this.style.background='rgba(16,185,129,1)'">
        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z"/>
        </svg>
        Yeni Davetiye Oluştur
    </button>

    <div class="space-y-3" id="invite-list">
        @foreach($codes as $code)
        <div class="flex items-center justify-between p-4 rounded-2xl border transition-colors" id="invite-{{ $code->id }}"
             style="background:rgba(255,255,255,0.02);border-color:rgba(255,255,255,0.06)"
             onmouseover="this.style.background='rgba(255,255,255,0.05)'" onmouseout="this.style.background='rgba(255,255,255,0.02)'">
            <div class="flex items-center gap-4">
                <div class="h-10 w-10 rounded-xl flex items-center justify-center border"
                     style="{{ $code->is_used ? 'background:rgba(239,68,68,0.1);border-color:rgba(239,68,68,0.2);color:rgba(248,113,113,1)' : 'background:rgba(16,185,129,0.1);border-color:rgba(16,185,129,0.2);color:rgba(52,211,153,1)' }}">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z"/>
                    </svg>
                </div>
                <div>
                    <p class="font-mono text-xl font-bold tracking-wider text-white">{{ $code->code }}</p>
                    <p class="text-xs text-zinc-500">
                        {{ $code->is_used ? 'Kullanıldı' : 'Aktif' }}
                        @if($code->expires_at) • Son: {{ $code->expires_at->format('d.m.Y') }} @endif
                    </p>
                </div>
            </div>
            <button @click="deleteCode({{ $code->id }})"
                class="p-2 rounded-lg border transition-all"
                style="color:rgba(113,113,122,1);border-color:rgba(255,255,255,0.06)"
                onmouseover="this.style.color='rgba(248,113,113,1)';this.style.background='rgba(239,68,68,0.08)'" onmouseout="this.style.color='rgba(113,113,122,1)';this.style.background=''">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0"/>
                </svg>
            </button>
        </div>
        @endforeach
    </div>
</div>
@endsection

@push('admin-scripts')
<script>
function adminInvites() {
    return {
        async createCode() {
            const r = await fetch(`/admin/invites`, { method: 'POST', headers: { 'X-CSRF-TOKEN': CSRF } });
            const d = await r.json();
            showToast('Kod oluşturuldu: ' + d.code);
            setTimeout(() => location.reload(), 1000);
        },
        async deleteCode(id) {
            const r = await fetch(`/admin/invites/${id}`, { method: 'DELETE', headers: { 'X-CSRF-TOKEN': CSRF } });
            if (r.ok) { showToast('Davetiye silindi'); document.getElementById('invite-' + id)?.remove(); }
            else showToast('Hata oluştu', 'error');
        }
    }
}
</script>
@endpush
