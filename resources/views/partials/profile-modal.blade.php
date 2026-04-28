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

            <!-- Warning -->
            <div class="w-full rounded-lg p-3 flex items-start gap-3" style="background:rgba(244,63,94,0.08);border:1px solid rgba(244,63,94,0.18)">
                <svg class="h-4 w-4 mt-0.5 shrink-0" style="color:rgba(251,113,133,1)" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <p class="text-xs leading-relaxed" style="color:rgba(253,164,175,0.8)">
                    <span class="font-bold" style="color:rgba(251,113,133,1)">Dikkat:</span>
                    Profil fotoğrafınızı haftada sadece <span class="underline" style="text-decoration-color:rgba(244,63,94,0.5)">1 kez</span> değiştirebilirsiniz.
                </p>
            </div>

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
        }
    }
}
</script>
