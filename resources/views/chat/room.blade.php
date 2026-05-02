@extends('layouts.chat')

@section('chat-content')

{{-- ── Android / Mobile Autoplay Unlock Overlay ──
     Chrome/Android blocks audio until the first user gesture.
     This overlay captures that gesture and unlocks AudioContext globally.
     Hidden on desktop (pointer:fine) and when already unlocked. --}}
{{-- Avatar Lightbox --}}
<div x-data="{}" x-show="$store.lightbox && $store.lightbox.src"
     x-cloak
     @click="$store.lightbox.src = null"
     @keydown.escape.window="$store.lightbox.src = null"
     class="fixed inset-0 z-[9500] flex items-center justify-center"
     style="background:rgba(0,0,0,0.88);backdrop-filter:blur(20px)">
    <div @click.stop class="flex flex-col items-center gap-4">
        <img :src="$store.lightbox && $store.lightbox.src"
             class="rounded-2xl shadow-2xl object-contain"
             style="max-width:min(480px,90vw);max-height:80vh;border:1px solid rgba(255,255,255,0.1)">
        <p class="text-sm font-medium" style="color:rgba(255,255,255,0.6)"
           x-text="$store.lightbox && $store.lightbox.name"></p>
    </div>
    <button @click="$store.lightbox.src = null"
            class="absolute top-5 right-5 h-10 w-10 flex items-center justify-center rounded-full"
            style="background:rgba(255,255,255,0.08);color:rgba(255,255,255,0.7)">
        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
        </svg>
    </button>
</div>

<div id="autoplay-overlay"
     style="display:none;position:fixed;inset:0;z-index:9000;
            background:rgba(0,0,0,0.92);backdrop-filter:blur(16px);
            flex-direction:column;align-items:center;justify-content:center;gap:1.5rem;">
    <img src="{{ url('icons/icon.svg') }}" style="width:4rem;height:4rem;border-radius:1rem;opacity:0.9" alt="Kankio">
    <p style="color:rgba(255,255,255,0.85);font-size:1rem;font-weight:500;letter-spacing:0.02em;text-align:center;max-width:20rem;line-height:1.6">
        Sesli sohbet ve müzik için <br><strong style="color:#fff">ekrana bir kez dokunun</strong>
    </p>
    <button onclick="unlockAudio(this)"
            style="padding:0.875rem 2.5rem;border-radius:999px;font-size:0.9rem;font-weight:600;
                   background:rgba(52,211,153,1);color:#000;border:none;cursor:pointer;
                   box-shadow:0 0 30px rgba(52,211,153,0.4)">
        Devam Et
    </button>
</div>
<script>
(function() {
    const KEY = 'kankio_audio_unlocked';
    if (sessionStorage.getItem(KEY)) return;
    /* Only show on mobile/touch devices */
    if (window.matchMedia('(pointer:fine)').matches) return;
    const overlay = document.getElementById('autoplay-overlay');
    if (overlay) overlay.style.display = 'flex';
})();

function unlockAudio(btn) {
    try {
        const ctx = new (window.AudioContext || window.webkitAudioContext)();
        const buf = ctx.createBuffer(1, 1, 22050);
        const src = ctx.createBufferSource();
        src.buffer = buf; src.connect(ctx.destination); src.start(0);
        ctx.resume().catch(() => {});
        window._audioUnlocked = true;
        sessionStorage.setItem('kankio_audio_unlocked', '1');
    } catch(e) {}
    const overlay = document.getElementById('autoplay-overlay');
    if (overlay) overlay.style.display = 'none';
}
</script>

<div class="flex bg-transparent relative select-none"
     style="height:100dvh;overflow:hidden;"
     x-data="chatRoom()" x-init="init()">

