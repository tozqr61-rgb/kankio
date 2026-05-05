@extends('layouts.admin')

@section('admin-content')
<div x-data="adminUsers()" class="space-y-4">
    <h1 class="text-3xl font-bold tracking-tight text-white">Kullanıcılar ({{ count($users) }})</h1>

    <div class="space-y-3">
        @foreach($users as $user)
        <div class="flex items-center justify-between p-4 rounded-2xl border transition-colors"
             style="background:rgba(255,255,255,0.02);border-color:rgba(255,255,255,0.06)"
             onmouseover="this.style.background='rgba(255,255,255,0.05)'" onmouseout="this.style.background='rgba(255,255,255,0.02)'">
            <div class="flex items-center gap-4">
                <div class="h-10 w-10 rounded-full overflow-hidden shrink-0" style="background:rgba(39,39,42,1)">
                    @if($user->avatar_url)
                    <img src="{{ $user->avatar_url }}" class="h-full w-full object-cover">
                    @else
                    <div class="h-full w-full flex items-center justify-center text-xs font-bold text-zinc-400">{{ strtoupper(substr($user->username, 0, 1)) }}</div>
                    @endif
                </div>
                <div>
                    <p class="font-medium text-sm text-white flex items-center gap-2">
                        {{ $user->username }}
                        @if($user->role === 'admin')
                        <span class="text-[10px] px-2 py-0.5 rounded-full border" style="background:rgba(244,63,94,0.15);color:rgba(251,113,133,1);border-color:rgba(244,63,94,0.25)">Yönetici</span>
                        @endif
                        @if($user->role === 'oversight_admin')
                        <span class="text-[10px] px-2 py-0.5 rounded-full border" style="background:rgba(59,130,246,0.15);color:rgba(147,197,253,1);border-color:rgba(59,130,246,0.25)">Denetim</span>
                        @endif
                        @if($user->is_bot)
                        <span class="text-[10px] px-2 py-0.5 rounded-full border" style="background:rgba(168,85,247,0.15);color:rgba(216,180,254,1);border-color:rgba(168,85,247,0.25)">Bot</span>
                        @endif
                        @if($user->deactivated_at)
                        <span class="text-[10px] px-2 py-0.5 rounded-full border" style="background:rgba(113,113,122,0.15);color:rgba(212,212,216,1);border-color:rgba(113,113,122,0.25)">Pasif</span>
                        @endif
                        @if($user->is_banned)
                        <span class="text-[10px] px-2 py-0.5 rounded-full border" style="background:rgba(239,68,68,0.15);color:rgba(248,113,113,1);border-color:rgba(239,68,68,0.25)">Yasaklı</span>
                        @endif
                    </p>
                    <p class="text-xs text-zinc-500">{{ $user->email }} • {{ $user->created_at->format('d.m.Y') }}</p>
                </div>
            </div>
            <div class="flex items-center gap-2">
                @if(auth()->id() !== $user->id)
                <button @click="toggleAdmin({{ $user->id }}, '{{ $user->role }}', $el)"
                    class="px-3 py-1.5 rounded-lg text-xs font-medium border transition-all"
                    style="{{ $user->role === 'admin' ? 'border-color:rgba(99,102,241,0.3);color:rgba(165,180,252,1)' : 'border-color:rgba(52,211,153,0.3);color:rgba(52,211,153,1)' }}">
                    {{ $user->role === 'admin' ? 'Yöneticilikten Al' : 'Yönetici Yap' }}
                </button>
                <button @click="setRole({{ $user->id }}, '{{ $user->role === 'oversight_admin' ? 'user' : 'oversight_admin' }}')"
                    class="px-3 py-1.5 rounded-lg text-xs font-medium border transition-all"
                    style="border-color:rgba(59,130,246,0.3);color:rgba(147,197,253,1)">
                    {{ $user->role === 'oversight_admin' ? 'Denetimden Al' : 'Denetim Yap' }}
                </button>
                @endif
                <button @click="banUser({{ $user->id }}, {{ $user->is_banned ? 'true' : 'false' }}, $el)"
                    class="px-3 py-1.5 rounded-lg text-xs font-medium border transition-all"
                    style="{{ $user->is_banned ? 'border-color:rgba(161,161,170,0.3);color:rgba(161,161,170,1)' : 'border-color:rgba(239,68,68,0.3);color:rgba(248,113,113,1)' }}">
                    {{ $user->is_banned ? 'Yasağı Kaldır' : 'Yasakla' }}
                </button>
                <button @click="deleteUser({{ $user->id }}, '{{ $user->username }}', $el.closest('div.flex.items-center.justify-between'))"
                    class="p-2 rounded-lg border transition-all"
                    style="border-color:rgba(255,255,255,0.06);color:rgba(113,113,122,1)"
                    onmouseover="this.style.color='rgba(248,113,113,1)';this.style.borderColor='rgba(239,68,68,0.3)';this.style.background='rgba(239,68,68,0.08)'"
                    onmouseout="this.style.color='rgba(113,113,122,1)';this.style.borderColor='rgba(255,255,255,0.06)';this.style.background=''">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0"/>
                    </svg>
                </button>
            </div>
        </div>
        @endforeach
    </div>
</div>
@endsection

@push('admin-scripts')
<script>
function adminUsers() {
    return {
        async toggleAdmin(userId, currentRole, btn) {
            const action = currentRole === 'admin' ? 'yöneticilikten almak' : 'yönetici yapmak';
            if (!confirm(`Bu kullanıcıyı ${action} istediğinize emin misiniz?`)) return;
            const r = await fetch(`/admin/users/${userId}/role`, {
                method: 'POST', headers: { 'X-CSRF-TOKEN': CSRF }
            });
            const d = await r.json();
            if (d.error) { showToast(d.error, 'error'); return; }
            showToast(`Kullanıcı rolü güncellendi: ${d.role === 'admin' ? 'Yönetici' : 'Üye'}`);
            setTimeout(() => location.reload(), 800);
        },

        async banUser(userId, isBanned, btn) {
            const action = isBanned ? 'yasağını kaldırmak' : 'yasaklamak';
            if (!confirm(`Kullanıcıyı ${action} istediğinize emin misiniz?`)) return;
            const r = await fetch(`/admin/users/${userId}/ban`, { method: 'POST', headers: { 'X-CSRF-TOKEN': CSRF } });
            const d = await r.json();
            showToast('Kullanıcı durumu güncellendi');
            setTimeout(() => location.reload(), 800);
        },
        async deleteUser(userId, username, row) {
            if (!confirm(`"${username}" adlı kullanıcı pasifleştirilip anonimleştirilecek. Devam edilsin mi?`)) return;
            if (!confirm('Son karar: Hesap girişe kapatılsın ve kimliği anonimleştirilsin mi?')) return;
            const r = await fetch(`/admin/users/${userId}`, { method: 'DELETE', headers: { 'X-CSRF-TOKEN': CSRF } });
            if (r.ok) { showToast('Kullanıcı pasifleştirildi'); row.remove(); }
            else showToast('Hata oluştu', 'error');
        },

        async setRole(userId, role) {
            if (!confirm(`Kullanıcı rolü "${role}" yapılacak. Emin misiniz?`)) return;
            const r = await fetch(`/admin/users/${userId}/role`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
                body: JSON.stringify({ role })
            });
            const d = await r.json();
            if (d.error) { showToast(d.error, 'error'); return; }
            showToast('Kullanıcı rolü güncellendi');
            setTimeout(() => location.reload(), 800);
        }
    }
}
</script>
@endpush
