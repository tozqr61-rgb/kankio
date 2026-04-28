@extends('layouts.chat')

@section('chat-content')
<div class="flex flex-col h-full bg-transparent relative">
    <!-- Header -->
    <div class="h-20 shrink-0 flex items-center justify-between px-4 md:px-8 z-20 border-b"
         style="backdrop-filter:blur(8px);border-color:rgba(255,255,255,0.05)">
        <div class="flex items-center gap-4">
            <button @click="toggleLeft()"
                class="p-2 rounded-lg transition-colors"
                style="color:rgba(255,255,255,0.6)"
                onmouseover="this.style.color='#fff';this.style.background='rgba(255,255,255,0.05)'"
                onmouseout="this.style.color='rgba(255,255,255,0.6)';this.style.background=''">
                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5"/>
                </svg>
            </button>
            <span class="font-serif text-xl text-white font-thin tracking-wide">Kankio</span>
        </div>
    </div>

    <!-- Empty State -->
    <div class="flex-1 flex flex-col items-center justify-center space-y-4" style="color:rgba(113,113,122,1)">
        <div class="p-6 rounded-full" style="background:rgba(39,39,42,1)">
            <svg class="w-12 h-12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M20.25 8.511c.884.284 1.5 1.128 1.5 2.097v4.286c0 1.136-.847 2.1-1.98 2.193-.34.027-.68.052-1.02.072v3.091l-3-3c-1.354 0-2.694-.055-4.02-.163a2.115 2.115 0 01-.825-.242m9.345-8.334a2.126 2.126 0 00-.476-.095 48.64 48.64 0 00-8.048 0c-1.131.094-1.976 1.057-1.976 2.192v4.286c0 .837.46 1.58 1.155 1.951m9.345-8.334V6.637c0-1.621-1.152-3.026-2.76-3.235A48.455 48.455 0 0011.25 3c-2.115 0-4.198.137-6.24.402-1.608.209-2.76 1.614-2.76 3.235v6.226c0 1.621 1.152 3.026 2.76 3.235.577.075 1.157.14 1.74.194V21l4.155-4.155"/>
            </svg>
        </div>
        <p class="text-lg font-medium">Bir sohbet odası seçin veya oluşturun.</p>
        <p class="text-sm" style="color:rgba(63,63,70,1)">Sol panelden bir oda seçerek sohbete başlayın.</p>
    </div>
</div>
@endsection

@push('scripts')
<script>
    // Expose chatLayout to window for room-list
    document.addEventListener('alpine:init', () => {
        Alpine.store('layout', Alpine.reactive({}));
    });
    window.addEventListener('DOMContentLoaded', () => {
        setTimeout(() => {
            const el = document.querySelector('[x-data="chatLayout()"]');
            if (el && el._x_dataStack) window._chatLayout = el._x_dataStack[0];
        }, 100);
    });
</script>
@endpush
