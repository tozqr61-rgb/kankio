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
