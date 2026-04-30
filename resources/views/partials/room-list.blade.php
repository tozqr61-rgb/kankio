<div class="flex flex-col h-full w-full pt-6 pb-6" x-data="roomListComp()" x-init="init()">

    <!-- Header -->
    <div class="px-6 pb-6 flex items-center justify-between">
        <div>
            <h2 class="font-serif text-3xl font-thin tracking-tighter" style="color:rgba(255,255,255,0.9)">Kankio</h2>
            <a href="{{ route('stay.connected') }}" target="_blank"
               class="text-[10px] tracking-[0.2em] uppercase mt-1 block transition-all duration-300"
               style="color:rgba(255,255,255,0.35)"
               onmouseover="this.style.color='rgba(255,255,255,0.7)';this.style.letterSpacing='0.25em'"
               onmouseout="this.style.color='rgba(255,255,255,0.35)';this.style.letterSpacing='0.2em'">Bağlantıda Kal</a>
        </div>
        <div class="flex items-center gap-1">
            @if(auth()->user()->isAdmin())
            <a href="{{ route('admin.dashboard') }}"
               class="p-2 rounded-full transition-colors hover:bg-rose-500/10"
               style="color:rgba(244,63,94,1)" title="Yönetim Paneli">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.955 11.955 0 01.25 12c0 6.627 5.373 12 12 12 6.627 0 12-5.373 12-12 0-2.285-.638-4.423-1.748-6.253M9 12.75L11.25 15 15 9.75"/>
                </svg>
            </a>
            @endif
            <!-- Create Room Button -->
            <button @click="$dispatch('open-create-room')"
                class="h-8 w-8 rounded-full flex items-center justify-center transition-all duration-300"
                style="background:rgba(255,255,255,0.05)"
                onmouseover="this.style.background='rgba(255,255,255,0.1)'"
                onmouseout="this.style.background='rgba(255,255,255,0.05)'"
                title="Oda Oluştur">
                <svg class="h-4 w-4" style="color:rgba(255,255,255,0.7)" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/>
                </svg>
            </button>
        </div>
    </div>

    <!-- Scrollable Area -->
    <div class="flex-1 px-4 scrollable overflow-y-auto space-y-8 pb-4">

        <!-- Global Rooms -->
        <div class="space-y-2">
            <div class="px-2 text-[10px] font-medium tracking-[0.2em] uppercase" style="color:rgba(255,255,255,0.18)">Odalar</div>
            <div class="space-y-1">
                @foreach($rooms as $room)
                <div class="block group relative">
                    <a href="{{ route('chat.room', $room->id) }}"
                       @click.prevent="isMobile && (leftOpen = false); window._changeRoom && window._changeRoom('{{ $room->id }}', '{{ addslashes($room->name) }}')"
                       class="relative w-full flex items-center px-4 py-3 text-sm font-light rounded-xl transition-all duration-300 overflow-hidden group/link"
                       :class="$store.chat.activeRoomId == '{{ $room->id }}' ? 'text-white' : 'text-zinc-500 hover:text-zinc-200'"
                       :style="$store.chat.activeRoomId == '{{ $room->id }}' ? 'background:rgba(255,255,255,0.08);box-shadow:0 4px 20px -10px rgba(255,255,255,0.15)' : ''"
                       @mouseover="$store.chat.activeRoomId != '{{ $room->id }}' && ($el.style.background='rgba(255,255,255,0.04)')"
                       @mouseout="$store.chat.activeRoomId != '{{ $room->id }}' && ($el.style.background='')">
                        <!-- Active indicator -->
                        <div class="absolute left-0 top-0 bottom-0 w-[2px] rounded-r transition-all duration-300"
                             :style="$store.chat.activeRoomId == '{{ $room->id }}' ? 'background:rgba(244,63,94,1)' : 'background:transparent'"></div>
                        <span class="relative z-10 flex items-center gap-3 flex-1 min-w-0">
                            @if($room->type === 'private')
                            <svg class="h-3 w-3 shrink-0" style="color:rgba(129,140,248,0.7)" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z"/>
                            </svg>
                            @elseif($room->type === 'announcements')
                            <svg class="h-3 w-3 shrink-0" style="color:rgba(99,102,241,0.8)" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M11 5.882V19.24a1.76 1.76 0 01-3.417.592l-2.147-6.15M18 13a3 3 0 100-6M5.436 13.683A4.001 4.001 0 017 6h1.832c4.1 0 7.625-1.234 9.168-3v14c-1.543-1.766-5.067-3-9.168-3H7a3.988 3.988 0 01-1.564-.317z"/>
                            </svg>
                            @else
                            <svg class="h-3 w-3 opacity-40 group-hover:opacity-70 transition-opacity shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M5.25 8.25h15m-16.5 7.5h15m-1.8-13.5l-3.9 19.5m-2.1-19.5l-3.9 19.5"/>
                            </svg>
                            @endif
                            <span class="truncate">{{ $room->name }}</span>
                        </span>
                        <!-- Unread badge -->
                        <span x-show="unread({{ $room->id }}) > 0"
                              x-text="unread({{ $room->id }}) > 99 ? '99+' : unread({{ $room->id }})"
                              class="ml-auto shrink-0 min-w-[1.25rem] h-5 px-1.5 rounded-full text-[10px] font-bold flex items-center justify-center"
                              style="background:rgba(244,63,94,0.9);color:#fff"></span>
                    </a>
                    @if($room->created_by === auth()->id() && !in_array($room->name, ['Genel Sohbet', '# Genel Sohbet']))
                    <button @click="$dispatch('delete-room', { id: '{{ $room->id }}' })"
                        class="absolute right-2 top-1/2 -translate-y-1/2 p-1.5 rounded-full opacity-0 group-hover:opacity-100 transition-all z-20"
                        style="color:rgba(82,82,91,1)"
                        onmouseover="this.style.color='rgba(248,113,113,1)';this.style.background='rgba(239,68,68,0.1)'"
                        onmouseout="this.style.color='rgba(82,82,91,1)';this.style.background=''"
                        title="Odayı Sil">
                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0"/>
                        </svg>
                    </button>
                    @endif
                </div>
                @endforeach
            </div>
        </div>

        <!-- DMs -->
        @if(count($dms) > 0)
        <div class="space-y-2">
            <div class="px-2 text-[10px] font-medium tracking-[0.2em] uppercase" style="color:rgba(255,255,255,0.18)">Mesajlar</div>
            <div class="space-y-1">
                @foreach($dms as $dm)
                <a href="{{ route('chat.room', $dm->id) }}"
                   @click.prevent="window._changeRoom && window._changeRoom('{{ $dm->id }}')"
                   class="relative w-full flex items-center px-4 py-3 text-sm font-light rounded-xl transition-all duration-300
                   {{ request()->route('roomId') == $dm->id ? 'text-white' : 'text-zinc-500 hover:text-zinc-200' }}"
                   style="{{ request()->route('roomId') == $dm->id ? 'background:rgba(255,255,255,0.08)' : '' }}">
                    <span class="flex items-center gap-3">
                        <div class="h-5 w-5 rounded-full flex items-center justify-center"
                             style="background:linear-gradient(to top right,rgba(244,63,94,0.2),rgba(99,102,241,0.2))">
                            <svg class="h-3 w-3 opacity-60" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z"/>
                            </svg>
                        </div>
                        <span class="truncate">DM ({{ substr($dm->id, 0, 4) }})</span>
                    </span>
                </a>
                @endforeach
            </div>
        </div>
        @endif

        <!-- Online Users -->
        <div class="space-y-2">
            <button @click="isActiveUsersOpen = !isActiveUsersOpen"
                class="w-full px-2 flex items-center justify-between group">
                <div class="text-[10px] font-medium tracking-[0.2em] uppercase flex items-center gap-2 transition-colors group-hover:text-emerald-400"
                     style="color:rgba(255,255,255,0.18)">
                    <div class="h-1.5 w-1.5 rounded-full bg-emerald-500" style="animation:pulse 2s cubic-bezier(0.4,0,0.6,1) infinite"></div>
                    Aktif (<span x-text="onlineUsers.length"></span>)
                </div>
                <svg :class="!isActiveUsersOpen ? '-rotate-90' : ''"
                     class="h-3 w-3 transition-transform duration-300" style="color:rgba(82,82,91,1)"
                     fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5"/>
                </svg>
            </button>

            <div x-show="isActiveUsersOpen" x-transition class="space-y-1">
                <template x-for="u in onlineUsers" :key="u.id">
                    <div class="relative w-full flex items-center px-4 py-2 text-sm font-light rounded-xl transition-all cursor-default"
                         onmouseover="this.style.background='rgba(255,255,255,0.03)'" onmouseout="this.style.background=''">
                        <div class="h-8 w-8 rounded-full border flex items-center justify-center shrink-0 mr-3 overflow-hidden relative"
                             style="background:rgba(39,39,42,1);border-color:rgba(255,255,255,0.08)">
                            <template x-if="u.avatar_url">
                                <img :src="u.avatar_url" :alt="u.username" class="h-full w-full object-cover">
                            </template>
                            <template x-if="!u.avatar_url">
                                <span class="text-[10px] font-bold" style="color:rgba(113,113,122,1)" x-text="u.username?.[0]?.toUpperCase()"></span>
                            </template>
                            <!-- Status dot -->
                            <div class="absolute bottom-0 right-0 h-2.5 w-2.5 rounded-full border-2 z-20"
                                 style="border-color:#09090b"
                                 :style="u.status === 'online' ? 'background:#10b981' : u.status === 'busy' ? 'background:#f59e0b' : 'background:#52525b'"></div>
                        </div>
                        <div class="flex flex-col">
                            <span class="text-xs truncate flex items-center gap-1.5"
                                  :style="u.role === 'admin' ? 'color:rgba(251,113,133,1);font-weight:700' : 'color:rgba(161,161,170,1)'" x-text="u.username">
                            </span>
                            <span class="text-[9px]" style="color:rgba(82,82,91,1)"
                                  x-text="u.status === 'online' ? 'Çevrimiçi' : u.status === 'busy' ? 'Meşgul' : 'Uzakta'"></span>
                        </div>
                        <template x-if="u.id == currentUser.id">
                            <span class="ml-auto text-[9px]" style="color:rgba(82,82,91,1)">(Sen)</span>
                        </template>
                    </div>
                </template>
                <template x-if="onlineUsers.length === 0">
                    <div class="px-4 py-2 text-xs" style="color:rgba(63,63,70,1)">Çevrimiçi kullanıcı yok</div>
                </template>
            </div>
        </div>
    </div>

    <!-- PWA Install (sidebar) -->
    <div x-data="{}" x-show="$store.pwa && $store.pwa.canInstall" x-cloak
         class="px-4 pb-2">
        <button @click="$store.pwa.install()"
                class="w-full flex items-center gap-3 px-4 py-2.5 rounded-xl text-xs transition-all"
                style="color:rgba(52,211,153,0.8);background:rgba(52,211,153,0.05);border:1px solid rgba(52,211,153,0.15)"
                onmouseover="this.style.background='rgba(52,211,153,0.1)'" onmouseout="this.style.background='rgba(52,211,153,0.05)'">
            <svg class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3"/>
            </svg>
            Kankio'yu Uygulama Olarak Yükle
        </button>
    </div>

    <!-- User Profile Footer -->
    <div class="px-6 pt-4 mt-auto border-t shrink-0" style="border-color:rgba(255,255,255,0.04)">
        <button @click="$dispatch('open-profile')"
            class="group w-full flex items-center gap-4 p-3 rounded-2xl border transition-all duration-300 text-left"
            style="background:rgba(255,255,255,0.04);border-color:rgba(255,255,255,0.05)"
            onmouseover="this.style.background='rgba(255,255,255,0.08)'" onmouseout="this.style.background='rgba(255,255,255,0.04)'">
            <div class="relative h-10 w-10 rounded-full p-[1px] shrink-0"
                 style="background:linear-gradient(to top right,rgba(244,63,94,1),rgba(99,102,241,1))">
                <div class="h-full w-full rounded-full flex items-center justify-center overflow-hidden bg-black">
                    @if(auth()->user()->avatar_url)
                    <img src="{{ auth()->user()->avatar_url }}" alt="Profile" class="h-full w-full object-cover">
                    @else
                    <span class="text-sm font-medium text-white">{{ strtoupper(substr(auth()->user()->username, 0, 1)) }}</span>
                    @endif
                </div>
                <div class="absolute bottom-0 right-0 h-2.5 w-2.5 rounded-full border-2 z-10" style="border-color:#000;background:#10b981"></div>
            </div>
            <div class="flex-1 overflow-hidden">
                <p class="text-sm font-normal truncate transition-colors group-hover:text-white" style="color:rgba(255,255,255,0.85)">
                    {{ auth()->user()->username }}
                </p>
                <p class="text-[10px] uppercase transition-colors group-hover:text-rose-400" style="color:rgba(255,255,255,0.35)">
                    {{ auth()->user()->role === 'admin' ? 'Yönetici' : 'Üye' }}
                </p>
            </div>
        </button>
    </div>
