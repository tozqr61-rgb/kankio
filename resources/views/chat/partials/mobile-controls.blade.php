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
        <button x-show="showMusicUnlockPrompt && musicState.video_id && musicState.is_playing"
                @click="unlockMusicPlayback()"
                class="h-9 px-3 rounded-xl flex items-center gap-1 text-xs font-semibold shrink-0"
                style="background:rgba(16,185,129,0.18);color:rgba(110,231,183,1);border:1px solid rgba(16,185,129,0.25)">
            Müziği Aç
        </button>

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

        {{-- Game button --}}
        <button @click="startIsimSehirGame()"
                class="flex flex-col items-center justify-center gap-1 flex-1 rounded-xl py-1 relative transition-colors"
                style="color:rgba(82,82,91,1)">
            <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M14.25 9V6.75A2.25 2.25 0 0012 4.5h-1.5A2.25 2.25 0 008.25 6.75V9m6 0v7.5A2.25 2.25 0 0112 18.75h-1.5A2.25 2.25 0 018.25 16.5V9m6 0h1.5A2.25 2.25 0 0118 11.25v3A2.25 2.25 0 0115.75 16.5h-1.5m-6-7.5h-1.5A2.25 2.25 0 004.5 11.25v3A2.25 2.25 0 006.75 16.5h1.5"/>
            </svg>
            <span class="text-[9px] font-medium tracking-wide leading-none">Oyun</span>
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