{{-- ── LEFT: messages column ── --}}
<div class="flex flex-col flex-1 min-w-0 h-full">

    <!-- Header -->
    <div class="h-20 shrink-0 flex items-center justify-between px-4 md:px-8 z-20 border-b"
         style="backdrop-filter:blur(8px);border-color:rgba(255,255,255,0.05)">
        <div class="flex items-center gap-4">
            <button @click="$dispatch('toggle-left-sidebar')"
                class="p-2 rounded-lg transition-colors"
                style="color:rgba(255,255,255,0.6)"
                onmouseover="this.style.color='#fff';this.style.background='rgba(255,255,255,0.05)'"
                onmouseout="this.style.color='rgba(255,255,255,0.6)';this.style.background=''">
                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5"/>
                </svg>
            </button>
            <div class="flex flex-col">
                <div class="flex items-center gap-2">
                    <span class="font-serif text-xl md:text-2xl text-white font-thin tracking-wide"># <span x-text="_roomName">{{ $room->name }}</span></span>
                    <div class="w-1.5 h-1.5 rounded-full"
                         :class="connected ? 'bg-emerald-500' : 'bg-amber-500 animate-pulse'"
                         :style="connected ? 'box-shadow:0 0 10px rgba(16,185,129,0.5)' : ''"
                         :title="connected ? 'Bağlı' : 'Bağlanıyor...'"></div>
                </div>
                <span class="text-[10px] tracking-widest uppercase mt-1 hidden md:block" style="color:rgba(255,255,255,0.25)">Burada herkes eşit</span>
            </div>
        </div>

        <!-- Header Right Actions -->
        <div class="flex items-center gap-2">
            <!-- Desktop-only voice sidebar toggle -->
            <button @click="musicOpen = !musicOpen"
                class="hidden lg:flex relative p-2 rounded-lg transition-colors items-center justify-center"
                :style="musicOpen ? 'color:#fff;background:rgba(255,255,255,0.08)' : 'color:rgba(255,255,255,0.6)'"
                title="Ses">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 18.75a6 6 0 006-6v-1.5m-6 7.5a6 6 0 01-6-6v-1.5m6 7.5v3.75m-3.75 0h7.5M12 15.75a3 3 0 01-3-3V4.5a3 3 0 116 0v8.25a3 3 0 01-3 3z"/>
                </svg>
                <div x-show="voiceState.in_voice" class="absolute top-1 right-1 h-2 w-2 rounded-full border border-black"
                     :class="voiceState.is_muted ? 'bg-amber-500' : 'bg-emerald-500 animate-pulse'"></div>
            </button>
            <!-- Notification Bell -->
            <div class="relative" x-data="{ bellOpen: false }">
                <button @click="bellOpen = !bellOpen"
                    class="relative p-2 rounded-lg transition-colors"
                    style="color:rgba(255,255,255,0.6)"
                    onmouseover="this.style.color='#fff'" onmouseout="this.style.color='rgba(255,255,255,0.6)'">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 005.454-1.31A8.967 8.967 0 0118 9.75v-.7V9A6 6 0 006 9v.75a8.967 8.967 0 01-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 01-5.714 0m5.714 0a3 3 0 11-5.714 0"/>
                    </svg>
                    <div class="absolute -bottom-0.5 -right-0.5 h-2 w-2 rounded-full border border-black"
                         :class="connected ? 'bg-emerald-500' : 'bg-amber-500 animate-pulse'"
                         style="box-shadow:0 0 6px rgba(16,185,129,0.4)"></div>
                </button>
                <div x-show="bellOpen" @click.away="bellOpen = false" x-transition
                     class="absolute right-0 top-full mt-2 w-72 rounded-2xl border shadow-2xl overflow-hidden z-50"
                     style="background:rgba(9,9,11,0.95);border-color:rgba(39,39,42,1);backdrop-filter:blur(24px)">
                    <div class="p-3 border-b flex items-center justify-between" style="border-color:rgba(255,255,255,0.05)">
                        <h4 class="font-medium text-white text-sm">Bildirimler</h4>
                        <span class="text-[10px] uppercase font-bold px-1.5 py-0.5 rounded"
                              :style="connected ? 'background:rgba(16,185,129,0.1);color:rgba(52,211,153,1)' : 'background:rgba(239,68,68,0.1);color:rgba(248,113,113,1)'"
                              x-text="connected ? 'BAĞLI' : 'BAĞLANIYOR'"></span>
                    </div>
                    <div class="p-2 text-center text-xs" style="color:rgba(113,113,122,1)">Yeni bildirim yok</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Archived messages toggle -->
    <div x-show="archivedCount > 0" class="px-4 md:px-8 pt-2"
         :class="mobileTab !== 'chat' ? 'hidden lg:block' : ''">
        <button @click="toggleArchived()"
            class="w-full flex items-center justify-center gap-2 py-2 rounded-xl text-xs font-medium transition-all"
            style="background:rgba(255,255,255,0.03);color:rgba(113,113,122,1);border:1px solid rgba(255,255,255,0.06)"
            onmouseover="this.style.background='rgba(255,255,255,0.06)'" onmouseout="this.style.background='rgba(255,255,255,0.03)'">
            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M20.25 7.5l-.625 10.632a2.25 2.25 0 01-2.247 2.118H6.622a2.25 2.25 0 01-2.247-2.118L3.75 7.5M10 11.25h4M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125z"/>
            </svg>
            <span x-text="showArchived ? 'Arşivi Gizle' : 'Eski Mesajlar (' + archivedCount + ')'"></span>
        </button>
    </div>

    <!-- Archived messages panel -->
    <div x-show="showArchived" x-transition class="px-4 md:px-8 py-2 space-y-1 border-b"
         :class="mobileTab !== 'chat' ? 'hidden lg:block' : ''"
         style="border-color:rgba(255,255,255,0.06);max-height:40vh;overflow-y:auto">
        <p class="text-[10px] uppercase tracking-wider text-center mb-2" style="color:rgba(113,113,122,1)">Arşivlenmiş Mesajlar</p>
        <template x-for="msg in archivedMessages" :key="'arch-'+msg.id">
            <div class="flex items-start gap-2 py-1.5 px-2 rounded-lg" style="background:rgba(255,255,255,0.02);opacity:0.7">
                <div class="h-5 w-5 rounded-full flex items-center justify-center text-[8px] font-bold shrink-0 mt-0.5"
                     style="background:rgba(39,39,42,1);color:rgba(113,113,122,1)"
                     x-text="msg.sender?.username?.[0]?.toUpperCase() || '?'"></div>
                <div class="min-w-0">
                    <span class="text-[10px] font-medium" style="color:rgba(161,161,170,1)" x-text="msg.sender?.username || 'Silinmiş'"></span>
                    <span class="text-[9px] ml-1" style="color:rgba(82,82,91,1)" x-text="formatTime(msg.created_at)"></span>
                    <p class="text-xs break-words" style="color:rgba(161,161,170,0.8)" x-text="msg.content"></p>
                </div>
            </div>
        </template>
        <div x-show="archivedLoading" class="text-center py-2">
            <span class="text-xs" style="color:rgba(113,113,122,1)">Yükleniyor...</span>
        </div>
        <button x-show="archivedHasMore && !archivedLoading" @click="loadArchived(archivedPage + 1)"
            class="w-full py-2 text-center text-xs rounded-lg transition-colors"
            style="color:rgba(99,102,241,0.8);background:rgba(99,102,241,0.05)"
            onmouseover="this.style.background='rgba(99,102,241,0.1)'" onmouseout="this.style.background='rgba(99,102,241,0.05)'">
            Daha fazla yükle
        </button>
    </div>

    <!-- Messages Area -->
    <div class="flex-1 overflow-y-auto scrollable px-4 md:px-8 py-4 space-y-1"
         :class="mobileTab !== 'chat' ? 'hidden lg:block' : ''"
         id="messages-container" x-ref="msgContainer">
        <template x-for="(msg, idx) in messages" :key="msg.id">
            <div>
                <!-- System Message (plain rooms) -->
                <template x-if="msg.is_system_message && roomType !== 'announcements'">
                    <div class="flex w-full justify-center my-4">
                        <span class="text-[10px] tracking-wider rounded-xl px-3 py-1.5 border text-center"
                              style="color:rgba(113,113,122,1);background:rgba(255,255,255,0.03);border-color:rgba(255,255,255,0.05);font-family:monospace;white-space:pre-line;max-width:80%"
                              x-text="msg.content"></span>
                    </div>
                </template>

                <!-- Announcement Card -->
                <template x-if="roomType === 'announcements'">
                    <div class="group relative w-full my-3 rounded-2xl overflow-hidden"
                         style="background:rgba(13,13,17,0.95);border:1px solid rgba(99,102,241,0.18);box-shadow:0 4px 32px rgba(0,0,0,0.5),inset 0 0 0 1px rgba(99,102,241,0.06)">
                        <!-- Left accent bar -->
                        <div class="absolute left-0 top-0 bottom-0 w-1 rounded-l-2xl" style="background:linear-gradient(to bottom,rgba(99,102,241,1),rgba(139,92,246,0.6))"></div>
                        <div class="pl-5 pr-4 py-4">
                            <!-- Header row -->
                            <div class="flex items-start justify-between gap-3 mb-2">
                                <div class="flex items-center gap-2.5">
                                    <span class="text-lg select-none">📢</span>
                                    <p class="text-base font-semibold text-white leading-tight" x-text="msg.title || 'Duyuru'"></p>
                                </div>
                                <template x-if="isAdmin">
                                    <button @click.stop="deleteMsg(msg.id)"
                                            class="opacity-0 group-hover:opacity-100 h-7 w-7 rounded-lg flex items-center justify-center shrink-0 transition-all"
                                            style="color:rgba(248,113,113,0.7);background:rgba(239,68,68,0.1)" title="Sil">
                                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0"/>
                                        </svg>
                                    </button>
                                </template>
                            </div>
                            <!-- Body -->
                            <p class="text-sm leading-relaxed break-words" style="color:rgba(212,212,216,1)" x-text="msg.content"></p>
                            <!-- Footer -->
                            <div class="flex items-center gap-2 mt-3 pt-3" style="border-top:1px solid rgba(255,255,255,0.05)">
                                <div class="h-5 w-5 rounded-full flex items-center justify-center text-[9px] font-bold shrink-0"
                                     style="background:rgba(244,63,94,0.2);color:rgba(251,113,133,1)">
                                    <span x-text="msg.sender?.username?.[0]?.toUpperCase()"></span>
                                </div>
                                <span class="text-[10px] font-medium" style="color:rgba(251,113,133,0.9)" x-text="msg.sender?.username"></span>
                                <span class="text-[10px]" style="color:rgba(63,63,70,1)">·</span>
                                <span class="text-[10px]" style="color:rgba(63,63,70,1)" x-text="formatTime(msg.created_at)"></span>
                            </div>
                        </div>
                    </div>
                </template>

                <!-- Regular Message (non-announcement rooms only) -->
                <template x-if="!msg.is_system_message && roomType !== 'announcements'">
                    <div class="group flex w-full mt-2 max-w-3xl relative"
                         :class="msg.sender?.id == currentUser.id ? 'ml-auto justify-end' : ''">

                        <!-- Avatar (others) -->
                        <template x-if="msg.sender?.id != currentUser.id">
                            <div :class="isContinual(idx) ? 'opacity-0' : ''" class="mr-3 shrink-0">
                                <div class="h-8 w-8 rounded-full flex items-center justify-center overflow-hidden border shadow-sm"
                                     :style="msg.sender?.role === 'admin'
                                         ? 'background:#18181b;border-color:rgba(244,63,94,0.3);box-shadow:0 0 0 2px rgba(244,63,94,0.1)'
                                         : 'background:#27272a;border-color:rgba(255,255,255,0.08)'">
                                    <template x-if="msg.sender?.avatar_url">
                                        <img :src="msg.sender.avatar_url" :alt="msg.sender.username"
                                             class="h-full w-full object-cover cursor-zoom-in"
                                             @click.stop="$store.lightbox = { src: msg.sender.avatar_url, name: msg.sender.username }">
                                    </template>
                                    <template x-if="!msg.sender?.avatar_url">
                                        <span class="text-xs font-bold"
                                              :style="msg.sender?.role === 'admin' ? 'color:rgba(244,63,94,1)' : 'color:rgba(113,113,122,1)'"
                                              x-text="msg.sender?.username?.[0]?.toUpperCase()"></span>
                                    </template>
                                </div>
                            </div>
                        </template>

                        <div class="flex flex-col w-full max-w-[82%] md:max-w-[75%]"
                             :class="msg.sender?.id == currentUser.id ? 'items-end' : 'items-start'">
                            <!-- Username -->
                            <template x-if="!isContinual(idx) && msg.sender?.id != currentUser.id">
                                <span class="text-[10px] mb-1 ml-2 font-medium tracking-wide flex items-center gap-2"
                                      :style="`color:${msg.sender?.role === 'admin' ? 'rgba(251,113,133,1)' : _userColor(msg.sender?.id).text}`">
                                    <span x-text="msg.sender?.username"></span>
                                    <template x-if="msg.sender?.role === 'admin'">
                                        <span class="flex items-center gap-1 px-1.5 py-0.5 rounded text-[8px] font-bold uppercase tracking-wider text-white"
                                              style="background:rgba(185,28,28,0.9);border:1px solid rgba(239,68,68,1)">
                                            🦁 YÖNETİCİ
                                        </span>
                                    </template>
                                    <span class="text-[9px]" style="color:rgba(63,63,70,1)" x-text="formatTime(msg.created_at)"></span>
                                </span>
                            </template>

                            <!-- Bubble -->
                            <div class="relative group/bubble px-4 py-2 text-sm leading-relaxed rounded-2xl shadow-sm cursor-pointer select-none transition-all duration-300 hover:shadow-md"
                                 :class="isContinual(idx) ? 'mt-0.5' : 'mt-1'"
                                 :style="msg.sender?.id == currentUser.id
                                     ? 'background:linear-gradient(135deg,rgba(79,70,229,0.9),rgba(37,99,235,0.9));color:#fff;border-radius:1rem 1rem 0.25rem 1rem'
                                     : msg.sender?.role === 'admin'
                                         ? 'background:linear-gradient(135deg,#18181b,rgba(136,19,55,0.3));color:rgba(255,228,230,1);border-radius:1rem 1rem 1rem 0.25rem;border:1px solid rgba(244,63,94,0.18);box-shadow:0 0 15px -5px rgba(244,63,94,0.1)'
                                         : `background:rgba(18,18,22,0.95);color:rgba(212,212,216,1);border-radius:1rem 1rem 1rem 0.25rem;border:1px solid ${_userColor(msg.sender?.id).border};box-shadow:inset 0 0 0 1px ${_userColor(msg.sender?.id).border}`"
                                 @dblclick="setReply(msg)">

                                <!-- Reply context -->
                                <template x-if="msg.reply_message">
                                    <div class="mb-2 pl-2 border-l-2 text-xs truncate max-w-[200px]"
                                         style="border-color:rgba(255,255,255,0.3);color:rgba(255,255,255,0.45)">
                                        <span class="text-[9px] uppercase tracking-wider block opacity-70">Yanıt</span>
                                        <span x-text="msg.reply_message.username + ': ' + msg.reply_message.content"></span>
                                    </div>
                                </template>

                                <!-- Voice message player -->
                                <template x-if="msg.audio_url">
                                    <div class="flex items-center gap-2 min-w-[180px]" @click.stop>
                                        <button @click.stop="playAudio(msg)"
                                            class="h-8 w-8 rounded-full flex items-center justify-center shrink-0 transition-all"
                                            :style="(_playingAudioId === msg.id) ? 'background:rgba(239,68,68,0.3);color:rgba(248,113,113,1)' : 'background:rgba(255,255,255,0.15);color:#fff'">
                                            <template x-if="_playingAudioId !== msg.id">
                                                <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg>
                                            </template>
                                            <template x-if="_playingAudioId === msg.id">
                                                <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 24 24"><path d="M6 19h4V5H6v14zm8-14v14h4V5h-4z"/></svg>
                                            </template>
                                        </button>
                                        <div class="flex-1 flex flex-col gap-0.5">
                                            <div class="h-1 rounded-full overflow-hidden" style="background:rgba(255,255,255,0.15)">
                                                <div class="h-full rounded-full transition-all duration-300"
                                                     :style="'width:' + (_playingAudioId === msg.id ? _audioProgress : 0) + '%;background:rgba(99,102,241,0.9)'"></div>
                                            </div>
                                            <span class="text-[9px] opacity-60" x-text="(msg.audio_duration ? msg.audio_duration + 's' : '')"></span>
                                        </div>
                                    </div>
                                </template>

                                <template x-if="!msg.audio_url">
                                    <span class="relative z-10 break-words" x-text="msg.content"></span>
                                </template>

                                <div class="text-[9px] mt-1 text-right opacity-70"
                                     :style="msg.sender?.id == currentUser.id ? 'color:rgba(199,210,254,1)' : 'color:rgba(113,113,122,1)'"
                                     x-text="formatTime(msg.created_at)"></div>

                                <!-- Floating actions -->
                                <div class="absolute top-1/2 -translate-y-1/2 flex items-center gap-1 opacity-0 group-hover/bubble:opacity-100 transition-all scale-0 group-hover/bubble:scale-100 z-20"
                                     :class="msg.sender?.id == currentUser.id ? '-left-4 -translate-x-full pr-2' : '-right-4 translate-x-full pl-2'">
                                    <button @click.stop="setReply(msg)"
                                        class="h-8 w-8 rounded-full border shadow-xl flex items-center justify-center transition-all"
                                        style="background:rgba(9,9,11,0.8);border-color:rgba(255,255,255,0.08);color:rgba(161,161,170,1)"
                                        onmouseover="this.style.background='#fff';this.style.color='#000'" onmouseout="this.style.background='rgba(9,9,11,0.8)';this.style.color='rgba(161,161,170,1)'">
                                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 15L3 9m0 0l6-6M3 9h12a6 6 0 010 12h-3"/>
                                        </svg>
                                    </button>
                                    <template x-if="isAdmin">
                                        <button @click.stop="deleteMsg(msg.id)"
                                            class="h-8 w-8 rounded-full border shadow-xl flex items-center justify-center transition-all"
                                            style="background:rgba(239,68,68,0.15);border-color:rgba(239,68,68,0.25);color:rgba(248,113,113,1)"
                                            onmouseover="this.style.background='rgba(220,38,38,1)';this.style.color='#fff'" onmouseout="this.style.background='rgba(239,68,68,0.15)';this.style.color='rgba(248,113,113,1)'">
                                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0"/>
                                            </svg>
                                        </button>
                                    </template>
                                </div>
                            </div>
                        </div>
                    </div>
                </template>
            </div>
        </template>

        <!-- Scroll anchor -->
        <div id="msg-bottom" x-ref="bottom"></div>
    </div>

    {{-- MOBILE: Voice Panel (replaces messages area on voice tab) --}}
    <div class="flex-1 overflow-y-auto scrollable p-5 lg:hidden space-y-5"
         x-show="mobileTab === 'voice'">

        <!-- Status card -->
        <div class="rounded-2xl p-5 border space-y-4" style="background:rgba(255,255,255,0.03);border-color:rgba(255,255,255,0.07)">
            <div class="flex items-center gap-3">
                <div class="h-12 w-12 rounded-full flex items-center justify-center"
                     :style="voiceState.in_voice ? 'background:rgba(16,185,129,0.15)' : 'background:rgba(255,255,255,0.05)'">
                    <svg class="h-6 w-6" :style="voiceState.in_voice ? 'color:rgba(52,211,153,1)' : 'color:rgba(113,113,122,1)'" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 18.75a6 6 0 006-6v-1.5m-6 7.5a6 6 0 01-6-6v-1.5m6 7.5v3.75m-3.75 0h7.5M12 15.75a3 3 0 01-3-3V4.5a3 3 0 116 0v8.25a3 3 0 01-3 3z"/>
                    </svg>
                </div>
                <div>
                    <p class="text-base font-medium text-white" x-text="voiceState.in_voice ? 'Ses Kanalındasınız' : 'Ses Kanalı'"></p>
                    <p class="text-xs text-zinc-500" x-text="voiceParticipantCount() + ' katılımcı'"></p>
                    <p x-show="voiceState.in_voice" class="text-[10px] mt-0.5"
                       :style="voiceConnectionStatus === 'reconnecting' ? 'color:rgba(251,191,36,1)' : 'color:rgba(113,113,122,1)'"
                       x-text="voiceConnectionStatus === 'reconnecting' ? 'Yeniden bağlanıyor...' : ('Kalite: ' + qualityLabel(voiceState.connection_quality))"></p>
                </div>
            </div>
            <!-- Join/Leave -->
            <template x-if="!voiceState.in_voice">
                <button @click="voiceJoin()" class="w-full py-3.5 rounded-2xl text-sm font-semibold transition-all"
                        style="background:rgba(16,185,129,0.2);color:rgba(52,211,153,1);border:1px solid rgba(52,211,153,0.3)">
                    Ses Kanalına Katıl
                </button>
            </template>
            <template x-if="voiceState.in_voice">
                <div class="flex gap-3">
                    <button @click="voiceToggleMute()" class="flex-1 py-3.5 rounded-2xl text-sm font-semibold transition-all"
                            :style="voiceState.is_muted ? 'background:rgba(245,158,11,0.15);color:rgba(251,191,36,1);border:1px solid rgba(245,158,11,0.3)' : 'background:rgba(16,185,129,0.1);color:rgba(52,211,153,1);border:1px solid rgba(52,211,153,0.2)'">
                        <span x-text="voiceState.is_muted ? '🔇 Sesi Aç' : '🎤 Sesi Kapat'"></span>
                    </button>
                    <button @click="voiceToggleDeafen()" class="flex-1 py-3.5 rounded-2xl text-sm font-semibold transition-all"
                            :style="voiceState.is_deafened ? 'background:rgba(99,102,241,0.16);color:rgba(165,180,252,1);border:1px solid rgba(99,102,241,0.3)' : 'background:rgba(255,255,255,0.06);color:rgba(161,161,170,1);border:1px solid rgba(255,255,255,0.08)'">
                        <span x-text="voiceState.is_deafened ? 'Kulaklık Aç' : 'Sağırlaştır'"></span>
                    </button>
                    <button @click="voiceLeave(); mobileTab='chat'" class="flex-1 py-3.5 rounded-2xl text-sm font-semibold"
                            style="background:rgba(239,68,68,0.15);color:rgba(248,113,113,1);border:1px solid rgba(239,68,68,0.2)">
                        Ayrıl
                    </button>
                </div>
            </template>
            <div x-show="voiceState.in_voice" class="grid grid-cols-1 gap-2 text-xs text-zinc-400">
                <label class="flex items-center justify-between gap-3 px-3 py-2 rounded-xl" style="background:rgba(255,255,255,0.04)">
                    <span>Bas-konuş</span>
                    <input type="checkbox" x-model="voicePrefs.pushToTalk" @change="_applyLocalMicState()" class="accent-emerald-500">
                </label>
                <label class="flex items-center justify-between gap-3 px-3 py-2 rounded-xl" style="background:rgba(255,255,255,0.04)">
                    <span>Gürültü azaltma</span>
                    <input type="checkbox" x-model="voicePrefs.noiseSuppression" @change="_applyLocalMicState()" class="accent-emerald-500">
                </label>
                <label class="flex items-center justify-between gap-3 px-3 py-2 rounded-xl" style="background:rgba(255,255,255,0.04)">
                    <span>Yankı engelleme</span>
                    <input type="checkbox" x-model="voicePrefs.echoCancellation" @change="_applyLocalMicState()" class="accent-emerald-500">
                </label>
                <label class="flex items-center justify-between gap-3 px-3 py-2 rounded-xl" style="background:rgba(255,255,255,0.04)">
                    <span>Düşük bant genişliği</span>
                    <input type="checkbox" x-model="voicePrefs.lowBandwidth" @change="_applyLocalMicState()" class="accent-emerald-500">
                </label>
            </div>
            <div x-show="voiceState.in_voice && voiceState.can_moderate" class="grid grid-cols-1 gap-2 text-xs text-zinc-400">
                <label class="flex items-center justify-between gap-3 px-3 py-2 rounded-xl" style="background:rgba(255,255,255,0.04)">
                    <span>Sadece oda üyeleri seste</span>
                    <input type="checkbox"
                           :checked="!!voiceState.settings?.voice_members_only"
                           @change="voiceUpdateSettings({ voice_members_only: $event.target.checked })"
                           class="accent-emerald-500">
                </label>
                <label class="flex items-center justify-between gap-3 px-3 py-2 rounded-xl" style="background:rgba(255,255,255,0.04)">
                    <span>Konuşma izni sistemi</span>
                    <input type="checkbox"
                           :checked="!!voiceState.settings?.voice_requires_permission"
                           @change="voiceUpdateSettings({ voice_requires_permission: $event.target.checked })"
                           class="accent-emerald-500">
                </label>
                <button @click="voiceMuteAll()" class="py-2 rounded-xl font-medium"
                        style="background:rgba(245,158,11,0.12);color:rgba(251,191,36,1)">
                    Herkesi Sustur
                </button>
            </div>
        </div>

        <!-- Participants grid (same as desktop but full-width) -->
        <div x-show="voiceParticipantCount() > 0" class="space-y-3">
            <p class="text-[11px] uppercase tracking-widest text-zinc-500">
                Ses Kanalı &mdash; <span x-text="voiceParticipantCount()"></span> kişi
            </p>
            <div class="grid grid-cols-3 gap-3">
                <template x-for="p in voiceParticipants()" :key="p.id">
                    <div class="flex flex-col items-center gap-2 p-4 rounded-2xl relative"
                         :style="_speakingUsers[p.id] && !p.is_muted
                             ? 'background:rgba(16,185,129,0.08);border:1px solid rgba(16,185,129,0.3)'
                             : 'background:rgba(255,255,255,0.03);border:1px solid rgba(255,255,255,0.06)'">
                        <div class="relative">
                            <div x-show="_speakingUsers[p.id] && !p.is_muted" class="absolute inset-0 rounded-full"
                                 style="animation:speakPulse 1s ease-in-out infinite"></div>
                            <div class="h-14 w-14 rounded-full overflow-hidden flex items-center justify-center"
                                 :style="_speakingUsers[p.id] ? 'background:rgba(16,185,129,0.15)' : 'background:#27272a'">
                                <template x-if="p.avatar_url"><img :src="p.avatar_url" class="h-full w-full object-cover"></template>
                                <template x-if="!p.avatar_url"><span class="text-base font-bold text-zinc-400" x-text="p.username?.[0]?.toUpperCase()"></span></template>
                            </div>
                            <div x-show="p.is_muted" class="absolute -bottom-1 -right-1 h-5 w-5 rounded-full flex items-center justify-center" style="background:rgba(245,158,11,0.9)">
                                <svg class="h-3 w-3 text-black" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M17.25 9.75L19.5 12m0 0l2.25 2.25M19.5 12l2.25-2.25M19.5 12l-2.25 2.25"/></svg>
                            </div>
                        </div>
                        <span class="text-[10px] text-zinc-300 truncate w-full text-center" x-text="p.username"></span>
                        <div x-show="voiceState.can_moderate && String(p.id) !== String(currentUser.id)"
                             class="flex gap-1 w-full">
                            <button @click="voiceSetSpeakPermission(p, !p.can_speak)"
                                    class="flex-1 py-1 rounded-lg text-[9px]"
                                    style="background:rgba(255,255,255,0.06);color:rgba(212,212,216,1)"
                                    x-text="p.can_speak ? 'Sustur' : 'İzin ver'"></button>
                            <button @click="voiceKick(p)"
                                    class="flex-1 py-1 rounded-lg text-[9px]"
                                    style="background:rgba(239,68,68,0.12);color:rgba(248,113,113,1)">
                                At
                            </button>
                        </div>
                    </div>
                </template>
            </div>
        </div>

        <p x-show="voiceParticipantCount() === 0" class="text-center text-xs text-zinc-600 pt-8">
            Henüz kimse ses kanalında değil
        </p>
    </div>

    <!-- Chat Input -->
    <div class="px-3 md:px-8 pt-2 z-20"
         :class="mobileTab !== 'chat' ? 'hidden lg:block' : ''"
         :style="(window.innerWidth < 1024)
             ? `background:linear-gradient(to top,rgba(0,0,0,0.98) 0%,rgba(0,0,0,0.92) 50%,rgba(0,0,0,0.4) 100%);padding-bottom:calc(${62 + (voiceState.in_voice ? 56 : 0) + 12}px + env(safe-area-inset-bottom,0px))`
             : 'background:linear-gradient(to top,rgba(0,0,0,0.98) 0%,rgba(0,0,0,0.92) 50%,rgba(0,0,0,0.4) 100%);padding-bottom:1.25rem'">
        <div class="relative max-w-4xl mx-auto flex flex-col gap-2">
            <!-- Reply Banner -->
            <div x-show="replyingTo" x-transition
                 class="flex items-center justify-between px-4 py-2 rounded-t-2xl border border-b-0 backdrop-blur-xl"
                 style="background:rgba(24,24,27,0.9);border-color:rgba(255,255,255,0.08)">
                <div class="flex items-center gap-2 overflow-hidden">
                    <div class="w-1 h-8 rounded-full" style="background:rgba(99,102,241,1)"></div>
                    <div class="flex flex-col min-w-0">
                        <span class="text-xs font-bold" style="color:rgba(129,140,248,1)"
                              x-text="'Yanıtlanıyor: ' + (replyingTo?.sender?.username || 'Silinmiş Kullanıcı')"></span>
                        <span class="text-[10px] truncate max-w-[200px] md:max-w-md" style="color:rgba(161,161,170,1)"
                              x-text="replyingTo?.content"></span>
                    </div>
                </div>
                <button @click="replyingTo = null" class="p-1 rounded transition-colors" style="color:rgba(113,113,122,1)" onmouseover="this.style.color='#fff'" onmouseout="this.style.color='rgba(113,113,122,1)'">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>

            <div x-show="Object.keys(typingUsers).length > 0" x-transition
                 class="px-4 text-[10px] tracking-wide"
                 style="color:rgba(161,161,170,0.75)">
                <span x-text="Object.values(typingUsers).map(u => u.username).slice(0,2).join(', ') + ' yazıyor...'"></span>
            </div>

            <!-- Normal chat input (non-announcements rooms) -->
            <template x-if="roomType !== 'announcements'">
            <div class="relative flex items-center gap-2 p-1 pl-5 backdrop-blur-2xl border shadow-2xl transition-all duration-300"
                 :class="replyingTo ? 'rounded-b-3xl rounded-t-sm' : 'rounded-2xl'"
                 style="background:rgba(18,18,20,0.95);border-color:rgba(255,255,255,0.1);box-shadow:0 -4px 30px rgba(0,0,0,0.6),inset 0 1px 0 rgba(255,255,255,0.04)"
                 id="input-wrapper"
                 onfocusin="this.style.borderColor='rgba(99,102,241,0.4)';this.style.boxShadow='0 -4px 30px rgba(0,0,0,0.6),0 0 0 1px rgba(99,102,241,0.2),inset 0 1px 0 rgba(255,255,255,0.04)'" onfocusout="this.style.borderColor='rgba(255,255,255,0.1)';this.style.boxShadow='0 -4px 30px rgba(0,0,0,0.6),inset 0 1px 0 rgba(255,255,255,0.04)'">

                <!-- Recording indicator -->
                <template x-if="_voiceRecording">
                    <div class="flex items-center gap-3 w-full py-3">
                        <div class="h-3 w-3 rounded-full bg-red-500 animate-pulse shrink-0"></div>
                        <span class="text-sm text-red-400 font-medium" x-text="'Kayıt: ' + _voiceRecordSecs + 's'"></span>
                        <div class="flex-1"></div>
                        <button @click="cancelVoiceRecord()"
                            class="h-8 w-8 rounded-full flex items-center justify-center shrink-0"
                            style="background:rgba(239,68,68,0.15);color:rgba(248,113,113,1)">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                        </button>
                        <button @click="stopVoiceRecord()"
                            class="h-10 w-10 rounded-xl flex items-center justify-center shrink-0 mr-1"
                            style="background:rgba(99,102,241,0.9);color:#fff;box-shadow:0 0 16px rgba(99,102,241,0.4)">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 12L3.269 3.126A59.768 59.768 0 0121.485 12 59.77 59.77 0 013.27 20.876L5.999 12zm0 0h7.5"/></svg>
                        </button>
                    </div>
                </template>

                <!-- Normal input -->
                <template x-if="!_voiceRecording">
                    <input x-model="inputValue"
                        type="text"
                        :placeholder="replyingTo ? 'Yanıt yaz...' : 'Mesaj yaz veya /play şarkı adı...'"
                        class="w-full bg-transparent border-none text-sm text-white placeholder-zinc-600 py-4 font-light tracking-wide outline-none"
                        @input.debounce.500ms="sendTyping(true)"
                        @keydown.enter.prevent="sendMessage()"
                        :disabled="sending">
                </template>
                <template x-if="!_voiceRecording">
                    <button x-show="!inputValue.trim()" @click="startVoiceRecord()"
                        class="h-10 w-10 shrink-0 rounded-xl flex items-center justify-center transition-all duration-200 mr-1"
                        style="background:rgba(255,255,255,0.06);color:rgba(113,113,122,1)"
                        onmouseover="this.style.background='rgba(239,68,68,0.15)';this.style.color='rgba(248,113,113,1)'"
                        onmouseout="this.style.background='rgba(255,255,255,0.06)';this.style.color='rgba(113,113,122,1)'"
                        title="Sesli mesaj">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 18.75a6 6 0 006-6v-1.5m-6 7.5a6 6 0 01-6-6v-1.5m6 7.5v3.75m-3.75 0h7.5M12 15.75a3 3 0 01-3-3V4.5a3 3 0 116 0v8.25a3 3 0 01-3 3z"/>
                        </svg>
                    </button>
                </template>
                <template x-if="!_voiceRecording">
                    <button x-show="inputValue.trim()" @click="sendMessage()" :disabled="sending || !inputValue.trim()"
                        class="h-10 w-10 shrink-0 rounded-xl flex items-center justify-center transition-all duration-200 mr-1 disabled:opacity-30"
                        :style="inputValue.trim() ? 'background:rgba(99,102,241,0.9);color:#fff;box-shadow:0 0 16px rgba(99,102,241,0.4)' : 'background:rgba(255,255,255,0.06);color:rgba(113,113,122,1)'"
                        onmouseover="if(!this.disabled){this.style.opacity='0.9'}"
                        onmouseout="this.style.opacity='1'">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 12L3.269 3.126A59.768 59.768 0 0121.485 12 59.77 59.77 0 013.27 20.876L5.999 12zm0 0h7.5"/>
                        </svg>
                    </button>
                </template>
            </div>
            </template>

            <!-- Announcements room: admin form -->
            <template x-if="roomType === 'announcements' && isAdmin">
            <div class="rounded-2xl border overflow-hidden shadow-2xl"
                 style="background:rgba(13,13,20,0.97);border-color:rgba(99,102,241,0.25);box-shadow:0 -4px 30px rgba(0,0,0,0.6)">
                <div class="flex items-center gap-2 px-4 pt-3 pb-0">
                    <span style="color:rgba(99,102,241,0.8);font-size:1.1rem">📢</span>
                    <input x-model="announcementTitle"
                           type="text"
                           placeholder="Duyuru başlığı..."
                           maxlength="200"
                           class="flex-1 bg-transparent border-none text-sm font-semibold text-white placeholder-zinc-600 py-2 outline-none"
                           style="letter-spacing:0.01em">
                </div>
                <div style="height:1px;background:rgba(99,102,241,0.1);margin:0 1rem"></div>
                <div class="flex items-end gap-2 px-4 pb-3 pt-1">
                    <textarea x-model="inputValue"
                              placeholder="Duyuru içeriği..."
                              rows="2"
                              maxlength="4000"
                              class="flex-1 bg-transparent border-none text-sm text-white placeholder-zinc-600 py-2 font-light outline-none resize-none"
                              @input.debounce.500ms="sendTyping(true)"
                              @keydown.ctrl.enter.prevent="sendMessage()"
                              :disabled="sending"></textarea>
                    <button @click="sendMessage()" :disabled="sending || !inputValue.trim() || !announcementTitle.trim()"
                            class="h-10 w-10 shrink-0 rounded-xl flex items-center justify-center transition-all duration-200 disabled:opacity-30 mb-1"
                            :style="(inputValue.trim() && announcementTitle.trim()) ? 'background:rgba(99,102,241,0.9);color:#fff;box-shadow:0 0 16px rgba(99,102,241,0.4)' : 'background:rgba(255,255,255,0.06);color:rgba(113,113,122,1)'">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 12L3.269 3.126A59.768 59.768 0 0121.485 12 59.77 59.77 0 013.27 20.876L5.999 12zm0 0h7.5"/>
                        </svg>
                    </button>
                </div>
                <div class="px-4 pb-2">
                    <span class="text-[10px]" style="color:rgba(99,102,241,0.5)">Ctrl+Enter ile gönder</span>
                </div>
            </div>
            </template>

            <!-- Announcements room: read-only notice for non-admins -->
            <template x-if="roomType === 'announcements' && !isAdmin">
            <div class="rounded-2xl px-5 py-4 flex items-center gap-3"
                 style="background:rgba(13,13,20,0.9);border:1px solid rgba(99,102,241,0.12)">
                <svg class="h-5 w-5 shrink-0" style="color:rgba(99,102,241,0.6)" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M11 5.882V19.24a1.76 1.76 0 01-3.417.592l-2.147-6.15M18 13a3 3 0 100-6M5.436 13.683A4.001 4.001 0 017 6h1.832c4.1 0 7.625-1.234 9.168-3v14c-1.543-1.766-5.067-3-9.168-3H7a3.988 3.988 0 01-1.564-.317z"/>
                </svg>
                <span class="text-sm" style="color:rgba(113,113,122,1)">Bu kanal yalnızca yönetici duyuruları içindir</span>
            </div>
            </template>
        </div>
    </div>