</div>

@once
<script>
function roomListComp() {
    return {
        isActiveUsersOpen: true,
        _unreadTimer: null,
        _echo: null,
        _roomIds: @json($rooms->pluck('id')->merge($dms->pluck('id'))->unique()->values()),

        get onlineUsers() {
            return (typeof Alpine !== 'undefined' && Alpine.store('chat')) ? Alpine.store('chat').onlineUsers : [];
        },
        get currentUser() {
            return (typeof Alpine !== 'undefined' && Alpine.store('chat')) ? Alpine.store('chat').currentUser : {};
        },
        unread(roomId) {
            if (typeof Alpine === 'undefined' || !Alpine.store('chat')) return 0;
            return Alpine.store('chat').unreadCounts[roomId] || 0;
        },

        async _pollUnread() {
            try {
                const r = await fetch(`/api/unread`);
                if (r.ok) {
                    const counts = await r.json();
                    if (typeof Alpine !== 'undefined' && Alpine.store('chat')) {
                        Alpine.store('chat').unreadCounts = counts;
                    }
                }
            } catch(e) {}
        },

        _initRealtimeUnread() {
            try {
                if (!window.LaravelEcho || typeof BROADCAST_DRIVER === 'undefined' || BROADCAST_DRIVER === 'null') return false;

                if (window.KANKIO_ECHO) {
                    this._echo = window.KANKIO_ECHO;
                } else {
                    const cfg = BROADCAST_DRIVER === 'pusher'
                        ? {
                            broadcaster: 'pusher',
                            key: BROADCAST_CONFIG.pusher.key,
                            cluster: BROADCAST_CONFIG.pusher.cluster,
                            forceTLS: BROADCAST_CONFIG.pusher.scheme === 'https',
                            disableStats: true,
                        }
                        : {
                            broadcaster: 'reverb',
                            key: BROADCAST_CONFIG.reverb.key,
                            wsHost: BROADCAST_CONFIG.reverb.host,
                            wsPort: BROADCAST_CONFIG.reverb.port,
                            wssPort: BROADCAST_CONFIG.reverb.port,
                            forceTLS: BROADCAST_CONFIG.reverb.scheme === 'https',
                            disableStats: true,
                            enabledTransports: ['ws'],
                        };

                    this._echo = new window.LaravelEcho({
                        ...cfg,
                        auth: { headers: { 'X-CSRF-TOKEN': CSRF } },
                        authEndpoint: `/broadcasting/auth`,
                    });
                    window.KANKIO_ECHO = this._echo;
                }

                for (const roomId of this._roomIds) {
                    this._echo.private(`room.${roomId}.chat`)
                        .listen('.message.sent', ({ message }) => {
                            if (!message || String(message.sender?.id) === String(this.currentUser.id)) return;
                            if (String(Alpine.store('chat').activeRoomId) === String(roomId)) return;
                            const counts = { ...Alpine.store('chat').unreadCounts };
                            counts[roomId] = (counts[roomId] || 0) + 1;
                            Alpine.store('chat').unreadCounts = counts;
                        })
                        .listen('.messages.read', ({ reader_id }) => {
                            if (String(reader_id) !== String(this.currentUser.id)) return;
                            const counts = { ...Alpine.store('chat').unreadCounts };
                            delete counts[roomId];
                            Alpine.store('chat').unreadCounts = counts;
                        });
                }

                return true;
            } catch(e) {
                return false;
            }
        },

        init() {
            window.addEventListener('delete-room', (e) => {
                if (window._chatLayout) window._chatLayout.deleteRoom(e.detail.id);
                else window.dispatchEvent(new CustomEvent('toggle-left-sidebar'));
            });
            this._pollUnread();
            const realtimeReady = this._initRealtimeUnread();
            this._unreadTimer = setInterval(() => this._pollUnread(), realtimeReady ? 60000 : 5000);
        }
    }
}
</script>
@endonce
