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
            <button @click="startIsimSehirGame()"
                class="flex p-2 rounded-lg transition-colors items-center justify-center"
                style="color:rgba(255,255,255,0.6)"
                onmouseover="this.style.color='#fff';this.style.background='rgba(255,255,255,0.05)'"
                onmouseout="this.style.color='rgba(255,255,255,0.6)';this.style.background=''"
                title="İsim-Şehir">
                <span class="text-sm font-semibold">İŞ</span>
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
