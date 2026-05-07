<div x-data="createRoomModal()" x-init="init()"
     @open-create-room.window="open = true; if(type==='private') fetchUsers()"
     x-show="open" x-cloak
     class="fixed inset-0 z-[60] flex items-center justify-center p-4"
     style="background:rgba(0,0,0,0.6);backdrop-filter:blur(8px)"
     @click.self="open = false">

    <div class="w-full max-w-md rounded-2xl border overflow-hidden shadow-2xl"
         style="background:#09090b;border-color:rgba(255,255,255,0.08)"
         @click.stop>

        <div class="flex items-center justify-between p-6 border-b" style="border-color:rgba(255,255,255,0.06)">
            <h2 class="text-xl font-bold tracking-tight text-white">Yeni Oda Oluştur</h2>
            <button @click="open = false" class="text-zinc-500 hover:text-white transition-colors">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>

        <div class="p-6 space-y-5">
            <!-- Room Name -->
            <div class="space-y-1.5">
                <label class="text-xs uppercase tracking-wider" style="color:rgba(161,161,170,1)">Oda İsmi</label>
                <input x-model="name" type="text" placeholder="# Genel Sohbet"
                    class="w-full rounded-lg px-3 py-2.5 text-sm text-white placeholder-zinc-600 border outline-none transition-colors"
                    style="background:rgba(0,0,0,0.3);border-color:rgba(255,255,255,0.08)"
                    onfocus="this.style.borderColor='rgba(244,63,94,0.4)'" onblur="this.style.borderColor='rgba(255,255,255,0.08)'">
            </div>

            <!-- Room Type -->
            <div class="space-y-3">
                <label class="text-xs uppercase tracking-wider" style="color:rgba(161,161,170,1)">Oda Türü</label>
                <div class="grid grid-cols-2 gap-3">
                    <label class="flex flex-col items-center justify-center rounded-xl border-2 p-4 cursor-pointer transition-all"
                           :style="type === 'global' ? 'border-color:rgba(244,63,94,1);color:rgba(244,63,94,1);background:rgba(244,63,94,0.05)' : 'border-color:rgba(255,255,255,0.06);color:rgba(161,161,170,1);background:rgba(255,255,255,0.03)'"
                           @click="type='global'">
                        <svg class="mb-2 h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 21a9.004 9.004 0 008.716-6.747M12 21a9.004 9.004 0 01-8.716-6.747M12 21c2.485 0 4.5-4.03 4.5-9S14.485 3 12 3m0 18c-2.485 0-4.5-4.03-4.5-9S9.515 3 12 3m0 0a8.997 8.997 0 017.843 4.582M12 3a8.997 8.997 0 00-7.843 4.582m15.686 0A11.953 11.953 0 0112 10.5c-2.998 0-5.74-1.1-7.843-2.918m15.686 0A8.959 8.959 0 0121 12c0 .778-.099 1.533-.284 2.253"/>
                        </svg>
                        <span class="text-sm font-medium">Global</span>
                    </label>
                    <label class="flex flex-col items-center justify-center rounded-xl border-2 p-4 cursor-pointer transition-all"
                           :style="type === 'private' ? 'border-color:rgba(99,102,241,1);color:rgba(99,102,241,1);background:rgba(99,102,241,0.05)' : 'border-color:rgba(255,255,255,0.06);color:rgba(161,161,170,1);background:rgba(255,255,255,0.03)'"
                           @click="type='private'; fetchUsers()">
                        <svg class="mb-2 h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z"/>
                        </svg>
                        <span class="text-sm font-medium">Gizli</span>
                    </label>
                </div>
            </div>

            <!-- User Selection for Private -->
            <div x-show="type === 'private'" x-transition class="space-y-2">
                <label class="text-xs uppercase tracking-wider flex justify-between" style="color:rgba(161,161,170,1)">
                    <span>Katılımcılar</span>
                    <span style="color:rgba(82,82,91,1)" x-text="selectedUsers.length + ' seçildi'"></span>
                </label>
                <input x-model.debounce.300ms="userSearch" @input="fetchUsers(true)" type="search" placeholder="Kullanıcı ara"
                    class="w-full rounded-lg px-3 py-2 text-sm text-white placeholder-zinc-600 border outline-none transition-colors"
                    style="background:rgba(0,0,0,0.25);border-color:rgba(255,255,255,0.08)">
                <div class="h-48 rounded-xl border overflow-y-auto scrollable"
                     style="background:rgba(0,0,0,0.2);border-color:rgba(255,255,255,0.08)">
                    <div x-show="fetchingUsers" class="flex items-center justify-center h-full">
                        <svg class="h-5 w-5 animate-spin" style="color:rgba(113,113,122,1)" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                        </svg>
                    </div>
                    <div x-show="!fetchingUsers" class="p-2 space-y-1">
                        <template x-for="u in users" :key="u.id">
                            <div @click="toggleUser(u.id)"
                                class="flex items-center gap-3 p-2 rounded-lg cursor-pointer transition-colors"
                                :style="selectedUsers.includes(u.id) ? 'background:rgba(99,102,241,0.15)' : ''"
                                onmouseover="if(!this.getAttribute('data-selected')) this.style.background='rgba(255,255,255,0.04)'"
                                onmouseout="if(!this.getAttribute('data-selected')) this.style.background=''"
                                :data-selected="selectedUsers.includes(u.id)">
                                <div class="h-4 w-4 rounded border flex items-center justify-center shrink-0 transition-all"
                                     :style="selectedUsers.includes(u.id) ? 'background:rgba(99,102,241,1);border-color:rgba(99,102,241,1)' : 'border-color:rgba(255,255,255,0.2)'">
                                    <svg x-show="selectedUsers.includes(u.id)" class="h-3 w-3 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5"/>
                                    </svg>
                                </div>
                                <div class="flex items-center gap-2">
                                    <div class="h-6 w-6 rounded-full flex items-center justify-center text-xs font-bold"
                                         style="background:rgba(39,39,42,1);color:rgba(113,113,122,1)"
                                         x-text="u.username?.[0]?.toUpperCase()"></div>
                                    <span class="text-sm" style="color:rgba(212,212,216,1)" x-text="u.username"></span>
                                </div>
                            </div>
                        </template>
                        <button x-show="usersHasMore" @click="fetchUsers(false)" type="button"
                            class="w-full rounded-lg py-2 text-xs font-medium text-zinc-300 hover:text-white"
                            style="background:rgba(255,255,255,0.04)">
                            Daha fazla
                        </button>
                    </div>
                </div>
            </div>

            <div x-show="error" class="text-xs text-center p-2 rounded text-rose-400" style="background:rgba(255,255,255,0.04)" x-text="error"></div>

            <button @click="submit()" :disabled="loading || !name.trim()"
                class="w-full py-2.5 rounded-xl bg-white text-black font-bold text-sm hover:bg-zinc-200 transition-all disabled:opacity-50">
                <span x-show="!loading">Oluştur</span>
                <span x-show="loading">Oluşturuluyor...</span>
            </button>
        </div>
    </div>