</div>{{-- end messages column --}}

{{-- ── RIGHT: Voice sidebar (desktop only) ── --}}
<div x-show="musicOpen" x-transition:enter="transition-all duration-300"
     x-transition:enter-start="opacity-0 translate-x-4" x-transition:enter-end="opacity-100 translate-x-0"
     x-transition:leave="transition-all duration-200" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0 translate-x-4"
     class="hidden lg:flex w-80 shrink-0 h-full border-l flex-col z-10 overflow-hidden"
     style="background:rgba(0,0,0,0.4);backdrop-filter:blur(20px);border-color:rgba(255,255,255,0.06)">

    <!-- Sidebar header -->
    <div class="h-20 border-b px-4 flex items-center gap-2 shrink-0"
         style="border-color:rgba(255,255,255,0.05)">
        <svg class="h-4 w-4" style="color:rgba(52,211,153,1)" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 18.75a6 6 0 006-6v-1.5m-6 7.5a6 6 0 01-6-6v-1.5m6 7.5v3.75m-3.75 0h7.5M12 15.75a3 3 0 01-3-3V4.5a3 3 0 116 0v8.25a3 3 0 01-3 3z"/>
        </svg>
        <span class="text-sm font-medium text-white">Sesli Sohbet</span>
        <div x-show="voiceState.in_voice" class="h-1.5 w-1.5 rounded-full"
             :class="voiceState.is_muted ? 'bg-amber-500' : 'bg-emerald-500 animate-pulse'"></div>
    </div>

    <div class="flex-1 overflow-y-auto scrollable p-4 space-y-4">

    {{-- ── VOICE PANEL ── --}}
    <div class="space-y-4">

        <!-- Join/Leave button -->
        <div class="rounded-xl p-4 border text-center space-y-3" style="background:rgba(255,255,255,0.03);border-color:rgba(255,255,255,0.07)">
            <div class="flex items-center justify-center gap-2">
                <div class="h-10 w-10 rounded-full flex items-center justify-center"
                     :style="voiceState.in_voice ? 'background:rgba(16,185,129,0.15)' : 'background:rgba(255,255,255,0.05)'">
                    <svg class="h-5 w-5" :style="voiceState.in_voice ? 'color:rgba(52,211,153,1)' : 'color:rgba(113,113,122,1)'" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 18.75a6 6 0 006-6v-1.5m-6 7.5a6 6 0 01-6-6v-1.5m6 7.5v3.75m-3.75 0h7.5M12 15.75a3 3 0 01-3-3V4.5a3 3 0 116 0v8.25a3 3 0 01-3 3z"/>
                    </svg>
                </div>
                <div>
                    <p class="text-sm font-medium text-white" x-text="voiceState.in_voice ? 'Ses Kanalındasınız' : 'Ses Kanalı'"></p>
                    <p class="text-[10px] text-zinc-500" x-text="voiceParticipantCount() + ' katılımcı'"></p>
                    <p x-show="voiceState.in_voice" class="text-[9px]"
                       :style="voiceConnectionStatus === 'reconnecting' ? 'color:rgba(251,191,36,1)' : 'color:rgba(113,113,122,1)'"
                       x-text="voiceConnectionStatus === 'reconnecting' ? 'Yeniden bağlanıyor...' : qualityLabel(voiceState.connection_quality)"></p>
                </div>
            </div>
            <div class="flex gap-2">
                <template x-if="!voiceState.in_voice">
                    <button @click="voiceJoin()"
                        class="flex-1 py-2.5 rounded-xl text-xs font-medium transition-all"
                        style="background:rgba(16,185,129,0.2);color:rgba(52,211,153,1)"
                        onmouseover="this.style.background='rgba(16,185,129,0.3)'" onmouseout="this.style.background='rgba(16,185,129,0.2)'">
                        Katıl
                    </button>
                </template>
                <template x-if="voiceState.in_voice">
                    <button @click="voiceToggleMute()"
                        class="flex-1 py-2.5 rounded-xl text-xs font-medium transition-all"
                        :style="voiceState.is_muted ? 'background:rgba(245,158,11,0.2);color:rgba(252,211,77,1)' : 'background:rgba(255,255,255,0.07);color:rgba(161,161,170,1)'"
                        x-text="voiceState.is_muted ? 'Sessizden Çıkart' : 'Mikrofonu Kapat'">
                    </button>
                </template>
                <template x-if="voiceState.in_voice">
                    <button @click="voiceToggleDeafen()"
                        class="py-2.5 px-3 rounded-xl text-xs font-medium transition-all"
                        :style="voiceState.is_deafened ? 'background:rgba(99,102,241,0.16);color:rgba(165,180,252,1)' : 'background:rgba(255,255,255,0.07);color:rgba(161,161,170,1)'"
                        x-text="voiceState.is_deafened ? 'Dinle' : 'Sağırlaş'">
                    </button>
                </template>
                <template x-if="voiceState.in_voice && voiceState.can_moderate">
                    <button @click="voiceMuteAll()"
                        class="py-2.5 px-3 rounded-xl text-xs font-medium transition-all"
                        style="background:rgba(245,158,11,0.12);color:rgba(251,191,36,1)">
                        Herkesi Sustur
                    </button>
                </template>
                <template x-if="voiceState.in_voice">
                    <button @click="voiceLeave()"
                        class="py-2.5 px-4 rounded-xl text-xs font-medium transition-all"
                        style="background:rgba(239,68,68,0.15);color:rgba(248,113,113,1)"
                        onmouseover="this.style.background='rgba(239,68,68,0.25)'" onmouseout="this.style.background='rgba(239,68,68,0.15)'">
                        Ayrıl
                    </button>
                </template>
            </div>
            <div x-show="voiceState.in_voice" class="grid gap-2 text-[10px] text-zinc-400 text-left">
                <label class="flex items-center justify-between gap-2 px-2 py-1.5 rounded-lg" style="background:rgba(255,255,255,0.04)">
                    <span>Bas-konuş</span>
                    <input type="checkbox" x-model="voicePrefs.pushToTalk" @change="_applyLocalMicState()" class="accent-emerald-500">
                </label>
                <label class="flex items-center justify-between gap-2 px-2 py-1.5 rounded-lg" style="background:rgba(255,255,255,0.04)">
                    <span>Gürültü azaltma</span>
                    <input type="checkbox" x-model="voicePrefs.noiseSuppression" @change="_applyLocalMicState()" class="accent-emerald-500">
                </label>
                <label class="flex items-center justify-between gap-2 px-2 py-1.5 rounded-lg" style="background:rgba(255,255,255,0.04)">
                    <span>Yankı engelleme</span>
                    <input type="checkbox" x-model="voicePrefs.echoCancellation" @change="_applyLocalMicState()" class="accent-emerald-500">
                </label>
                <label class="flex items-center justify-between gap-2 px-2 py-1.5 rounded-lg" style="background:rgba(255,255,255,0.04)">
                    <span>Düşük veri</span>
                    <input type="checkbox" x-model="voicePrefs.lowBandwidth" @change="_applyLocalMicState()" class="accent-emerald-500">
                </label>
            </div>
            <div x-show="voiceState.in_voice && voiceState.can_moderate" class="grid gap-2 text-[10px] text-zinc-400 text-left">
                <label class="flex items-center justify-between gap-2 px-2 py-1.5 rounded-lg" style="background:rgba(255,255,255,0.04)">
                    <span>Sadece oda üyeleri seste</span>
                    <input type="checkbox"
                           :checked="!!voiceState.settings?.voice_members_only"
                           @change="voiceUpdateSettings({ voice_members_only: $event.target.checked })"
                           class="accent-emerald-500">
                </label>
                <label class="flex items-center justify-between gap-2 px-2 py-1.5 rounded-lg" style="background:rgba(255,255,255,0.04)">
                    <span>Konuşma izni sistemi</span>
                    <input type="checkbox"
                           :checked="!!voiceState.settings?.voice_requires_permission"
                           @change="voiceUpdateSettings({ voice_requires_permission: $event.target.checked })"
                           class="accent-emerald-500">
                </label>
            </div>
        </div>

        <!-- Participants -->
        <div x-show="voiceParticipantCount() > 0" class="space-y-2">
            <label class="text-[10px] uppercase tracking-wider text-zinc-500">
                Ses Kanalı — <span x-text="voiceParticipantCount()"></span> kişi
            </label>
            <div class="grid grid-cols-2 gap-2">
                <template x-for="p in voiceParticipants()" :key="p.id">
                    <div class="flex flex-col items-center gap-1.5 p-3 rounded-xl relative transition-all"
                         :style="_speakingUsers[p.id]
                             ? 'background:rgba(16,185,129,0.08);border:1px solid rgba(16,185,129,0.3)'
                             : 'background:rgba(255,255,255,0.03);border:1px solid rgba(255,255,255,0.05)'">

                        <!-- Avatar with speaking ring -->
                        <div class="relative">
                            <!-- Speaking pulse ring -->
                            <div x-show="_speakingUsers[p.id] && !p.is_muted"
                                 class="absolute inset-0 rounded-full"
                                 style="animation:speakPulse 1s ease-in-out infinite;box-shadow:0 0 0 3px rgba(52,211,153,0.6);">
                            </div>
                            <div class="h-10 w-10 rounded-full overflow-hidden flex items-center justify-center shrink-0"
                                 :style="p.is_muted
                                     ? 'background:rgba(39,39,42,1)'
                                     : (_speakingUsers[p.id] ? 'background:rgba(16,185,129,0.15)' : 'background:rgba(39,39,42,1)')">
                                <template x-if="p.avatar_url">
                                    <img :src="p.avatar_url" :alt="p.username" class="h-full w-full object-cover">
                                </template>
                                <template x-if="!p.avatar_url">
                                    <span class="text-sm font-bold"
                                          :style="_speakingUsers[p.id] && !p.is_muted ? 'color:rgba(52,211,153,1)' : 'color:rgba(113,113,122,1)'"
                                          x-text="p.username?.[0]?.toUpperCase()"></span>
                                </template>
                            </div>
                            <!-- Mute badge -->
                            <div x-show="p.is_muted"
                                 class="absolute -bottom-0.5 -right-0.5 h-4 w-4 rounded-full flex items-center justify-center"
                                 style="background:rgba(245,158,11,0.9)">
                                <svg class="h-2.5 w-2.5 text-black" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M17.25 9.75L19.5 12m0 0l2.25 2.25M19.5 12l2.25-2.25M19.5 12l-2.25 2.25m-10.5-6l4.72-4.72a.75.75 0 011.28.531V19.94a.75.75 0 01-1.28.53l-4.72-4.72H4.51c-.88 0-1.704-.506-1.938-1.354A9.01 9.01 0 012.25 12c0-.83.112-1.633.322-2.396C2.806 8.756 3.63 8.25 4.51 8.25H6.75z"/>
                                </svg>
                            </div>
                        </div>

                        <p class="text-[10px] text-zinc-300 truncate w-full text-center" x-text="p.username"></p>
                        <div x-show="voiceState.can_moderate && String(p.id) !== String(currentUser.id)"
                             class="flex gap-1 w-full">
                            <button @click="voiceSetSpeakPermission(p, !p.can_speak)"
                                    class="flex-1 py-1 rounded-md text-[9px]"
                                    style="background:rgba(255,255,255,0.06);color:rgba(212,212,216,1)"
                                    x-text="p.can_speak ? 'Sustur' : 'İzin ver'"></button>
                            <button @click="voiceKick(p)"
                                    class="flex-1 py-1 rounded-md text-[9px]"
                                    style="background:rgba(239,68,68,0.12);color:rgba(248,113,113,1)">
                                At
                            </button>
                        </div>
                    </div>
                </template>
            </div>
        </div>

        <p class="text-[10px] text-zinc-600 text-center" x-show="!voiceState.in_voice">Katıldığınızda aynı odadaki herkes sizi duyabilir</p>
    </div>

    <!-- Now Playing mini banner (slash-command driven) -->
    <div x-show="musicState.video_id" class="rounded-xl p-3 border space-y-2"
         style="background:rgba(16,185,129,0.06);border-color:rgba(16,185,129,0.15)">
        <div class="flex items-center gap-2">
            <div class="h-8 w-8 rounded-lg flex items-center justify-center shrink-0"
                 :style="musicState.is_playing ? 'background:rgba(16,185,129,0.2)' : 'background:rgba(255,255,255,0.05)'">
                <svg class="h-4 w-4" :style="musicState.is_playing ? 'color:rgba(52,211,153,1)' : 'color:rgba(113,113,122,1)'" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 9l10.5-3m0 6.553v3.75a2.25 2.25 0 01-1.632 2.163l-1.32.377a1.803 1.803 0 11-.99-3.467l2.31-.66a2.25 2.25 0 001.632-2.163zm0 0V2.25L9 5.25v10.303m0 0v3.75a2.25 2.25 0 01-1.632 2.163l-1.32.377a1.803 1.803 0 01-.99-3.467l2.31-.66A2.25 2.25 0 009 15.553z"/>
                </svg>
            </div>
            <div class="flex-1 min-w-0">
                <p class="text-xs text-white truncate" x-text="musicState.video_title"></p>
                <p class="text-[9px] text-zinc-500" x-text="musicState.is_playing ? '▶ Çalıyor' : '⏸ Duraklatıldı'"></p>
            </div>
            {{-- Sound toggle --}}
            <button x-show="voiceState.in_voice && musicState.is_playing" @click="muteToggle()"
                    class="h-7 w-7 rounded-lg flex items-center justify-center shrink-0 transition-all"
                    :style="_playerMuted ? 'background:rgba(239,68,68,0.15);color:rgba(248,113,113,1)' : 'background:rgba(16,185,129,0.15);color:rgba(52,211,153,1)'"
                    :title="_playerMuted ? 'Sesi Aç' : 'Sesi Kapat'">
                <svg x-show="!_playerMuted" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19.114 5.636a9 9 0 010 12.728M16.463 8.288a5.25 5.25 0 010 7.424M6.75 8.25l4.72-4.72a.75.75 0 011.28.53v15.88a.75.75 0 01-1.28.53l-4.72-4.72H4.51c-.88 0-1.704-.507-1.938-1.354A9.01 9.01 0 012.25 12c0-.83.112-1.633.322-2.396C2.806 8.756 3.63 8.25 4.51 8.25H6.75z"/>
                </svg>
                <svg x-show="_playerMuted" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M17.25 9.75L19.5 12m0 0l2.25 2.25M19.5 12l2.25-2.25M19.5 12l-2.25 2.25m-10.5-6l4.72-4.72a.75.75 0 011.28.531V19.94a.75.75 0 01-1.28.53l-4.72-4.72H4.51c-.88 0-1.704-.506-1.938-1.354A9.01 9.01 0 012.25 12c0-.83.112-1.633.322-2.396C2.806 8.756 3.63 8.25 4.51 8.25H6.75z"/>
                </svg>
            </button>
        </div>
        <p class="text-[9px] text-zinc-600 text-center">/play /durdur /geç /sıra /devam</p>
    </div>

    </div>{{-- end scrollable --}}
