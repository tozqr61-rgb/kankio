@extends('layouts.admin')

@section('admin-content')
<div x-data="adminRooms()" class="space-y-4">
    <h1 class="text-3xl font-bold tracking-tight text-white">Odalar ({{ count($rooms) }})</h1>

    <div class="space-y-3">
        @foreach($rooms as $room)
        <div class="flex items-center justify-between p-4 rounded-2xl border transition-colors"
             style="background:rgba(255,255,255,0.02);border-color:rgba(255,255,255,0.06)"
             onmouseover="this.style.background='rgba(255,255,255,0.05)'" onmouseout="this.style.background='rgba(255,255,255,0.02)'">
            <div class="flex items-center gap-4">
                <div class="h-10 w-10 rounded-xl flex items-center justify-center border"
                     style="background:rgba(99,102,241,0.15);border-color:rgba(99,102,241,0.2)">
                    <svg class="h-5 w-5" style="color:rgba(129,140,248,1)" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12.76c0 1.6 1.123 2.994 2.707 3.227 1.087.16 2.185.283 3.293.369V21l4.076-4.076a1.526 1.526 0 011.037-.443 48.282 48.282 0 005.68-.494c1.584-.233 2.707-1.626 2.707-3.228V6.741c0-1.602-1.123-2.995-2.707-3.228A48.394 48.394 0 0012 3c-2.392 0-4.744.175-7.043.513C3.373 3.746 2.25 5.14 2.25 6.741v6.018z"/>
                    </svg>
                </div>
                <div>
                    <p class="font-medium text-sm text-white">{{ $room->name }}</p>
                    <p class="text-xs text-zinc-500">{{ $room->type }} • {{ $room->created_at->format('d.m.Y') }}
                        @if($room->creator) • {{ $room->creator->username }} @endif
                    </p>
                </div>
            </div>
            <button @click="deleteRoom({{ $room->id }}, $el.closest('div.flex.items-center.justify-between'))"
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
function adminRooms() {
    return {
        async deleteRoom(roomId, row) {
            if (!confirm('Odayı kalıcı olarak silmek istediğinize emin misiniz?')) return;
            const r = await fetch(`/admin/rooms/${roomId}`, { method: 'DELETE', headers: { 'X-CSRF-TOKEN': CSRF } });
            if (r.ok) { showToast('Oda silindi'); row.remove(); }
            else showToast('Hata oluştu', 'error');
        }
    }
}
</script>
@endpush
