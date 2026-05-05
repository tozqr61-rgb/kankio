<div x-data="profileModal()" x-init="init()"
     @open-profile.window="open = true"
     x-show="open" x-cloak
     class="fixed inset-0 z-[60] flex items-center justify-center p-4"
     style="background:rgba(0,0,0,0.6);backdrop-filter:blur(8px)"
     @click.self="open = false">

    <div class="w-full max-w-md rounded-2xl border p-0 overflow-hidden shadow-2xl"
         style="background:#09090b;border-color:rgba(255,255,255,0.08)"
         @click.stop>

        <!-- Header -->
        <div class="flex items-center justify-between p-6 border-b" style="border-color:rgba(255,255,255,0.06)">
            <h2 class="text-xl font-bold tracking-tight text-white">Profil Düzenle</h2>
            <button @click="open = false" class="text-zinc-500 hover:text-white transition-colors">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>

        <div class="flex flex-col items-center p-6 space-y-5">

            <!-- Avatar -->
            <div class="relative group cursor-pointer" @click="$refs.fileInput.click()">
                <div class="h-36 w-36 rounded-full border-2 overflow-hidden shadow-2xl"
                     style="background:rgba(39,39,42,1);border-color:rgba(255,255,255,0.08)">
                    <template x-if="avatarPreview || currentUser.avatar_url">
                        <img :src="avatarPreview || currentUser.avatar_url" alt="Avatar" class="h-full w-full object-cover">
                    </template>
                    <template x-if="!avatarPreview && !currentUser.avatar_url">
                        <div class="h-full w-full flex items-center justify-center text-2xl font-bold text-zinc-500">
                            {{ strtoupper(substr(auth()->user()->username, 0, 1)) }}
                        </div>
                    </template>
                </div>
                <div class="absolute inset-0 rounded-full opacity-0 group-hover:opacity-100 transition-all flex items-center justify-center backdrop-blur-sm"
                     style="background:rgba(0,0,0,0.5)">
                    <svg class="h-8 w-8" style="color:rgba(255,255,255,0.8)" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6.827 6.175A2.31 2.31 0 015.186 7.23c-.38.054-.757.112-1.134.175C2.999 7.58 2.25 8.507 2.25 9.574V18a2.25 2.25 0 002.25 2.25h15A2.25 2.25 0 0021.75 18V9.574c0-1.067-.75-1.994-1.802-2.169a47.865 47.865 0 00-1.134-.175 2.31 2.31 0 01-1.64-1.055l-.822-1.316a2.192 2.192 0 00-1.736-1.039 48.774 48.774 0 00-5.232 0 2.192 2.192 0 00-1.736 1.039l-.821 1.316z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 12.75a4.5 4.5 0 11-9 0 4.5 4.5 0 019 0zM18.75 10.5h.008v.008h-.008V10.5z"/>
                    </svg>
                </div>
                <div x-show="uploading" class="absolute inset-0 rounded-full flex items-center justify-center z-10" style="background:rgba(0,0,0,0.7)">
                    <svg class="h-8 w-8 animate-spin" style="color:rgba(244,63,94,1)" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                    </svg>
                </div>
            </div>

            <div class="text-center space-y-1">
                <h3 class="font-medium text-lg text-white">{{ auth()->user()->username }}</h3>
                <p class="text-xs" style="color:rgba(255,255,255,0.35)">{{ auth()->user()->email }}</p>
            </div>

            <!-- App Download (Eski uyarı yerine) -->
            @php $latestApp = \App\Models\AppRelease::latest()->first(); @endphp
            @if($latestApp)
            <div class="w-full rounded-xl p-4 flex flex-col gap-2" style="background:rgba(16,185,129,0.08);border:1px solid rgba(16,185,129,0.18)">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <svg class="h-5 w-5 text-emerald-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z" />
                        </svg>
                        <span class="font-bold text-emerald-400 text-sm">Kankio Android Uygulaması</span>
                    </div>
                    <span class="text-xs px-2 py-0.5 rounded-full font-bold" style="background:rgba(16,185,129,0.2);color:rgba(16,185,129,1)">v{{ $latestApp->version }}</span>
                </div>
                @if($latestApp->notes)
                <p class="text-xs opacity-70 mb-1" style="color:rgba(167,243,208,1)">{{ $latestApp->notes }}</p>
                @endif
                <a href="{{ $latestApp->direct_download_link }}" download class="w-full mt-1 flex items-center justify-center gap-2 py-2 rounded-lg text-sm font-bold transition-all"
                   style="background:rgba(16,185,129,1);color:#fff"
                   onmouseover="this.style.background='rgba(5,150,105,1)'" onmouseout="this.style.background='rgba(16,185,129,1)'">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                    </svg>
                    Hemen İndir (APK)
                </a>
            </div>
            @endif

            <input type="file" x-ref="fileInput" class="hidden" accept="image/*" @change="handleAvatarChange">

            <!-- Notification Toggle -->
            <button @click="toggleNotifications()"
                class="w-full flex items-center justify-between px-4 py-3 rounded-xl border transition-all"
                style="border-color:rgba(255,255,255,0.08)"
                onmouseover="this.style.background='rgba(255,255,255,0.04)'" onmouseout="this.style.background=''">
                <span class="flex items-center gap-2 text-sm text-zinc-300">
                    <template x-if="notificationsEnabled">
                        <svg class="h-4 w-4" style="color:rgba(52,211,153,1)" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 005.454-1.31A8.967 8.967 0 0118 9.75v-.7V9A6 6 0 006 9v.75a8.967 8.967 0 01-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 01-5.714 0m5.714 0a3 3 0 11-5.714 0"/>
                        </svg>
                    </template>
                    <template x-if="!notificationsEnabled">
                        <svg class="h-4 w-4" style="color:rgba(113,113,122,1)" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9.143 17.082a24.248 24.248 0 003.844.148m-3.844-.148a23.856 23.856 0 01-5.455-1.31 8.964 8.964 0 002.3-5.542m3.155 6.852a3 3 0 005.667 1.97m1.965-2.277L21 21m-4.225-4.225a8.964 8.964 0 00.75-3.362V9a6 6 0 00-9.33-4.993M3 3l18 18"/>
                        </svg>
                    </template>
                    Bildirim Sesi
                </span>
                <span class="text-xs font-bold" :style="notificationsEnabled ? 'color:rgba(52,211,153,1)' : 'color:rgba(113,113,122,1)'"
                      x-text="notificationsEnabled ? 'AÇIK' : 'KAPALI'"></span>
            </button>

            <!-- Presence Mode Toggle -->
            <button @click="togglePresenceMode()"
                class="w-full flex items-center justify-between px-4 py-3 rounded-xl border transition-all"
                style="border-color:rgba(255,255,255,0.08)"
                onmouseover="this.style.background='rgba(255,255,255,0.04)'" onmouseout="this.style.background=''">
                <span class="flex items-center gap-2 text-sm text-zinc-300">
                    <template x-if="presenceMode === 'online'">
                        <svg class="h-4 w-4" style="color:rgba(52,211,153,1)" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6l4 2m6-2a10 10 0 11-20 0 10 10 0 0120 0z"/>
                        </svg>
                    </template>
                    <template x-if="presenceMode === 'invisible'">
                        <svg class="h-4 w-4" style="color:rgba(113,113,122,1)" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 3l18 18M10.477 10.477A2.25 2.25 0 0013.5 13.5m2.121 2.121A9.77 9.77 0 0112 16.5c-4.478 0-8.268-2.943-9.543-7a9.973 9.973 0 012.206-3.592m3.096-1.722A9.764 9.764 0 0112 3.5c4.478 0 8.268 2.943 9.543 7a9.97 9.97 0 01-1.357 2.572"/>
                        </svg>
                    </template>
                    Çevrimdışı Görün
                </span>
                <span class="text-xs font-bold" :style="presenceMode === 'invisible' ? 'color:rgba(113,113,122,1)' : 'color:rgba(52,211,153,1)'"
                      x-text="presenceMode === 'invisible' ? 'AÇIK' : 'KAPALI'"></span>
            </button>

            <p class="w-full -mt-3 text-xs leading-relaxed text-zinc-500">
                Açıkken aktif listesinde görünmezsin; yazıyor ve görüldü bilgilerin gönderilmez.
            </p>

            <!-- Upload Button -->
            <button @click="$refs.fileInput.click()" :disabled="uploading"
                class="w-full py-2.5 rounded-xl border text-sm text-zinc-300 transition-all disabled:opacity-60"
                style="border-color:rgba(255,255,255,0.08)"
                onmouseover="this.style.background='rgba(255,255,255,0.04)'" onmouseout="this.style.background=''">
                Fotoğraf Yükle
            </button>

            <!-- Logout -->
            <form action="{{ route('logout') }}" method="POST" class="w-full">
                @csrf
                <button type="submit"
                    class="w-full py-2.5 rounded-xl text-sm font-medium transition-all"
                    style="color:rgba(239,68,68,1)"
                    onmouseover="this.style.background='rgba(239,68,68,0.08)';this.style.color='rgba(252,165,165,1)'"
                    onmouseout="this.style.background='';this.style.color='rgba(239,68,68,1)'">
                    Çıkış Yap
                </button>
            </form>
        </div>
    </div>