</div>{{-- end right sidebar --}}

{{-- ═══════════════════════════════════════════════════════════
     MOBILE-ONLY COMPONENTS (all hidden on lg+ via class/show)
     ═══════════════════════════════════════════════════════════ --}}

{{-- ── Voice Mini Bar ── --}}
<div class="lg:hidden"
     x-show="voiceState.in_voice"
     x-transition:enter="transition-all duration-200" x-transition:enter-start="opacity-0 translate-y-2" x-transition:enter-end="opacity-100 translate-y-0"
     x-transition:leave="transition-all duration-150" x-transition:leave-start="opacity-100 translate-y-0" x-transition:leave-end="opacity-0 translate-y-2"
     style="position:fixed;left:0;right:0;bottom:62px;z-index:61;padding:0 10px 4px">
    <div class="flex items-center gap-2.5 px-3 py-2 rounded-2xl"
         :style="voiceState.is_muted
             ? 'background:rgba(9,9,11,0.97);border:1px solid rgba(245,158,11,0.25);backdrop-filter:blur(24px);box-shadow:0 -4px 24px rgba(0,0,0,0.5)'
             : 'background:rgba(9,9,11,0.97);border:1px solid rgba(52,211,153,0.22);backdrop-filter:blur(24px);box-shadow:0 -4px 24px rgba(0,0,0,0.5)'">

        {{-- Live pulse --}}
        <div class="h-2 w-2 rounded-full shrink-0"
             :class="voiceState.is_muted ? 'bg-amber-400' : 'bg-emerald-500 animate-pulse'"></div>

        {{-- Participant avatars --}}
        <div class="flex items-center shrink-0" style="--gap:-8px">
            <template x-for="(p, pi) in voiceParticipants().slice(0, 4)" :key="p.id">
                <div class="h-7 w-7 rounded-full border-2 flex items-center justify-center text-[11px] font-bold -ml-2 first:ml-0 transition-all"
                     :style="_speakingUsers[p.id]
                         ? 'background:rgba(16,185,129,0.3);color:rgba(52,211,153,1);border-color:rgba(52,211,153,0.6)'
                         : 'background:rgba(39,39,42,1);color:rgba(161,161,170,1);border-color:rgba(9,9,11,1)'"
                     x-text="p.username?.[0]?.toUpperCase()"></div>
            </template>
        </div>

        {{-- Status text --}}
        <div class="flex-1 min-w-0">
            <p class="text-xs font-medium leading-tight"
               :style="voiceState.is_muted ? 'color:rgba(251,191,36,1)' : 'color:rgba(52,211,153,1)'"
               x-text="voiceState.is_muted ? '🔇 Sessizde' : (musicState.video_id && musicState.is_playing ? '🎵 ' + (musicState.video_title || 'Çalıyor').substring(0,20) : `${voiceParticipantCount()} kişi sesli`)"></p>
        </div>

        {{-- Music speaker toggle (mobile) --}}
        <button x-show="musicState.video_id && musicState.is_playing" @click="muteToggle()"
                class="h-9 w-9 rounded-xl flex items-center justify-center shrink-0 transition-all"
                :style="_playerMuted
                    ? 'background:rgba(239,68,68,0.15);color:rgba(248,113,113,1);border:1px solid rgba(239,68,68,0.25)'
                    : 'background:rgba(16,185,129,0.15);color:rgba(52,211,153,1);border:1px solid rgba(16,185,129,0.25)'"
                :title="_playerMuted ? 'Müzik Sesini Aç' : 'Müzik Sesini Kapat'">
            <svg x-show="!_playerMuted" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M19.114 5.636a9 9 0 010 12.728M16.463 8.288a5.25 5.25 0 010 7.424M6.75 8.25l4.72-4.72a.75.75 0 011.28.53v15.88a.75.75 0 01-1.28.53l-4.72-4.72H4.51c-.88 0-1.704-.507-1.938-1.354A9.01 9.01 0 012.25 12c0-.83.112-1.633.322-2.396C2.806 8.756 3.63 8.25 4.51 8.25H6.75z"/>
            </svg>
            <svg x-show="_playerMuted" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M17.25 9.75L19.5 12m0 0l2.25 2.25M19.5 12l2.25-2.25M19.5 12l-2.25 2.25m-10.5-6l4.72-4.72a.75.75 0 011.28.531V19.94a.75.75 0 01-1.28.53l-4.72-4.72H4.51c-.88 0-1.704-.506-1.938-1.354A9.01 9.01 0 012.25 12c0-.83.112-1.633.322-2.396C2.806 8.756 3.63 8.25 4.51 8.25H6.75z"/>
            </svg>
        </button>

        {{-- Mute toggle --}}
        <button @click="voiceToggleMute()"
                class="h-9 w-9 rounded-xl flex items-center justify-center shrink-0 transition-all"
                :style="voiceState.is_muted
                    ? 'background:rgba(245,158,11,0.2);color:rgba(251,191,36,1);border:1px solid rgba(245,158,11,0.3)'
                    : 'background:rgba(52,211,153,0.15);color:rgba(52,211,153,1);border:1px solid rgba(52,211,153,0.25)'">
            <svg x-show="!voiceState.is_muted" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 18.75a6 6 0 006-6v-1.5m-6 7.5a6 6 0 01-6-6v-1.5m6 7.5v3.75m-3.75 0h7.5M12 15.75a3 3 0 01-3-3V4.5a3 3 0 116 0v8.25a3 3 0 01-3 3z"/>
            </svg>
            <svg x-show="voiceState.is_muted" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M17.25 9.75L19.5 12m0 0l2.25 2.25M19.5 12l2.25-2.25M19.5 12l-2.25 2.25m-10.5-6l4.72-4.72a.75.75 0 011.28.531V19.94a.75.75 0 01-1.28.53l-4.72-4.72H4.51c-.88 0-1.704-.506-1.938-1.354A9.01 9.01 0 012.25 12c0-.83.112-1.633.322-2.396C2.806 8.756 3.63 8.25 4.51 8.25H6.75z"/>
            </svg>
        </button>

        {{-- Leave call --}}
        <button @click="voiceLeave()"
                class="h-9 px-3 rounded-xl flex items-center gap-1 text-xs font-semibold shrink-0"
                style="background:rgba(220,38,38,0.18);color:rgba(252,165,165,1);border:1px solid rgba(220,38,38,0.25)">
            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
            </svg>
            Ayrıl
        </button>
    </div>
</div>


{{-- ── Bottom Navigation Bar ── --}}
<nav class="lg:hidden"
     style="position:fixed;left:0;right:0;bottom:0;z-index:60;
            padding-bottom:env(safe-area-inset-bottom,0px);
            background:rgba(7,7,9,0.98);
            border-top:1px solid rgba(255,255,255,0.06);
            box-shadow:0 -8px 40px rgba(0,0,0,0.7);
            backdrop-filter:blur(32px) saturate(180%);">
    <div class="flex items-stretch justify-around px-2" style="height:62px">

        {{-- Chat tab --}}
        <button @click="mobileTab='chat'"
                class="flex flex-col items-center justify-center gap-1 flex-1 rounded-xl py-1 relative transition-colors"
                :style="mobileTab==='chat' ? 'color:rgba(52,211,153,1)' : 'color:rgba(82,82,91,1)'">
            <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M7.5 8.25h9m-9 3H12m-9.75 1.51c0 1.6 1.123 2.994 2.707 3.227 1.129.166 2.27.293 3.423.379.35.026.67.21.865.501L12 21l2.755-4.133a1.14 1.14 0 01.865-.501 48.172 48.172 0 003.423-.379c1.584-.233 2.707-1.626 2.707-3.228V6.741c0-1.602-1.123-2.995-2.707-3.228A48.394 48.394 0 0012 3c-2.392 0-4.744.175-7.043.513C3.373 3.746 2.25 5.14 2.25 6.741v6.018z"/>
            </svg>
            <span class="text-[9px] font-medium tracking-wide leading-none">Sohbet</span>
            <div x-show="mobileTab==='chat'" class="absolute bottom-0 left-1/2 -translate-x-1/2 h-0.5 w-8 rounded-full bg-emerald-500"></div>
            <div x-show="mobileTab !== 'chat'" class="absolute top-2 h-1.5 w-1.5 rounded-full bg-emerald-500" style="right:calc(50% - 16px)"></div>
        </button>

        {{-- Voice tab --}}
        <button @click="mobileTab = mobileTab === 'voice' ? 'chat' : 'voice'"
                class="flex flex-col items-center justify-center gap-1 flex-1 rounded-xl py-1 relative transition-colors"
                :style="mobileTab==='voice' ? 'color:rgba(52,211,153,1)' : 'color:rgba(82,82,91,1)'">
            <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 18.75a6 6 0 006-6v-1.5m-6 7.5a6 6 0 01-6-6v-1.5m6 7.5v3.75m-3.75 0h7.5M12 15.75a3 3 0 01-3-3V4.5a3 3 0 116 0v8.25a3 3 0 01-3 3z"/>
            </svg>
            <span class="text-[9px] font-medium tracking-wide leading-none">Ses</span>
            <div x-show="mobileTab==='voice'" class="absolute bottom-0 left-1/2 -translate-x-1/2 h-0.5 w-8 rounded-full bg-emerald-500"></div>
            <div x-show="voiceState.in_voice && mobileTab !== 'voice'"
                 class="absolute top-2 right-3 h-2 w-2 rounded-full border border-black"
                 :class="voiceState.is_muted ? 'bg-amber-400' : 'bg-emerald-500 animate-pulse'"></div>
        </button>

    </div>
</nav>


{{-- ══ YouTube player — audio only ══
     IMPORTANT: must NOT use z-index:-1 or opacity:0 — Chrome suspends fully occluded iframes.
     opacity:0.01 = effectively invisible to human eye, but Chrome keeps audio active.
     z-index:0 = default stacking, UI elements with higher z cover it naturally. --}}
