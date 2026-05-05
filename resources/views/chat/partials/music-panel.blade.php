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
        <button x-show="showMusicUnlockPrompt && voiceState.in_voice"
                @click="unlockMusicPlayback()"
                class="w-full py-2 rounded-lg text-xs font-semibold transition-all"
                style="background:rgba(16,185,129,0.2);color:rgba(52,211,153,1)">
            Müziği Etkinleştir
        </button>
        <p class="text-[9px] text-zinc-600 text-center">/play /durdur /geç /sıra /devam</p>
    </div>

    </div>{{-- end scrollable --}}
</div>{{-- end right sidebar --}}