</div>

<script>
function createRoomModal() {
    return {
        open: false,
        name: '',
        type: 'global',
        users: [],
        userSearch: '',
        usersPage: 1,
        usersHasMore: false,
        selectedUsers: [],
        loading: false,
        fetchingUsers: false,
        error: '',

        init() {},

        async fetchUsers(reset = false) {
            if (reset) {
                this.usersPage = 1;
                this.users = [];
            }
            if (this.fetchingUsers) return;
            this.fetchingUsers = true;
            try {
                const params = new URLSearchParams({
                    page: String(this.usersPage),
                    per_page: '20',
                });
                if (this.userSearch.trim()) params.set('q', this.userSearch.trim());
                const r = await fetch(`/api/users?${params.toString()}`, { headers: { 'Accept': 'application/json' } });
                const data = await r.json();
                const nextUsers = data.users || [];
                this.users = reset ? nextUsers : [...this.users, ...nextUsers];
                this.usersHasMore = !!data.pagination?.has_more;
                if (this.usersHasMore) this.usersPage = (data.pagination?.current_page || this.usersPage) + 1;
            } catch(e) {}
            this.fetchingUsers = false;
        },

        toggleUser(uid) {
            if (this.selectedUsers.includes(uid)) {
                this.selectedUsers = this.selectedUsers.filter(id => id !== uid);
            } else {
                this.selectedUsers.push(uid);
            }
        },

        async submit() {
            if (!this.name.trim()) return;
            this.loading = true;
            this.error = '';
            try {
                const r = await fetch(`/api/rooms`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF },
                    body: JSON.stringify({ name: this.name, type: this.type, members: this.selectedUsers })
                });
                const data = await r.json();
                if (!r.ok) { this.error = data.message || 'Hata oluştu'; return; }
                showToast('Oda başarıyla oluşturuldu');
                this.open = false;
                this.name = '';
                this.type = 'global';
                this.selectedUsers = [];
                setTimeout(() => location.reload(), 800);
            } catch(e) { this.error = 'Bir hata oluştu.'; }
            finally { this.loading = false; }
        }
    }
}
</script>
