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