</div>

<script>
function profileModal() {
    return {
        open: false,
        uploading: false,
	        avatarPreview: null,
	        notificationsEnabled: {{ auth()->user()->notifications_enabled ? 'true' : 'false' }},
	        presenceMode: @json(auth()->user()->presence_mode ?? 'online'),
	        currentUser: @json(auth()->user()),

        init() {},

        async handleAvatarChange(e) {
            const file = e.target.files[0];
            if (!file) return;
            this.uploading = true;
            const formData = new FormData();
            formData.append('avatar', file);
            formData.append('_token', CSRF);
            try {
                const r = await fetch(`/api/profile/avatar`, {
                    method: 'POST',
                    headers: { 'Accept': 'application/json' },
                    body: formData,
                });
                const data = await r.json();
                if (!r.ok) { showToast(data.message || data.error || 'Hata oluştu', 'error'); return; }
                this.avatarPreview = data.avatar_url;
                showToast('Profil fotoğrafı güncellendi');
                setTimeout(() => location.reload(), 1500);
            } catch(e) { showToast('Yükleme başarısız', 'error'); }
            finally { this.uploading = false; }
        },

	        async toggleNotifications() {
            const r = await fetch(`/api/profile/notifications`, {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': CSRF }
            });
            const data = await r.json();
	            this.notificationsEnabled = data.notifications_enabled;
	            showToast(this.notificationsEnabled ? 'Bildirim sesi açıldı' : 'Bildirim sesi kapatıldı');
	        },

	        async togglePresenceMode() {
	            const nextMode = this.presenceMode === 'invisible' ? 'online' : 'invisible';
	            const r = await fetch(`/api/profile/presence-mode`, {
	                method: 'POST',
	                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
	                body: JSON.stringify({ presence_mode: nextMode }),
	            });
	            const data = await r.json();
	            if (!r.ok) {
	                showToast(data.message || 'Durum güncellenemedi', 'error');
	                return;
	            }
	            this.presenceMode = data.presence_mode;
	            this.currentUser.presence_mode = data.presence_mode;
	            if (typeof Alpine !== 'undefined' && Alpine.store('chat')) {
	                Alpine.store('chat').currentUser.presence_mode = data.presence_mode;
	            }
	            window.dispatchEvent(new CustomEvent('presence-mode-changed', { detail: { presence_mode: data.presence_mode } }));
	            showToast(data.presence_mode === 'invisible' ? 'Çevrimdışı görünüyorsun' : 'Çevrimiçi görünüyorsun');
	        },
	    }
	}
</script>