<div id="yt-player" wire:ignore
     style="position:fixed;bottom:0;right:0;
            width:160px;height:90px;
            opacity:0.01;z-index:0;pointer-events:none;">
</div>

</div>{{-- end root flex div --}}
@endsection

@push('scripts')
<script>
let ROOM_ID        = '{{ $room->id }}';
const CURRENT_USER = {!! json_encode(auth()->user()) !!};
const IS_ADMIN     = {{ auth()->user()->isAdmin() ? 'true' : 'false' }};
const INIT_MSGS    = {!! json_encode($initMsgs) !!};
const ROOM_TYPE    = '{{ $room->type }}';

/* ─── Broadcast config — auto-detects Pusher vs Reverb from .env ─── */
const BROADCAST_DRIVER = '{{ config('broadcasting.default', 'null') }}';
const BROADCAST_CONFIG = {!! json_encode([
    'pusher' => [
        'key'     => config('broadcasting.connections.pusher.key'),
        'cluster' => config('broadcasting.connections.pusher.options.cluster', 'eu'),
        'host'    => config('broadcasting.connections.pusher.options.host'),
        'port'    => config('broadcasting.connections.pusher.options.port', 443),
        'scheme'  => config('broadcasting.connections.pusher.options.scheme', 'https'),
    ],
    'reverb' => [
        'key'    => config('broadcasting.connections.reverb.key'),
        'host'   => config('broadcasting.connections.reverb.options.host', 'localhost'),
        'port'   => config('broadcasting.connections.reverb.options.port', 8080),
        'scheme' => config('broadcasting.connections.reverb.options.scheme', 'http'),
    ],
]) !!};

/* LiveKit runtime — NOT inside Alpine reactive state to avoid DataCloneError
   when LiveKit SDK internally calls structuredClone on proxied objects. */
const KankioVoiceRuntime = {
    lkRoom: null,
    audioEls: {},
    micToggleInFlight: false,
    lastAppliedMicEnabled: null,
    permissionStream: null,
};

function getVoiceRoom() { return KankioVoiceRuntime.lkRoom; }
function setVoiceRoom(room) { KankioVoiceRuntime.lkRoom = room; }
function resetVoiceRuntime() {
    KankioVoiceRuntime.lkRoom = null;
    KankioVoiceRuntime.audioEls = {};
    KankioVoiceRuntime.micToggleInFlight = false;
    KankioVoiceRuntime.lastAppliedMicEnabled = null;
    KankioVoiceRuntime.permissionStream = null;
}

function stopPermissionStreamIfAny() {
    if (!KankioVoiceRuntime.permissionStream) return;
    for (const track of KankioVoiceRuntime.permissionStream.getTracks()) {
        try { track.stop(); } catch (_) {}
    }
    KankioVoiceRuntime.permissionStream = null;
}

window.addEventListener('pagehide', stopPermissionStreamIfAny);
window.addEventListener('beforeunload', stopPermissionStreamIfAny);
document.addEventListener('visibilitychange', () => {
    if (document.visibilityState === 'hidden' && !getVoiceRoom()) {
        stopPermissionStreamIfAny();
    }
});

function chatRoom() {
    return {
        /* Chat state */
        messages: [],
        inputValue: '',
        replyingTo: null,
        sending: false,
        connected: false,
        lastMessageAt: null,
        currentUser: CURRENT_USER,
        isAdmin: IS_ADMIN,
        notifyEnabled: {{ auth()->user()->notifications_enabled ? 'true' : 'false' }},

        /* Poll state - adaptive */
        _pollTimer: null,
        _idleCount: 0,
        _hidden: false,
        _realtimeReady: false,

        /* Right sidebar state */
        musicOpen: false,

        /* Music state */
        musicState: { video_id: null, video_title: null, is_playing: false, position: 0, queue: [] },
        _ytIframe: null,             /* direct iframe element */
        _ytCurVid: '',               /* video_id currently in the iframe */
        _ytCurPlay: null,            /* is_playing state currently applied */
        _lastMusicHash: '',
        _playerMuted: true,           /* true = muted, false = sound on */

        /* Room type */
        roomType: ROOM_TYPE,
        announcementTitle: '',

        /* Archived messages */
        archivedCount: {{ $archivedCount ?? 0 }},
        showArchived: false,
        archivedMessages: [],
        archivedLoading: false,
        archivedPage: 1,
        archivedHasMore: false,

        /* Mobile navigation */
        mobileTab: 'chat',       /* 'chat' | 'voice' */
        _speakRaf: null,         /* rAF handle for local speaking detection */
        _remoteRafs: {},         /* { userId: rafId } for remote speaking loops */
        _videoEndedAt: 0,        /* debounce timestamp for _onVideoEnded */
        _endTimer: null,          /* JS timer that fires when video duration runs out */
        _knownDuration: 0,        /* duration captured from infoDelivery (no API key needed) */
        _ytPollTimer: null,       /* interval: polls getDuration/getCurrentTime from iframe */
        _lastCurrentTime: 0,      /* last known currentTime from iframe poll */

        /* Voice message recording */
        _voiceRecording: false,
        _voiceMediaRec: null,
        _voiceChunks: [],
        _voiceRecordSecs: 0,
        _voiceRecTimer: null,
        _voiceRecStream: null,

        /* Audio playback */
        _playingAudioId: null,
        _audioEl: null,
        _audioProgress: 0,
        _audioProgressTimer: null,

        /* Message seen tracking */
        _seenTimer: null,
        typingUsers: {},
        _typingTimer: null,
        _typingSent: false,

        /* SPA room state */
        _roomId:   '{{ $room->id }}',
        _roomName: '{{ addslashes($room->name) }}',

        /* Voice state */
        voiceState: {
            in_voice: false, is_muted: false, is_deafened: false, can_speak: true,
            connection_quality: 'unknown', reconnect_count: 0, can_moderate: false,
            settings: {}, participants: []
        },
        _voiceRoomId: null,   /* room where voice is LOCKED — never changes during navigation */
        _voiceTimer: null,
        _manualVoiceLeave: false,
        _voiceReconnectAttempts: 0,
        _voiceReconnectTimer: null,
        _voiceJoinInFlight: false,      /* prevents concurrent voiceJoin() races */
        voiceConnectionStatus: 'idle', /* idle | connecting | connected | reconnecting | failed */
        voicePrefs: { noiseSuppression: true, echoCancellation: true, pushToTalk: false, lowBandwidth: false },
        _pttDown: false,

        /* Active speaker detection */
        _speakingUsers: {},   /* { userId: bool } */
        _speakingTimer: null,

        /* Shared Echo instance */
        _echo: null,

        /* PWA background state */
        _silentAudio: null,     /* AudioBufferSourceNode — keeps audio pipeline alive */
        _isBackground: false,
        _reconnectTimer: null,

        init() {
            const pathMatch = window.location.pathname.match(/\/chat\/(\d+)/);
            if (pathMatch) {
                this._roomId = String(pathMatch[1]);
                ROOM_ID = this._roomId;
            }
            if (typeof Alpine !== 'undefined' && Alpine.store('chat')) {
                Alpine.store('chat').activeRoomId = this._roomId;
            }

            this.messages = INIT_MSGS;
            if (this.messages.length > 0) {
                this.lastMessageAt = this.messages[this.messages.length - 1].created_at;
            }
            this.$nextTick(() => this.scrollToBottom());

            /* Expose layout reference (Alpine v3 compatible) */
            setTimeout(() => {
                const el = document.querySelector('[x-data="chatLayout()"]');
                if (el) window._chatLayout = (typeof Alpine !== 'undefined' && Alpine.$data) ? Alpine.$data(el) : (el._x_dataStack?.[0]);
            }, 500);

            /* Expose changeRoom globally for sidebar SPA links */
            window._changeRoom = (id, name) => this.changeRoom(id, name);

            window.addEventListener('popstate', async () => {
                const m = window.location.pathname.match(/\/chat\/(\d+)/);
                if (!m) return;

                const roomId = m[1];
                if (String(roomId) === String(this._roomId)) return;

                await this.changeRoom(roomId, '', { pushState: false });
            });

            /* Pause polling when tab hidden — saves battery/CPU */
            document.addEventListener('visibilitychange', () => {
                this._hidden = document.hidden;
                if (!document.hidden) this._schedulePoll(500);
            });

            this._schedulePoll(1000);
            this._initEcho();
            this._initYT();
            this._initBackgroundHandlers();
            this._initTauri();
            this._startSeenTracking();
            this._initPushToTalk();
        },

        /* ── Room Transition ── */
        async changeRoom(id, name, options = {}) {
            id = String(id);
            const pushState = options.pushState !== false;
            if (String(this._roomId) === id) return;

            try {
                this.connected = false;
                this._leaveRoomRealtimeChannels();

                const r = await fetch(`/api/chat/${id}/bootstrap`, {
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    }
                });

                if (!r.ok) {
                    showToast('Oda yüklenemedi', 'error');
                    this.connected = true;
                    this._joinRoomRealtimeChannels();
                    return;
                }

                const data = await r.json();

                this._roomId = String(data.room?.id || id);
                this._roomName = data.room?.name || name || this._roomName;
                this.roomType = data.room?.type || this.roomType;
                ROOM_ID = this._roomId;
                if (typeof Alpine !== 'undefined' && Alpine.store('chat')) {
                    Alpine.store('chat').activeRoomId = this._roomId;
                }

                this.messages = Array.isArray(data.messages) ? data.messages : [];
                this.lastMessageAt = this.messages.length
                    ? this.messages[this.messages.length - 1].created_at
                    : null;

                this.archivedMessages = [];
                this.archivedPage = 1;
                this.archivedHasMore = false;
                this.archivedCount = data.archived_count || 0;
                this.archivedLoading = false;
                this.showArchived = false;

                this.typingUsers = {};
                this.replyingTo = null;
                this.inputValue = '';
                this.announcementTitle = '';
                this.mobileTab = 'chat';
                this.connected = true;

                if (pushState) {
                    window.history.pushState({}, '', `/chat/${this._roomId}`);
                }

                this._joinRoomRealtimeChannels();

                this.$nextTick(() => this.scrollToBottom());
                this._schedulePoll(300);
            } catch (e) {
                console.error('[room] changeRoom failed', e);
                showToast('Oda değiştirilemedi', 'error');
                this.connected = true;
                this._joinRoomRealtimeChannels();
            }
        },


        /* ── Tauri Desktop Integration ── */
        _initTauri() {
            /* Alt+M global shortcut → sent by src-tauri/src/lib.rs via win.eval() */
            window.addEventListener('tauri-mute-toggle', async () => {
                if (this.voiceState.in_voice) {
                    await this.voiceToggleMute();
                } else {
                    showToast('Ses kanalına girmeden mikrofon açılamaz', 'error');
                }
            });

            /* Auto-update notification from Tauri updater */
            window.addEventListener('tauri-update-available', (e) => {
                const v = e.detail?.version ?? '';
                showToast(`Kankio ${v} güncelleniyor — uygulamayı kapatınca yüklenir`, 'success');
            });
        },

        /* ── Adaptive polling ── */
        _schedulePoll(delay) {
            clearTimeout(this._pollTimer);
            if (this._hidden) return;
            this._pollTimer = setTimeout(() => this._doPoll(), delay);
        },

        async _doPoll() {
            if (this._hidden) return;
            try {
                if (this._realtimeReady) {
                    await this._syncMusic();
                    this._schedulePoll(15000);
                    return;
                }

                /* Messages */
                const activeRoomId = String(this._roomId || ROOM_ID);
                const url = `/api/chat/${activeRoomId}/messages${this.lastMessageAt ? '?since=' + encodeURIComponent(this.lastMessageAt) : ''}`;
                const r = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
                if (!r.ok) { this.connected = false; this._schedulePoll(5000); return; }
                /* If session expired, server returns login page HTML instead of JSON */
                const ct = r.headers.get('content-type') || '';
                if (!ct.includes('application/json')) {
                    window.location.href = `${APP_URL}/login`; return;
                }
                this.connected = true;
                const msgs = await r.json();
                if (msgs.length > 0) {
                    /* Always advance cursor using MAX — never let poll regress the pointer
                       that sendMessage() may have already moved forward. */
                    const tail = msgs[msgs.length - 1].created_at;
                    if (!this.lastMessageAt || tail > this.lastMessageAt) {
                        this.lastMessageAt = tail;
                    }
                    /* Deduplicate: skip messages already in the array (by real numeric ID).
                       This prevents doubles when the poll races with sendMessage()'s
                       optimistic-replace path. */
                    const existingIds = new Set(
                        this.messages.map(m => String(m.id)).filter(id => !id.startsWith('_tmp_'))
                    );
                    const fresh = msgs.filter(m => !existingIds.has(String(m.id)));
                    if (fresh.length > 0) {
                        this.messages.push(...fresh);
                        this.$nextTick(() => this.scrollToBottom());
                        if (this.notifyEnabled) this._playSound();
                        this._idleCount = 0;
                    } else {
                        this._idleCount++;
                    }
                } else {
                    this._idleCount++;
                }
                /* Music sync */
                await this._syncMusic();
            } catch(e) { this.connected = false; }

            /* Adaptive interval: 3s normal, 6s after 20 idle cycles (~1 min quiet) */
            const next = this._idleCount > 20 ? 6000 : 3000;
            this._schedulePoll(next);
        },

        /* Slash command detection — routes to music command API */
        _isSlashCommand(text) {
            return /^\/(play|durdur|stop|pause|ge[cç]|skip|next|s[ıi]ra|queue|devam|resume)\b/i.test(text);
        },

        async _handleSlashCommand(text) {
            this.sending = true;
            this.inputValue = '';
            this.sendTyping(false);

            /* ── User gesture context: unlock iframe audio BEFORE async fetch ──
               Browser autoplay policy requires audio-producing elements to be
               created synchronously within a user gesture (keydown/click).
               After await, the gesture context is consumed → audio blocked.
               Solution: create/unlock the iframe NOW, swap video after fetch. */
            if (this._isSlashCommand(text) && this.voiceState.in_voice) {
                this._playerMuted = false;
                this._ensureIframeReady();
            }

            try {
                const r = await fetch(`/api/music/${ROOM_ID}/command`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
                    body: JSON.stringify({ command: text })
                });
                const ct = r.headers.get('content-type') || '';
                if (!ct.includes('application/json')) {
                    showToast('Oturum süresi dolmuş olabilir — sayfayı yenileyin', 'error'); return;
                }
                const data = await r.json();
                if (!r.ok) {
                    showToast(data.error || data.message || 'Komut başarısız', 'error');
                    return;
                }
                if (data.state) {
                    /* Unmute for ALL music commands when in voice (not just /play) */
                    if (this.voiceState.in_voice) this._playerMuted = false;
                    this.musicState = data.state;
                    this._applyMusicState(data.state);
                }
                /* Force immediate poll to pick up system messages */
                this._idleCount = 0;
                this._schedulePoll(300);
            } catch(e) { showToast('Komut gönderilemedi', 'error'); }
            finally {
                this.sending = false;
                this.$nextTick(() => {
                    const inp = document.querySelector('#input-wrapper input[type="text"]');
                    if (inp) inp.focus();
                });
            }
        },

        /* Send message — Optimistic UI */
        async sendMessage() {
            const content = this.inputValue.trim();
            if (!content || this.sending) return;

            /* Intercept slash commands */
            if (this._isSlashCommand(content)) {
                return this._handleSlashCommand(content);
            }

            this.sending = true;
            const replyId = this.replyingTo ? this.replyingTo.id : null;
            this.replyingTo = null;
            this.inputValue = '';
            this.sendTyping(false);

            /* ── Optimistic: insert message immediately, replace after server confirms ── */
            const tempId = '_tmp_' + Date.now();
            const optimistic = {
                id: tempId,
                content,
                sender: this.currentUser,
                reply_to: replyId ? (this.messages.find(m => m.id === replyId) || null) : null,
                created_at: new Date().toISOString(),
                is_system_message: false,
                _pending: true,
            };
            this.messages.push(optimistic);
            this._idleCount = 0;
            this.$nextTick(() => this.scrollToBottom());

            try {
                const body = { content, reply_to: replyId };
                if (this.roomType === 'announcements') body.title = this.announcementTitle.trim();
                const r = await fetch(`/api/chat/${ROOM_ID}/messages`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF },
                    body: JSON.stringify(body)
                });
                if (!r.ok) {
                    this.messages = this.messages.filter(m => m.id !== tempId);
                    showToast('Mesaj gönderilemedi', 'error'); return;
                }
                const msg = await r.json();
                /* Replace temp placeholder with the confirmed server message.
                   Also remove any copy the poll may have snuck in concurrently
                   so we never end up with two identical real messages. */
                const pollDupIdx = this.messages.findIndex(
                    m => String(m.id) === String(msg.id) && m.id !== tempId
                );
                if (pollDupIdx !== -1) this.messages.splice(pollDupIdx, 1);
                const idx = this.messages.findIndex(m => m.id === tempId);
                if (idx !== -1) this.messages.splice(idx, 1, msg);
                else this.messages.push(msg);
                /* Advance cursor only forward — guard against poll regression */
                if (!this.lastMessageAt || msg.created_at > this.lastMessageAt) {
                    this.lastMessageAt = msg.created_at;
                }
                if (this.roomType === 'announcements') this.announcementTitle = '';
            } catch(e) {
                this.messages = this.messages.filter(m => m.id !== tempId);
                showToast('Mesaj gönderilemedi', 'error');
            } finally {
                this.sending = false;
                this.$nextTick(() => {
                    const inp = document.querySelector('#input-wrapper input[type="text"]');
                    if (inp) inp.focus();
                });
            }
        },

        async deleteMsg(msgId) {
            if (!confirm('Yönetici İşlemi: Bu mesajı silmek üzeresiniz.')) return;
            try {
                await fetch(`/api/chat/${ROOM_ID}/messages/${msgId}`, {
                    method: 'DELETE', headers: { 'X-CSRF-TOKEN': CSRF }
                });
                this.messages = this.messages.filter(m => m.id !== msgId);
            } catch(e) { showToast('Silinemedi', 'error'); }
        },

        setReply(msg) { this.replyingTo = msg; },

        isContinual(idx) {
            if (idx === 0) return false;
            const p = this.messages[idx-1], c = this.messages[idx];
            if (!p || !c || p.is_system_message || c.is_system_message) return false;
            return p.sender && c.sender && p.sender.id === c.sender.id;
        },

        scrollToBottom() {
            const el = this.$refs.msgContainer;
            if (el) el.scrollTop = el.scrollHeight;
        },

        formatTime(iso) {
            if (!iso) return '';
            return new Date(iso).toLocaleTimeString('tr-TR', { hour: '2-digit', minute: '2-digit' });
        },

        _playSound() {
            try {
                const ctx = new (window.AudioContext || window.webkitAudioContext)();
                const o = ctx.createOscillator(), g = ctx.createGain();
                o.connect(g); g.connect(ctx.destination);
                o.type = 'triangle';
                o.frequency.setValueAtTime(1200, ctx.currentTime);
                o.frequency.exponentialRampToValueAtTime(600, ctx.currentTime + 0.25);
                g.gain.setValueAtTime(0.4, ctx.currentTime);
                g.gain.exponentialRampToValueAtTime(0.01, ctx.currentTime + 0.25);
                o.start(); o.stop(ctx.currentTime + 0.25);
            } catch(e) {}
        },

        _appendRealtimeMessage(msg) {
            if (!msg || !msg.id) return;
            const exists = this.messages.some(m => String(m.id) === String(msg.id));
            if (exists) return;
            this.messages.push(msg);
            if (!this.lastMessageAt || msg.created_at > this.lastMessageAt) this.lastMessageAt = msg.created_at;
            this._idleCount = 0;
            this._playSound();
            this.$nextTick(() => this.scrollToBottom());
        },

        async sendTyping(isTyping) {
            if (this.roomType === 'announcements') return;
            if (isTyping && !this.inputValue.trim()) isTyping = false;
            if (this._typingSent === isTyping) return;
            this._typingSent = isTyping;
            clearTimeout(this._typingTimer);
            if (isTyping) {
                this._typingTimer = setTimeout(() => this.sendTyping(false), 2500);
            }
            try {
                await fetch(`/api/chat/${ROOM_ID}/typing`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF },
                    body: JSON.stringify({ is_typing: isTyping })
                });
            } catch(e) {}
        },

        _applyTyping(user, isTyping) {
            if (!user || user.id == this.currentUser.id) return;
            if (!isTyping) {
                delete this.typingUsers[user.id];
            } else {
                this.typingUsers[user.id] = user;
                setTimeout(() => { delete this.typingUsers[user.id]; this.typingUsers = { ...this.typingUsers }; }, 3500);
            }
            this.typingUsers = { ...this.typingUsers };
        },

        _applyReadReceipt(readerId, messageIds) {
            if (!Array.isArray(messageIds)) return;
            const ids = new Set(messageIds.map(id => String(id)));
            this.messages = this.messages.map(m => {
                if (!ids.has(String(m.id))) return m;
                const readers = new Set(m.read_by || []);
                readers.add(readerId);
                return { ...m, read_by: Array.from(readers), read_count: readers.size };
            });
        },

        toggleLeft() {
            window.dispatchEvent(new CustomEvent('toggle-left-sidebar'));
        },

        /* ═══════════ MUSIC SYSTEM — DISCORD BOT APPROACH ═══════════
         *
         * SERVER IS THE CLOCK. Position formula:
         *   playing  → pos = floor(Date.now()/1000 - started_at_unix)
         *   paused   → pos = state.position
         * All clients compute identical position from the same timestamp.
         * Reverb WebSocket pushes instant updates; polling is the fallback.
         * ══════════════════════════════════════════════════════════════════ */

        /* ══════════════════════════════════════════════════════════
         * PWA Background State & Network Resilience
         * ══════════════════════════════════════════════════════════ */
        _initBackgroundHandlers() {

            /* \u2500\u2500 visibilitychange: handle app going to background \u2500\u2500 */
            document.addEventListener('visibilitychange', () => {
                if (document.hidden) {
                    this._isBackground = true;
                    this._startSilentAudio(); /* keep audio pipeline alive on mobile */
                } else {
                    this._isBackground = false;
                    this._stopSilentAudio();
                    /* Immediately re-sync music state when returning */
                    this._syncMusic();
                    /* Resume suspended AudioContexts */
                    if (this._audioCtx?.state === 'suspended') this._audioCtx.resume().catch(() => {});
                }
            });

            /* \u2500\u2500 offline / online: auto-reconnect Reverb + WebRTC \u2500\u2500 */
            window.addEventListener('offline', () => {
                this.connected = false;
            });

            window.addEventListener('online', () => {
                /* Reconnect Echo WebSocket */
                clearTimeout(this._reconnectTimer);
                this._reconnectTimer = setTimeout(() => {
                    if (this._echo) {
                        try { this._echo.connector?.pusher?.connect?.(); } catch(e) {}
                    }
                    /* Re-sync music state */
                    this._syncMusic();
                    /* Re-create broken voice connections */
                    const room = getVoiceRoom();
                    if (this.voiceState.in_voice && room && room.state === 'disconnected') {
                        this._scheduleVoiceReconnect();
                    }
                    this.connected = true;
                }, 1500);
            });
        },

        /* \u2500\u2500 Silent audio trick: plays 1-second silence loop via Web Audio API
           This prevents mobile browsers from throttling/suspending the AudioContext
           and stops WebRTC audio tracks from being paused when the app is backgrounded.
           1 buffer of silence = ~172 bytes RAM. No network usage. \u2500\u2500 */
        _startSilentAudio() {
            if (this._silentAudio || !this.voiceState.in_voice) return;
            try {
                const ctx    = this._audioCtx ?? new (window.AudioContext || window.webkitAudioContext)();
                const buffer = ctx.createBuffer(1, ctx.sampleRate, ctx.sampleRate); /* 1s silence */
                const source = ctx.createBufferSource();
                source.buffer = buffer;
                source.loop   = true;
                source.connect(ctx.destination);
                source.start(0);
                this._silentAudio = source;
            } catch(e) {}
        },

        _stopSilentAudio() {
            if (!this._silentAudio) return;
            try { this._silentAudio.stop(); } catch(e) {}
            this._silentAudio = null;
        },

        /* ── Centralised Echo init — music + voice channels ── */
        _initEcho() {
            if (!window.LaravelEcho || !BROADCAST_DRIVER || BROADCAST_DRIVER === 'null' || BROADCAST_DRIVER === 'log') return;
            try {
                let echoConfig;
                if (BROADCAST_DRIVER === 'pusher') {
                    const c = BROADCAST_CONFIG.pusher;
                    echoConfig = {
                        broadcaster:  'pusher',
                        key:          c.key,
                        cluster:      c.cluster,
                        forceTLS:     c.scheme === 'https',
                        disableStats: true,
                    };
                } else {
                    /* reverb */
                    const c = BROADCAST_CONFIG.reverb;
                    echoConfig = {
                        broadcaster:       'reverb',
                        key:               c.key,
                        wsHost:            c.host,
                        wsPort:            c.port,
                        wssPort:           c.port,
                        forceTLS:          c.scheme === 'https',
                        disableStats:      true,
                        enabledTransports: ['ws'],
                    };
                }
                this._echo = window.KANKIO_ECHO || new window.LaravelEcho({
                    ...echoConfig,
                    auth: {
                        headers: { 'X-CSRF-TOKEN': CSRF },
                    },
                    authEndpoint: `/broadcasting/auth`,
                });
                window.KANKIO_ECHO = this._echo;

                this._joinUserRealtimeChannel();
                this._joinRoomRealtimeChannels();
                this._realtimeReady = true;

            } catch(e) {
                console.warn('[echo] init failed, polling fallback active', e);
            }
        },

        _joinUserRealtimeChannel() {
            if (!this._echo || this._stayConnectedUserChannelJoined) return;
            const userId = this.currentUser?.id;
            if (!userId) return;

            this._echo.private(`App.Models.User.${userId}`)
                .listen('.stay.connected', ({ triggered_by }) => {
                    window.dispatchEvent(new CustomEvent('open-stay-connected'));
                    const name = triggered_by?.username || 'Yönetici';
                    showToast(`${name} sürprizi başlattı`, 'success');
                });

            this._stayConnectedUserChannelJoined = true;
        },

        _leaveRoomRealtimeChannels() {
            if (!this._echo) return;

            const roomId = String(this._roomId || '');
            if (!roomId) return;

            try { this._echo.leave(`private-room.${roomId}.chat`); } catch (_) {}
            try { this._echo.leave(`private-room.${roomId}.music`); } catch (_) {}
            try { this._echo.leave(`private-room.${roomId}.voice`); } catch (_) {}

            try { this._echo.leave(`room.${roomId}.chat`); } catch (_) {}
            try { this._echo.leave(`room.${roomId}.music`); } catch (_) {}
            try { this._echo.leave(`room.${roomId}.voice`); } catch (_) {}
        },

        _joinRoomRealtimeChannels() {
            if (!this._echo) return;

            const roomId = String(this._roomId || '');
            if (!roomId) return;

            /* Chat events: realtime primary; polling remains only as a quiet fallback */
            this._echo.private(`room.${roomId}.chat`)
                .listen('.message.sent', ({ message }) => {
                    this._appendRealtimeMessage(message);
                })
                .listen('.message.deleted', ({ message_id }) => {
                    this.messages = this.messages.filter(m => String(m.id) !== String(message_id));
                })
                .listen('.typing', ({ user, is_typing }) => {
                    this._applyTyping(user, is_typing);
                })
                .listen('.messages.read', ({ reader_id, message_ids }) => {
                    this._applyReadReceipt(reader_id, message_ids);
                })
                .listen('.stay.connected', ({ triggered_by }) => {
                    window.dispatchEvent(new CustomEvent('open-stay-connected'));
                    const name = triggered_by?.username || 'Yönetici';
                    showToast(`${name} sürprizi başlattı`, 'success');
                });

            /* Music state push */
            this._echo.private(`room.${roomId}.music`)
                .listen('.music.state', (state) => {
                    this.musicState = state;
                    this._applyMusicState(state);
                });

            /* Voice state push updates the visible room only; LiveKit stays locked to _voiceRoomId. */
            this._echo.private(`room.${roomId}.voice`)
                .listen('.voice.state', (data) => {
                    if (String(this._voiceRoomId || '') !== String(this._roomId || '')) return;

                    this._applyVoiceState({
                        participants: data.participants || [],
                        settings: data.settings || this.voiceState.settings
                    });
                })
                .listen('.voice.participant', ({ action, participant }) => {
                    if (String(this._voiceRoomId || '') !== String(this._roomId || '')) return;
                    this._applyVoiceParticipant(action, participant);
                })
                .listen('.voice.mute', ({ user_id, is_muted }) => {
                    if (String(this._voiceRoomId || '') !== String(this._roomId || '')) return;
                    this._applyVoiceParticipant('updated', { id: user_id, is_muted });
                });
        },

        _initYT() {
            /* YouTube postMessage events (ended, error) */
            window.addEventListener('message', (ev) => {
                try {
                    const d = JSON.parse(ev.data);
                    /* Primary: onStateChange with info=0 means video ended */
                    if (d.event === 'onStateChange' && d.info === 0)
                        this._onVideoEnded();
                    /* infoDelivery: captures responses from getDuration/getCurrentTime polls */
                    if (d.event === 'infoDelivery' && d.info) {
                        const info = d.info;
                        if (info.playerState === 0) this._onVideoEnded();

                        /* Store currentTime from periodic poll */
                        if (typeof info.currentTime === 'number' && info.currentTime > 0)
                            this._lastCurrentTime = info.currentTime;

                        /* Capture duration — works even without YouTube API key */
                        if (typeof info.duration === 'number' && info.duration > 0 && this.musicState.video_id) {
                            if (info.duration !== this._knownDuration) {
                                this._knownDuration = info.duration;
                                /* Set end timer if not already set */
                                if (!this._endTimer && this.musicState.is_playing && this.musicState.started_at_unix) {
                                    const ms = (this.musicState.started_at_unix + info.duration) * 1000 - Date.now();
                                    if (ms > 0 && ms < 10800000)
                                        this._endTimer = setTimeout(() => { this._endTimer = null; this._onVideoEnded(); }, ms + 2000);
                                }
                            }
                            /* Direct: currentTime reached end */
                            if (this._lastCurrentTime > 0 && this._lastCurrentTime >= info.duration - 2)
                                this._onVideoEnded();
                        }
                    }
                    if (d.event === 'onError') {
                        const msg = (d.info === 101 || d.info === 150)
                            ? 'Bu video embed edilemiyor — başka video deneyin'
                            : `YouTube hatası: ${d.info}`;
                        showToast(msg, 'error');
                    }
                } catch(e) {}
            });

            /* Periodic poll: ask YouTube player for currentTime+duration every 8s.
               This captures duration without API key AND detects end via currentTime. */
            this._ytPollTimer = setInterval(() => {
                if (this._ytIframe && this.musicState.video_id && this.musicState.is_playing) {
                    this._ytCmd('getDuration');
                    this._ytCmd('getCurrentTime');
                }
            }, 8000);

            /* Initial state fetch */
            this._syncMusic();
        },

        async _syncMusic() {
            try {
                const r = await fetch(`/api/music/${ROOM_ID}`);
                if (!r.ok) return;
                const state = await r.json();
                this.musicState = state;
                this._applyMusicState(state);
            } catch(e) {}
        },

        /* Calculate current playback position.
         * Mirrors the server formula: floor(now - started_at_unix). */
        _calcPos(state) {
            if (state.is_playing && state.started_at_unix) {
                return Math.max(0, Math.floor(Date.now() / 1000 - state.started_at_unix));
            }
            return Math.max(0, state.position || 0);
        },

        _ytSrc(videoId, startSecs, muted, playing) {
            const m  = muted   ? '&mute=1' : '&mute=0';
            const ap = playing ? '&autoplay=1' : '&autoplay=0';
            return `https://www.youtube.com/embed/${videoId}?enablejsapi=1&rel=0&modestbranding=1&controls=0${ap}${m}&start=${Math.max(0, Math.floor(startSecs))}`;
        },

        _ytLoad(videoId, startSecs, muted, playing) {
            const c = document.getElementById('yt-player');
            if (!c) return;
            c.innerHTML = '';
            const f = document.createElement('iframe');
            f.width  = '200'; f.height = '112';
            f.src    = this._ytSrc(videoId, startSecs, muted, playing);
            f.allow  = 'autoplay; encrypted-media';
            f.setAttribute('frameborder', '0');
            f.style.cssText = 'border:0;width:100%;height:100%;display:block;';
            c.appendChild(f);
            this._ytIframe  = f;
            this._ytCurVid  = videoId;
        },

        /* ── Ensure iframe exists in user gesture context ──
           Creates a blank YouTube embed so the browser associates audio
           permission with it. Subsequent loadVideoById calls inherit this
           permission without needing a new user gesture. */
        _ensureIframeReady() {
            if (this._ytIframe) {
                /* Iframe exists — just unmute it via postMessage (user gesture) */
                this._ytCmd('unMute');
                this._ytCmd('setVolume', [100]);
                return;
            }
            const c = document.getElementById('yt-player');
            if (!c) return;
            c.innerHTML = '';
            const f = document.createElement('iframe');
            f.width = '200'; f.height = '112';
            /* Load minimal embed with autoplay+sound — this "locks" audio permission
               to this iframe element. loadVideoById will reuse the permission. */
            f.src = 'https://www.youtube.com/embed/?enablejsapi=1&autoplay=0&mute=0';
            f.allow = 'autoplay; encrypted-media';
            f.setAttribute('frameborder', '0');
            f.style.cssText = 'border:0;width:100%;height:100%;display:block;';
            c.appendChild(f);
            this._ytIframe = f;
        },

        _ytCmd(func, args) {
            if (!this._ytIframe || !this._ytIframe.contentWindow) return;
            this._ytIframe.contentWindow.postMessage(
                JSON.stringify({ event: 'command', func, args: args ?? '' }), '*'
            );
        },

        /* ── Core sync — called on every push/poll result ── */
        _applyMusicState(state) {
            /* Always clear pending end timer — will be reset below if needed */
            if (this._endTimer) { clearTimeout(this._endTimer); this._endTimer = null; }

            /* Discord-style: music only plays when user is in voice chat */
            if (!this.voiceState.in_voice) {
                if (this._ytIframe) { this._ytCmd('pauseVideo'); }
                return;
            }

            if (!state.video_id) {
                if (this._ytIframe) { this._ytIframe.src = ''; this._ytIframe = null; }
                this._ytCurVid = ''; this._ytCurPlay = null;
                this._knownDuration = 0; this._lastCurrentTime = 0;
                this._playerMuted = true; /* reset: next track starts muted */
                return;
            }

            const pos = this._calcPos(state);

            /* ① New track */
            if (this._ytCurVid !== state.video_id) {
                this._knownDuration = 0; this._lastCurrentTime = 0;
                if (this._ytIframe && !this._playerMuted) {
                    /* CRITICAL: Reuse existing iframe via postMessage API.
                       loadVideoById inherits the audio permission that was
                       granted when the iframe was created in user gesture context.
                       Creating a new iframe here would LOSE audio permission
                       because _applyMusicState runs in async/event context. */
                    this._ytCmd('loadVideoById', [{ videoId: state.video_id, startSeconds: Math.floor(pos) }]);
                    this._ytCmd('unMute');
                    this._ytCmd('setVolume', [100]);
                    if (!state.is_playing) this._ytCmd('pauseVideo');
                    this._ytCurVid = state.video_id;
                } else {
                    /* No iframe yet, or player is muted — safe to create new iframe */
                    const shouldMute = this._playerMuted;
                    this._ytLoad(state.video_id, pos, shouldMute, state.is_playing);
                    this._playerMuted = shouldMute;
                }
                this._ytCurPlay = state.is_playing;
                /* End timer set below */
            }
            /* ② Play state changed */
            else if (state.is_playing !== this._ytCurPlay) {
                this._ytCurPlay = state.is_playing;
                if (state.is_playing) {
                    if (!this._playerMuted && this._ytIframe) {
                        /* Reuse iframe — seek + play preserves audio lock */
                        this._ytCmd('seekTo', [Math.floor(pos), true]);
                        this._ytCmd('playVideo');
                        this._ytCmd('unMute');
                    } else {
                        /* Muted or no iframe: safe to reload for position sync */
                        this._ytLoad(state.video_id, pos, this._playerMuted, true);
                    }
                } else {
                    this._ytCmd('pauseVideo');
                }
            }
            /* ③ Same track, same state — let YouTube play naturally */

            /* Set JS end timer — works even if iframe postMessage doesn't fire */
            const dur = state.video_duration || this._knownDuration;
            if (state.is_playing && state.started_at_unix && dur > 0) {
                const msLeft = (state.started_at_unix + dur) * 1000 - Date.now();
                if (msLeft > 0 && msLeft < 10800000)
                    this._endTimer = setTimeout(() => { this._endTimer = null; this._onVideoEnded(); }, msLeft + 2000);
                else if (msLeft <= 0)
                    this._onVideoEnded();
            }
        },

        /* ── Sesi Aç / Kapat ── */
        muteToggle() {
            if (!this.musicState.video_id) return;
            if (this._playerMuted) {
                /* User gesture context (click/tap): reload iframe with sound.
                   This is the ONLY reliable way on iOS Safari — postMessage
                   unMute() is NOT treated as a user gesture inside the iframe.
                   On Chrome, postMessage works, but reload is universally safe. */
                const pos = this._calcPos(this.musicState);
                this._ytLoad(this.musicState.video_id, pos, false, this.musicState.is_playing);
                this._ytCurVid = this.musicState.video_id;
                this._ytCurPlay = this.musicState.is_playing;
                this._playerMuted = false;
                /* Re-set end timer for new iframe */
                if (this._endTimer) { clearTimeout(this._endTimer); this._endTimer = null; }
                const dur = this.musicState.video_duration || this._knownDuration;
                if (this.musicState.is_playing && this.musicState.started_at_unix && dur > 0) {
                    const ms = (this.musicState.started_at_unix + dur) * 1000 - Date.now();
                    if (ms > 0) this._endTimer = setTimeout(() => { this._endTimer = null; this._onVideoEnded(); }, ms + 2000);
                }
            } else {
                this._ytCmd('mute');
                this._playerMuted = true;
            }
        },

        async _onVideoEnded() {
            /* Debounce: ignore duplicate events within 5 seconds */
            const now = Date.now();
            if (now - this._videoEndedAt < 5000) return;
            this._videoEndedAt = now;
            if (this._endTimer) { clearTimeout(this._endTimer); this._endTimer = null; }
            /* Auto-skip via slash command API */
            try {
                const r = await fetch(`/api/music/${ROOM_ID}/command`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
                    body: JSON.stringify({ command: '/geç' })
                });
                const data = await r.json();
                if (data.state) {
                    this.musicState = data.state;
                    this._applyMusicState(data.state);
                }
            } catch(e) {}
        },

        /* ── Per-user accent color: stable mapping by userId % palette ──
           Returns { text, bg, border } CSS rgba strings. */
        _userColor(userId) {
            if (!userId) return { text: 'rgba(113,113,122,1)', bg: 'rgba(24,24,27,0.9)', border: 'rgba(255,255,255,0.05)' };
            const p = [
                { text:'rgba(99,102,241,1)',  bg:'rgba(99,102,241,0.1)',  border:'rgba(99,102,241,0.22)'  },
                { text:'rgba(168,85,247,1)',  bg:'rgba(168,85,247,0.1)', border:'rgba(168,85,247,0.22)'  },
                { text:'rgba(236,72,153,1)',  bg:'rgba(236,72,153,0.1)', border:'rgba(236,72,153,0.22)'  },
                { text:'rgba(20,184,166,1)',  bg:'rgba(20,184,166,0.1)', border:'rgba(20,184,166,0.22)'  },
                { text:'rgba(245,158,11,1)',  bg:'rgba(245,158,11,0.1)', border:'rgba(245,158,11,0.22)'  },
                { text:'rgba(59,130,246,1)',  bg:'rgba(59,130,246,0.1)', border:'rgba(59,130,246,0.22)'  },
                { text:'rgba(34,197,94,1)',   bg:'rgba(34,197,94,0.1)',  border:'rgba(34,197,94,0.22)'   },
                { text:'rgba(249,115,22,1)',  bg:'rgba(249,115,22,0.1)', border:'rgba(249,115,22,0.22)'  },
                { text:'rgba(6,182,212,1)',   bg:'rgba(6,182,212,0.1)',  border:'rgba(6,182,212,0.22)'   },
                { text:'rgba(132,204,22,1)',  bg:'rgba(132,204,22,0.1)', border:'rgba(132,204,22,0.22)'  },
            ];
            return p[parseInt(userId) % p.length];
        },

        /* ══════════ VOICE MESSAGE RECORDING ═══════════ */
        async startVoiceRecord() {
            try {
                this._voiceRecStream = await navigator.mediaDevices.getUserMedia({
                    audio: { echoCancellation: true, noiseSuppression: true }, video: false
                });
            } catch(e) { showToast('Mikrofon erişimi reddedildi', 'error'); return; }

            this._voiceChunks = [];
            this._voiceRecordSecs = 0;
            this._voiceRecording = true;

            const mimeType = MediaRecorder.isTypeSupported('audio/webm;codecs=opus') ? 'audio/webm;codecs=opus'
                           : MediaRecorder.isTypeSupported('audio/mp4') ? 'audio/mp4' : '';

            this._voiceMediaRec = new MediaRecorder(this._voiceRecStream, mimeType ? { mimeType } : {});
            this._voiceMediaRec.ondataavailable = (e) => { if (e.data.size > 0) this._voiceChunks.push(e.data); };
            this._voiceMediaRec.start(250);

            this._voiceRecTimer = setInterval(() => {
                this._voiceRecordSecs++;
                if (this._voiceRecordSecs >= 300) this.stopVoiceRecord();
            }, 1000);
        },

        cancelVoiceRecord() {
            clearInterval(this._voiceRecTimer);
            if (this._voiceMediaRec && this._voiceMediaRec.state !== 'inactive') this._voiceMediaRec.stop();
            if (this._voiceRecStream) this._voiceRecStream.getTracks().forEach(t => t.stop());
            this._voiceRecording = false;
            this._voiceRecordSecs = 0;
        },

        async stopVoiceRecord() {
            if (!this._voiceMediaRec || this._voiceMediaRec.state === 'inactive') return;
            const duration = this._voiceRecordSecs;
            clearInterval(this._voiceRecTimer);

            await new Promise(resolve => {
                this._voiceMediaRec.onstop = resolve;
                this._voiceMediaRec.stop();
            });

            if (this._voiceRecStream) this._voiceRecStream.getTracks().forEach(t => t.stop());
            this._voiceRecording = false;
            this._voiceRecordSecs = 0;

            if (this._voiceChunks.length === 0) return;

            const blob = new Blob(this._voiceChunks, { type: this._voiceMediaRec.mimeType || 'audio/webm' });
            const ext = (this._voiceMediaRec.mimeType || '').includes('mp4') ? 'mp4' : 'webm';

            const fd = new FormData();
            fd.append('audio', blob, `voice.${ext}`);
            fd.append('audio_duration', String(duration));
            if (this.replyingTo) fd.append('reply_to', String(this.replyingTo.id));

            this.replyingTo = null;
            this.sending = true;

            try {
                const r = await fetch(`/api/chat/${ROOM_ID}/messages`, {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': CSRF },
                    body: fd,
                });
                if (!r.ok) { showToast('Sesli mesaj gönderilemedi', 'error'); return; }
                const msg = await r.json();
                this.messages.push(msg);
                if (!this.lastMessageAt || msg.created_at > this.lastMessageAt) this.lastMessageAt = msg.created_at;
                this.$nextTick(() => this.scrollToBottom());
            } catch(e) { showToast('Sesli mesaj gönderilemedi', 'error'); }
            finally { this.sending = false; }
        },

        /* ══════════ AUDIO PLAYBACK ═══════════ */
        playAudio(msg) {
            if (this._playingAudioId === msg.id) {
                if (this._audioEl) { this._audioEl.pause(); this._audioEl = null; }
                clearInterval(this._audioProgressTimer);
                this._playingAudioId = null;
                this._audioProgress = 0;
                return;
            }
            if (this._audioEl) { this._audioEl.pause(); }
            clearInterval(this._audioProgressTimer);

            this._playingAudioId = msg.id;
            this._audioProgress = 0;

            const el = new Audio(msg.audio_url);
            this._audioEl = el;
            el.play().catch(() => {});

            this._audioProgressTimer = setInterval(() => {
                if (el.duration && el.duration > 0) {
                    this._audioProgress = Math.min(100, (el.currentTime / el.duration) * 100);
                }
            }, 200);

            el.onended = () => {
                clearInterval(this._audioProgressTimer);
                this._playingAudioId = null;
                this._audioProgress = 0;
                this._audioEl = null;
            };
        },

        /* ══════════ ARCHIVED MESSAGES ═══════════ */
        async toggleArchived() {
            this.showArchived = !this.showArchived;
            if (this.showArchived && this.archivedMessages.length === 0) {
                await this.loadArchived();
            }
        },

        async loadArchived(page = 1) {
            this.archivedLoading = true;
            try {
                const r = await fetch(`/api/chat/${this._roomId}/archived?page=${page}`);
                if (!r.ok) return;
                const data = await r.json();
                if (page === 1) this.archivedMessages = data.messages;
                else this.archivedMessages.push(...data.messages);
                this.archivedPage = page;
                this.archivedHasMore = data.has_more;
                this.archivedCount = data.total;
            } catch(e) {}
            finally { this.archivedLoading = false; }
        },

        /* ══════════ MESSAGE SEEN TRACKING ═══════════ */
        _startSeenTracking() {
            this._seenTimer = setInterval(() => {
                if (document.hidden || !this.messages.length) return;
                const unread = this.messages
                    .filter(m => m.sender?.id !== this.currentUser.id && !m._seen && !m.is_system_message)
                    .map(m => m.id);
                if (unread.length === 0) return;

                this.messages.forEach(m => { if (unread.includes(m.id)) m._seen = true; });

                fetch(`/api/chat/${this._roomId}/seen`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF },
                    body: JSON.stringify({ message_ids: unread })
                }).catch(() => {});
            }, 10000);
        },

        /* ══════════ VOICE CHAT (LiveKit) ═══════════ */
        async voiceJoin(options = {}) {
            const isReconnect = !!options.reconnect;

            if (this._voiceJoinInFlight) {
                console.log('[voice] voiceJoin rejected: join already in flight');
                return;
            }
            if ((this.voiceConnectionStatus === 'connecting' || this.voiceConnectionStatus === 'reconnecting') && !isReconnect) {
                console.log('[voice] voiceJoin rejected: status=' + this.voiceConnectionStatus);
                return;
            }
            if (getVoiceRoom() && this.voiceState?.in_voice && !isReconnect) {
                console.log('[voice] voiceJoin rejected: already connected and in_voice');
                return;
            }

            this._voiceJoinInFlight = true;
            this._manualVoiceLeave = false;
            this.voiceConnectionStatus = isReconnect ? 'reconnecting' : 'connecting';
            this._playerMuted = false;
            this._ensureIframeReady();

            try {
                if (!isReconnect) {
                    try {
                        stopPermissionStreamIfAny();
                        KankioVoiceRuntime.permissionStream = await navigator.mediaDevices.getUserMedia({ audio: true });
                    } catch(e) {
                        this.voiceConnectionStatus = 'failed';
                        showToast('Mikrofon erişimi reddedildi', 'error');
                        return;
                    }
                }

                this._voiceRoomId = this._roomId;
                window.KANKIO_ACTIVE_VOICE_ROOM_ID = this._voiceRoomId;
                console.log('[voice] voiceJoin starting. room=' + this._voiceRoomId + ' reconnect=' + isReconnect);
                const r = await fetch(`/api/voice/${this._voiceRoomId}/join`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
                    body: JSON.stringify({ reconnect: isReconnect })
                });
                const state = await this._safeJson(r, 'Ses API');

                if (!r.ok) {
                    this.voiceConnectionStatus = 'failed';
                    showToast(state.error || 'Ses API hatası oluştu', 'error');
                    return;
                }

                if (!state.livekit_url || !state.livekit_token) {
                    this.voiceConnectionStatus = 'failed';
                    showToast('Sunucu LiveKit bilgilerini döndürmedi. .env dosyasını kontrol edin.', 'error');
                    return;
                }

                this._applyVoiceState(state);
                await this._connectLiveKit(state, options);
                this._voiceReconnectAttempts = 0;
                this.voiceConnectionStatus = 'connected';
                console.log('[voice] voiceJoin succeeded. room=' + this._voiceRoomId);
                showToast(isReconnect ? 'Ses kanalına yeniden bağlanıldı' : 'Ses kanalına bağlanıldı', 'success');
                this._startVoicePolling();

                if (this.musicState.video_id) {
                    this._playerMuted = false;
                    this._applyMusicState(this.musicState);
                }
            } catch (error) {
                console.error('[voice] voiceJoin failed:', error);
                this.voiceConnectionStatus = 'failed';
                showToast('Ses kanalına bağlanılamadı: ' + error.message, 'error');
                this._scheduleVoiceReconnect();
            } finally {
                this._voiceJoinInFlight = false;
            }
        },

        async _connectLiveKit(state, options = {}) {
            const Room = LivekitClient.Room;
            const RoomEvent = LivekitClient.RoomEvent;

            const existingRoom = getVoiceRoom();
            if (existingRoom) {
                const shouldDisconnect = options.forceReconnect || existingRoom.state === 'disconnected' || !existingRoom.localParticipant;
                if (shouldDisconnect) {
                    console.log('[voice] _connectLiveKit: disconnecting existing room. state=' + existingRoom.state);
                    try { await existingRoom.disconnect(); } catch(e) {}
                    setVoiceRoom(null);
                } else {
                    console.log('[voice] _connectLiveKit: reusing healthy room. state=' + existingRoom.state);
                    return;
                }
            }

            const room = new Room({ adaptiveStream: true, dynacast: true });
            setVoiceRoom(room);

            room.on(RoomEvent.TrackSubscribed, (track, publication, participant) => {
                if (track.kind !== 'audio') return;
                const el = track.attach();
                el.muted = !!this.voiceState.is_deafened;
                KankioVoiceRuntime.audioEls[participant.identity] = el;
            });

            room.on(RoomEvent.TrackUnsubscribed, (track, publication, participant) => {
                if (track.kind !== 'audio') return;
                track.detach();
                if (KankioVoiceRuntime.audioEls[participant.identity]) {
                    KankioVoiceRuntime.audioEls[participant.identity].remove();
                    delete KankioVoiceRuntime.audioEls[participant.identity];
                }
            });

            room.on(RoomEvent.ActiveSpeakersChanged, (speakers) => {
                const speakingDict = {};
                for (const p of speakers) speakingDict[p.identity] = true;
                this._speakingUsers = speakingDict;
                this._syncLocalSpeaking(!!speakingDict[String(this.currentUser.id)]);
            });

            if (RoomEvent.ConnectionQualityChanged) {
                room.on(RoomEvent.ConnectionQualityChanged, (quality, participant) => {
                    if (participant && participant.identity !== String(this.currentUser.id)) return;
                    this._sendVoiceQuality(this._mapLiveKitQuality(quality));
                });
            }

            if (RoomEvent.Reconnecting) {
                room.on(RoomEvent.Reconnecting, () => {
                    this.voiceConnectionStatus = 'reconnecting';
                    this._sendVoiceQuality('poor', true);
                });
            }

            if (RoomEvent.Reconnected) {
                room.on(RoomEvent.Reconnected, () => {
                    this.voiceConnectionStatus = 'connected';
                    this._sendVoiceQuality('good', true);
                });
            }

            room.on(RoomEvent.Disconnected, () => {
                if (this._manualVoiceLeave) return;
                this.voiceConnectionStatus = 'reconnecting';
                this._scheduleVoiceReconnect();
            });

            await room.connect(state.livekit_url, state.livekit_token);
            await this._applyLocalMicState();
        },

        async voiceLeave() {
            this._manualVoiceLeave = true;
            clearInterval(this._voiceTimer);
            clearTimeout(this._voiceReconnectTimer);
            await this._disconnectLocalVoice(true);
            this.voiceConnectionStatus = 'idle';
            this.voiceState = {
                in_voice: false, is_muted: false, is_deafened: false, can_speak: true,
                connection_quality: 'unknown', reconnect_count: 0, can_moderate: false,
                settings: {}, participants: []
            };
            if (this._ytIframe) this._ytCmd('pauseVideo');
            this._playerMuted = true;
            KankioVoiceRuntime.lastAppliedMicEnabled = null;
        },

        async _disconnectLocalVoice(notifyServer) {
            this._speakingUsers = {};

            const room = getVoiceRoom();
            if (room) {
                try {
                    const localParticipant = room.localParticipant;
                    if (localParticipant) {
                        try {
                            await localParticipant.setMicrophoneEnabled(false);
                        } catch (e) {
                            console.warn('[voice] failed to disable microphone before disconnect', e);
                        }
                        const trackPubs = Array.from(localParticipant.trackPublications.values?.() || []);
                        for (const pub of trackPubs) {
                            try {
                                if (pub?.kind === 'audio') {
                                    const track = pub.track;
                                    try { await localParticipant.unpublishTrack(track); } catch (e) {}
                                    try { track?.stop?.(); } catch (e) {}
                                }
                            } catch (e) {}
                        }
                    }
                    await room.disconnect();
                } catch (e) {
                    console.warn('[voice] room disconnect failed', e);
                }
            }

            for (const uid in KankioVoiceRuntime.audioEls) {
                if (KankioVoiceRuntime.audioEls[uid]) {
                    try { KankioVoiceRuntime.audioEls[uid].remove(); } catch (e) {}
                }
            }
            KankioVoiceRuntime.audioEls = {};

            if (KankioVoiceRuntime.permissionStream) {
                for (const track of KankioVoiceRuntime.permissionStream.getTracks()) {
                    try { track.stop(); } catch (e) {
                        console.warn('[voice] failed to stop permission stream track', e);
                    }
                }
                KankioVoiceRuntime.permissionStream = null;
            }

            resetVoiceRuntime();
            setVoiceRoom(null);

            if (notifyServer && this._voiceRoomId) {
                fetch(`/api/voice/${this._voiceRoomId}/leave`, {
                    method: 'POST', headers: { 'X-CSRF-TOKEN': CSRF }
                }).catch(() => {});
            }
            this._voiceRoomId = null;
            window.KANKIO_ACTIVE_VOICE_ROOM_ID = null;
        },

        async voiceToggleMute(force = null) {
            if (!this._voiceRoomId) return;
            const newMuted = force === null ? !this.voiceState.is_muted : !!force;
            if (!this.voiceState.can_speak && !newMuted) {
                showToast('Bu odada konuşma izniniz yok', 'error');
                return;
            }
            await this._setLocalMicMuted(newMuted);
            try {
                const r = await fetch(`/api/voice/${this._voiceRoomId}/mute`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
                    body: JSON.stringify({ is_muted: newMuted })
                });
                const data = await this._safeJson(r, 'Ses API');
                if (!r.ok) { showToast(data.error || 'Mikrofon durumu güncellenemedi', 'error'); return; }
                this._applyVoiceState({ is_muted: data.is_muted, participant: data.participant });
            } catch(e) { showToast('Mikrofon durumu güncellenemedi', 'error'); }
        },

        async voiceToggleDeafen() {
            if (!this._voiceRoomId) return;
            const newDeafen = !this.voiceState.is_deafened;
            this._setRemoteAudioMuted(newDeafen);
            try {
                const r = await fetch(`/api/voice/${this._voiceRoomId}/deafen`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
                    body: JSON.stringify({ is_deafened: newDeafen })
                });
                const data = await this._safeJson(r, 'Ses API');
                if (!r.ok) { showToast(data.error || 'Kulaklık durumu güncellenemedi', 'error'); return; }
                this._applyVoiceState({ is_deafened: data.is_deafened, participant: data.participant });
            } catch(e) { showToast('Kulaklık durumu güncellenemedi', 'error'); }
        },

        async voiceMuteAll() {
            if (!this.voiceState.can_moderate || !this._voiceRoomId) return;
            if (!confirm('Ses kanalındaki herkesi susturmak istiyor musunuz?')) return;
            await fetch(`/api/voice/${this._voiceRoomId}/mute-all`, {
                method: 'POST', headers: { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' }
            }).catch(() => {});
        },

        async voiceKick(participant) {
            if (!this.voiceState.can_moderate || !this._voiceRoomId || !participant?.id) return;
            if (!confirm(`${participant.username || 'Kullanıcı'} ses kanalından atılsın mı?`)) return;
            try {
                const r = await fetch(`/api/voice/${this._voiceRoomId}/participants/${participant.id}`, {
                    method: 'DELETE',
                    headers: { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' }
                });
                const data = await this._safeJson(r, 'Ses API');
                if (!r.ok) showToast(data.error || 'Kullanıcı atılamadı', 'error');
            } catch(e) { showToast('Kullanıcı atılamadı', 'error'); }
        },

        async voiceSetSpeakPermission(participant, canSpeak) {
            if (!this.voiceState.can_moderate || !this._voiceRoomId || !participant?.id) return;
            try {
                const r = await fetch(`/api/voice/${this._voiceRoomId}/participants/${participant.id}/speak`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
                    body: JSON.stringify({ can_speak: !!canSpeak })
                });
                const data = await this._safeJson(r, 'Ses API');
                if (!r.ok) { showToast(data.error || 'Konuşma izni güncellenemedi', 'error'); return; }
                this._applyVoiceParticipant('updated', data.participant);
            } catch(e) { showToast('Konuşma izni güncellenemedi', 'error'); }
        },

        async voiceUpdateSettings(patch) {
            if (!this.voiceState.can_moderate || !this._voiceRoomId) return;
            try {
                const r = await fetch(`/api/voice/${this._voiceRoomId}/settings`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
                    body: JSON.stringify(patch)
                });
                const data = await this._safeJson(r, 'Ses API');
                if (!r.ok) { showToast(data.error || 'Ses ayarı güncellenemedi', 'error'); return; }
                this.voiceState.settings = { ...this.voiceState.settings, ...(data.settings || patch) };
            } catch(e) { showToast('Ses ayarı güncellenemedi', 'error'); }
        },

        _startVoicePolling() {
            clearInterval(this._voiceTimer);
            this._voiceTimer = setInterval(async () => {
                if (!this.voiceState.in_voice || !this._voiceRoomId) return;
                try {
                    const sr = await fetch(`/api/voice/${this._voiceRoomId}/state`, { headers: { 'Accept': 'application/json' } });
                    const state = await this._safeJson(sr, 'Ses API');
                    if (!sr.ok) return;
                    if (!state.in_voice && this.voiceState.in_voice) {
                        showToast('Ses kanalından çıkarıldınız', 'error');
                        await this._disconnectLocalVoice(false);
                        this.voiceConnectionStatus = 'idle';
                    }
                    this._applyVoiceState(state);
                } catch(e) {}
            }, 3000);
        },

        _applyVoiceState(state) {
            if (!state) return;
            const token = this.voiceState.livekit_token;
            const url = this.voiceState.livekit_url;
            this.voiceState = { ...this.voiceState, ...state, livekit_token: state.livekit_token || token, livekit_url: state.livekit_url || url };
            if (state.participant) this._applyVoiceParticipant('updated', state.participant);
            this._refreshSpeakingUsers();
            this._applyLocalMicState();
            this._setRemoteAudioMuted(!!this.voiceState.is_deafened);
        },

        isViewingVoiceRoom() {
            return String(this._voiceRoomId || '') === String(this._roomId || '');
        },

        voiceParticipants() {
            if (!this.isViewingVoiceRoom()) return [];
            return this.voiceState.participants || [];
        },

        voiceParticipantCount() {
            return this.voiceParticipants().length;
        },

        _applyVoiceParticipant(action, participant) {
            if (!participant || !participant.id) return;
            if (action === 'kicked' && String(participant.id) === String(this.currentUser.id)) {
                showToast('Ses kanalından çıkarıldınız', 'error');
                this.voiceLeave();
                return;
            }
            const list = [...(this.voiceState.participants || [])];
            const idx = list.findIndex(p => String(p.id) === String(participant.id));
            if (action === 'kicked') {
                this.voiceState.participants = list.filter(p => String(p.id) !== String(participant.id));
                return;
            }
            if (idx === -1) list.push(participant);
            else list[idx] = { ...list[idx], ...participant };
            this.voiceState.participants = list;
            if (String(participant.id) === String(this.currentUser.id)) {
                this.voiceState = { ...this.voiceState, ...participant, in_voice: true };
                this._applyLocalMicState();
            }
            this._refreshSpeakingUsers();
        },

        _refreshSpeakingUsers() {
            const speaking = {};
            for (const p of (this.voiceState.participants || [])) {
                if (p.is_speaking) speaking[p.id] = true;
            }
            this._speakingUsers = { ...speaking, ...this._speakingUsers };
        },

        async _applyLocalMicState() {
            const room = getVoiceRoom();
            if (!room?.localParticipant) {
                console.log('[voice] _applyLocalMicState skipped: no localParticipant');
                return;
            }
            const shouldMute = !!this.voiceState.is_muted || !this.voiceState.can_speak || (this.voicePrefs.pushToTalk && !this._pttDown);
            if (KankioVoiceRuntime.lastAppliedMicEnabled === shouldMute) {
                console.log('[voice] _applyLocalMicState dedup: already ' + shouldMute);
                return;
            }
            KankioVoiceRuntime.lastAppliedMicEnabled = shouldMute;
            await this._setLocalMicMuted(shouldMute, false);
        },

        async _setLocalMicMuted(muted, updateState = true) {
            if (updateState) this.voiceState.is_muted = muted;
            const room = getVoiceRoom();
            const localParticipant = room?.localParticipant;
            if (!localParticipant) {
                console.log('[voice] _setLocalMicMuted skipped: no localParticipant');
                return;
            }
            if (KankioVoiceRuntime.micToggleInFlight) {
                console.log('[voice] _setLocalMicMuted skipped: toggle already in flight');
                return;
            }
            KankioVoiceRuntime.micToggleInFlight = true;
            try {
                console.log('[voice] _setLocalMicMuted: enabled=' + !muted);
                await localParticipant.setMicrophoneEnabled(!muted, {
                    echoCancellation: !!this.voicePrefs.echoCancellation,
                    noiseSuppression: !!this.voicePrefs.noiseSuppression,
                    autoGainControl: true,
                }, {
                    dtx: !!this.voicePrefs.lowBandwidth,
                    audioBitrate: this.voicePrefs.lowBandwidth ? 16000 : 32000,
                });
            } catch(e) {
                console.warn('[voice] _setLocalMicMuted error:', e);
                KankioVoiceRuntime.lastAppliedMicEnabled = null; /* allow retry on failure */
            } finally {
                KankioVoiceRuntime.micToggleInFlight = false;
            }
        },

        _setRemoteAudioMuted(muted) {
            this.voiceState.is_deafened = muted;
            for (const uid in KankioVoiceRuntime.audioEls) {
                KankioVoiceRuntime.audioEls[uid].muted = muted;
            }
        },

        _scheduleVoiceReconnect() {
            if (this._manualVoiceLeave || !this._voiceRoomId) return;
            clearTimeout(this._voiceReconnectTimer);
            const delay = Math.min(15000, 1000 * Math.pow(2, this._voiceReconnectAttempts++));
            this.voiceConnectionStatus = 'reconnecting';
            console.log('[voice] _scheduleVoiceReconnect: attempt=' + this._voiceReconnectAttempts + ' delay=' + delay + 'ms');
            this._voiceReconnectTimer = setTimeout(() => this.voiceJoin({ reconnect: true }), delay);
        },

        async _syncLocalSpeaking(isSpeaking) {
            if (!this._voiceRoomId || this.voiceState._lastSpeaking === isSpeaking) return;
            this.voiceState._lastSpeaking = isSpeaking;
            fetch(`/api/voice/${this._voiceRoomId}/speaking`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF },
                body: JSON.stringify({ is_speaking: isSpeaking })
            }).catch(() => {});
        },

        _sendVoiceQuality(quality, reconnected = false) {
            if (!this._voiceRoomId) return;
            fetch(`/api/voice/${this._voiceRoomId}/quality`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF },
                body: JSON.stringify({ connection_quality: quality, reconnected })
            }).catch(() => {});
        },

        _mapLiveKitQuality(quality) {
            const raw = String(quality || '').toLowerCase();
            if (raw.includes('excellent')) return 'excellent';
            if (raw.includes('good')) return 'good';
            if (raw.includes('poor') || raw.includes('lost')) return 'poor';
            if (quality === 3) return 'excellent';
            if (quality === 2) return 'good';
            if (quality === 1) return 'poor';
            return 'unknown';
        },

        qualityLabel(q) {
            return ({ excellent: 'Çok iyi', good: 'İyi', poor: 'Zayıf', unknown: 'Bilinmiyor' })[q || 'unknown'] || 'Bilinmiyor';
        },

        _initPushToTalk() {
            window.addEventListener('keydown', (e) => {
                if (!this.voicePrefs.pushToTalk || e.code !== 'Space' || this._pttDown) return;
                if (['INPUT','TEXTAREA'].includes(document.activeElement?.tagName)) return;
                this._pttDown = true;
                this._applyLocalMicState();
                e.preventDefault();
            });
            window.addEventListener('keyup', (e) => {
                if (!this.voicePrefs.pushToTalk || e.code !== 'Space') return;
                this._pttDown = false;
                this._applyLocalMicState();
                e.preventDefault();
            });
        },

        async _safeJson(response, label) {
            const ct = response.headers.get('content-type') || '';
            if (!ct.includes('application/json')) {
                const text = await response.text().catch(() => '');
                throw new Error(`${label} JSON dönmedi (${response.status}). ${text.slice(0, 80)}`);
            }
            return response.json();
        }
    }
}
</script>
@endpush
