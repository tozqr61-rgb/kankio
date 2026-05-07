@extends($embedded ? 'layouts.game-embedded' : 'rooms.layout')

@section('room-content')
<div class="h-full overflow-y-auto" x-data="isimSehirGame()" x-init="init()">
    <div class="min-h-full px-4 py-5 md:px-8 md:py-8" style="background:radial-gradient(circle at top left,rgba(16,185,129,0.10),transparent 34%),#050505">
        <div class="mx-auto max-w-6xl space-y-5">
            <header class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                <div>
	                    @if($embedded)
		                        <button type="button"
		                                onclick="gamePost(window.GAME_EVENTS?.CLOSE || 'game:close')"
	                                class="text-xs text-zinc-500 hover:text-zinc-200">← Sohbete dön</button>
	                    @else
	                        <a href="{{ route('chat.room', $room->id) }}" class="text-xs text-zinc-500 hover:text-zinc-200">← Sohbete dön</a>
	                    @endif
	                    <h1 class="mt-2 text-3xl md:text-4xl font-semibold tracking-tight">İsim-Şehir</h1>
	                    <p class="text-sm text-zinc-400">#{{ $room->name }} odasında aktif oyun</p>
	                </div>
	                <div class="flex flex-wrap gap-2">
	                    <button x-show="!isFinished()" @click="join()" :disabled="actionInFlight" class="rounded-lg px-4 py-2 text-sm font-medium bg-white/10 hover:bg-white/15 disabled:opacity-40">Katıl</button>
	                    <button x-show="!isFinished()" @click="ready()" :disabled="actionInFlight" class="rounded-lg px-4 py-2 text-sm font-medium bg-emerald-500/15 text-emerald-200 hover:bg-emerald-500/25 disabled:opacity-40" x-text="myParticipant()?.is_ready ? 'Hazır Değilim' : 'Hazırım'"></button>
	                    @if($canManageGame)
	                        <button x-show="!isFinished()" @click="toggleSettings()" class="rounded-lg px-4 py-2 text-sm font-medium bg-white/10 hover:bg-white/15">Ayarlar</button>
	                    @endif
	                    <button x-show="!isFinished()" @click="leave()" class="rounded-lg px-4 py-2 text-sm font-medium bg-rose-500/15 text-rose-200 hover:bg-rose-500/25">Ayrıl</button>
		                </div>
			            </header>

		            <section x-show="refreshWarning" x-cloak class="rounded-lg border border-amber-400/20 bg-amber-400/10 p-3 text-sm text-amber-100" x-text="refreshWarning"></section>

		            <section x-show="isFinished()" x-cloak class="rounded-lg border border-emerald-400/20 bg-emerald-400/10 p-4">
	                <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
	                    <div>
	                        <p class="text-sm font-semibold text-emerald-100" x-text="state.session?.status === 'cancelled' ? 'Oyun iptal edildi' : 'Oyun bitti'"></p>
	                        <p class="text-xs text-emerald-200/80">Sonuçları inceleyebilir veya sohbete dönebilirsin.</p>
	                    </div>
	                    <button type="button" @click="gamePost(window.GAME_EVENTS?.CLOSE || 'game:close')"
	                            class="rounded-lg bg-emerald-300 px-4 py-2 text-sm font-semibold text-black">Odaya dön</button>
		                </div>
		            </section>

	            @if($canManageGame)
	                <section x-show="settingsOpen && !isFinished()" x-cloak class="rounded-lg border border-white/10 bg-zinc-950/80 p-5">
	                    <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
	                        <div>
	                            <h2 class="text-sm font-semibold text-zinc-200">Oyun Ayarları</h2>
	                            <p class="mt-1 text-xs text-zinc-500">Ayarlar yeni turlara uygulanır. Tur devam ederken değişiklik yapılamaz.</p>
	                        </div>
	                        <div class="grid gap-4 lg:w-[560px]">
	                            <label class="block">
	                                <span class="mb-1 block text-sm font-medium text-zinc-300">Tur süresi</span>
	                                <div class="flex items-center gap-2">
	                                    <input type="number" min="30" max="900" step="30" x-model.number="settingsRoundSeconds"
	                                           @input="settingsDirty = true"
	                                           :disabled="isCollecting() || settingsSaving"
	                                           class="w-32 rounded-lg border border-white/10 bg-black/50 px-3 py-2 text-sm outline-none focus:border-emerald-400/60 disabled:opacity-50">
	                                    <span class="text-xs text-zinc-500" x-text="settingsDurationLabel()"></span>
	                                </div>
	                            </label>

	                            <div>
	                                <span class="mb-2 block text-sm font-medium text-zinc-300">Kategoriler</span>
	                                <div class="flex flex-wrap gap-2">
	                                    <template x-for="category in settingsCategories" :key="category">
	                                        <button type="button" @click="removeCategory(category)"
	                                                :disabled="isCollecting() || settingsSaving || settingsCategories.length <= 2"
	                                                class="rounded-lg border border-white/10 bg-white/[0.04] px-3 py-2 text-sm text-zinc-200 disabled:opacity-40">
	                                            <span x-text="category"></span>
	                                            <span class="ml-2 text-zinc-500">×</span>
	                                        </button>
	                                    </template>
	                                </div>
	                                <div class="mt-3 flex gap-2">
	                                    <input x-model="newCategory" @keydown.enter.prevent="addCategory()"
	                                           :disabled="isCollecting() || settingsSaving || settingsCategories.length >= 10"
	                                           placeholder="Kategori ekle"
	                                           class="min-w-0 flex-1 rounded-lg border border-white/10 bg-black/50 px-3 py-2 text-sm outline-none focus:border-emerald-400/60 disabled:opacity-50">
	                                    <button type="button" @click="addCategory()"
	                                            :disabled="isCollecting() || settingsSaving || settingsCategories.length >= 10"
	                                            class="rounded-lg bg-white/10 px-4 py-2 text-sm font-medium hover:bg-white/15 disabled:opacity-40">Ekle</button>
	                                </div>
	                            </div>

	                            <div class="flex flex-wrap items-center gap-2">
	                                <button type="button" @click="saveSettings()"
	                                        :disabled="isCollecting() || settingsSaving || settingsCategories.length < 2"
	                                        class="rounded-lg bg-emerald-400 px-5 py-2.5 text-sm font-semibold text-black disabled:opacity-40">Ayarları Kaydet</button>
	                                <button type="button" @click="resetSettingsForm()"
	                                        :disabled="settingsSaving"
	                                        class="rounded-lg bg-white/10 px-4 py-2.5 text-sm font-medium hover:bg-white/15 disabled:opacity-40">Sıfırla</button>
	                                <span class="text-xs" :class="settingsStatus.includes('kaydedildi') ? 'text-emerald-300' : 'text-zinc-500'" x-text="settingsStatus"></span>
	                            </div>
	                        </div>
	                    </div>
	                </section>
	            @endif

            <section class="grid gap-4 lg:grid-cols-[1.4fr_0.8fr]">
                <div class="rounded-lg border border-white/10 bg-zinc-950/80 p-5">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <p class="text-xs uppercase tracking-widest text-zinc-500">Aktif tur</p>
                            <div class="mt-2 flex items-center gap-4">
                                <div class="flex h-20 w-20 items-center justify-center rounded-lg bg-emerald-400 text-5xl font-black text-black" x-text="state.round?.letter || '?'"></div>
                                <div>
                                    <p class="text-lg font-semibold" x-text="state.round ? `${state.round.round_no}. Tur` : 'Tur başlamadı'"></p>
                                    <p class="text-sm text-zinc-400" x-text="roundStatusText()"></p>
                                </div>
                            </div>
                        </div>
                        <div class="text-right">
                            <p class="text-xs uppercase tracking-widest text-zinc-500">Kalan süre</p>
                            <p class="mt-2 text-3xl font-semibold tabular-nums" x-text="remainingLabel"></p>
                        </div>
                    </div>

		                    <div class="mt-6 grid gap-3 md:grid-cols-2">
		                        <p class="md:col-span-2 text-xs text-zinc-500">
		                            Taslak otomatik kaydedilir. Sadece "Cevapları Kilitle" sonrası cevap kesinleşir ve oyun bitirilse bile yalnızca kilitlenen cevaplar puanlanır.
		                        </p>
		                        <template x-for="category in state.categories" :key="category">
                            <label class="block">
                                <span class="mb-1 block text-sm font-medium capitalize text-zinc-300" x-text="category"></span>
                                <input x-model="answers[category]" :disabled="isLocked() || !isCollecting()"
		                                       @input.debounce.2000ms="queueDraft()"
	                                       class="w-full rounded-lg border border-white/10 bg-black/50 px-3 py-3 text-sm outline-none focus:border-emerald-400/60 disabled:opacity-50">
	                            </label>
	                        </template>
	                    </div>
	                    <p class="mt-3 text-xs text-zinc-500" x-text="draftStatus"></p>

                    <div class="mt-5 flex flex-wrap gap-2">
	                        <button @click="submit()" :disabled="!isCollecting() || isLocked() || actionInFlight || isFinished()"
	                                class="rounded-lg px-5 py-2.5 text-sm font-semibold bg-emerald-400 text-black disabled:opacity-40">Cevapları Kilitle</button>
	                        @if($canManageGame)
		                            <button @click="beginRound()" :disabled="actionInFlight || !canBeginRound()" class="rounded-lg px-5 py-2.5 text-sm font-semibold bg-white/10 hover:bg-white/15 disabled:opacity-40">Yeni Tur Başlat</button>
	                            <button x-show="isCollecting()" @click="finalizeRound()" :disabled="actionInFlight" class="rounded-lg px-5 py-2.5 text-sm font-semibold bg-amber-500/15 text-amber-200 disabled:opacity-40">Turu Kapat</button>
	                            <button x-show="!isFinished()" @click="finish()" :disabled="actionInFlight" class="rounded-lg px-5 py-2.5 text-sm font-semibold bg-rose-500/15 text-rose-200 disabled:opacity-40">Oyunu Bitir</button>
	                        @endif
                    </div>
                </div>

	                <aside class="space-y-4">
	                    <div class="rounded-lg border border-white/10 bg-zinc-950/80 p-5">
	                        <p class="text-xs uppercase tracking-widest text-zinc-500">Önde olan</p>
	                        <div class="mt-2 rounded-lg bg-emerald-400/10 px-3 py-3">
	                            <p class="text-lg font-semibold text-emerald-100" x-text="state.leader?.username || 'Henüz yok'"></p>
	                            <p class="text-sm text-emerald-300/80" x-text="state.leader ? `${state.leader.total_score} puan` : 'İlk tur bekleniyor'"></p>
	                        </div>
	                    </div>

	                    <div class="rounded-lg border border-white/10 bg-zinc-950/80 p-5">
	                        <h2 class="text-sm font-semibold text-zinc-200">Genel Tablo</h2>
	                        <div class="mt-3 space-y-2">
	                            <template x-for="(p, index) in state.participants" :key="p.user_id">
	                                <div class="flex items-center justify-between rounded-lg bg-white/[0.03] px-3 py-2">
	                                    <div class="flex items-center gap-3">
	                                        <span class="flex h-7 w-7 items-center justify-center rounded-lg bg-white/5 text-xs font-semibold text-zinc-400" x-text="index + 1"></span>
	                                        <div>
	                                            <p class="text-sm font-medium" x-text="p.username"></p>
	                                            <p class="text-xs" :class="p.is_ready ? 'text-emerald-300' : 'text-zinc-500'" x-text="p.is_ready ? 'Hazır' : 'Bekliyor'"></p>
	                                        </div>
	                                    </div>
	                                    <p class="text-sm font-semibold tabular-nums" x-text="p.total_score"></p>
	                                </div>
	                            </template>
	                            <p x-show="!state.participants.length" class="text-sm text-zinc-500">Oyuncu bekleniyor.</p>
	                        </div>
	                    </div>
	                </aside>
	            </section>

	            <section x-show="state.round && state.round.status !== 'collecting'" class="rounded-lg border border-white/10 bg-zinc-950/80 p-5">
	                <h2 class="text-sm font-semibold text-zinc-200">Son Tur Sonuçları</h2>
	                <div class="mt-4 overflow-x-auto">
	                    <table class="w-full min-w-[680px] text-left text-sm">
	                        <thead class="text-xs uppercase tracking-widest text-zinc-500">
	                            <tr>
	                                <th class="py-2">Oyuncu</th>
	                                <template x-for="category in state.categories" :key="category">
	                                    <th class="py-2 capitalize" x-text="category"></th>
	                                </template>
	                                <th class="py-2 text-right">Puan</th>
	                            </tr>
	                        </thead>
	                        <tbody>
	                            <template x-for="submission in state.round?.submissions || []" :key="submission.user_id">
	                                <tr class="border-t border-white/5">
	                                    <td class="py-3 font-medium" x-text="submission.username"></td>
	                                    <template x-for="category in state.categories" :key="category">
	                                        <td class="py-3">
	                                            <span x-text="submission.answers?.[category] || '-'"></span>
	                                            <span class="ml-1 text-xs text-zinc-500" x-text="submission.score_breakdown?.[category] ? `(${submission.score_breakdown[category].score})` : ''"></span>
	                                        </td>
	                                    </template>
	                                    <td class="py-3 text-right font-semibold" x-text="submission.score_total"></td>
	                                </tr>
	                            </template>
	                        </tbody>
	                    </table>
	                </div>
	            </section>

	            <section class="rounded-lg border border-white/10 bg-zinc-950/80 p-5">
	                <h2 class="text-sm font-semibold text-zinc-200">Tur Geçmişi</h2>
	                <div class="mt-3 grid gap-2 md:grid-cols-2">
		                    <template x-for="round in state.history" :key="round.id">
		                        <div class="flex items-center justify-between gap-3 rounded-lg bg-white/[0.03] px-3 py-2 text-sm">
		                            <span x-text="`${round.round_no}. Tur - ${round.letter}`"></span>
		                            <span class="text-right text-zinc-500" x-text="`${round.locked_submissions_count || 0}/${round.submissions_count || 0} kilitli`"></span>
		                        </div>
		                    </template>
	                    <p x-show="!state.history.length" class="text-sm text-zinc-500">Henüz kapanan tur yok.</p>
	                </div>
	            </section>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
window.GAME_EVENTS = window.GAME_EVENTS || {
    CLOSE: 'game:close',
    LOADED: 'game:loaded',
    FINISHED: 'game:session-finished',
    TOAST: 'game:toast',
    ERROR: 'game:error',
};

window.gamePost = window.gamePost || function gamePost(type, payload = {}) {
    if (window.parent && window.parent !== window) {
        window.parent.postMessage({ type, ...payload }, window.location.origin);
    }
};

window.ISIM_SEHIR_BOOTSTRAP = {
	    roomId: {{ $room->id }},
	    sessionId: {{ $gameSession->id }},
	    currentUserId: {{ auth()->id() }},
    canManage: {{ $canManageGame ? 'true' : 'false' }},
    state: {!! json_encode($initialState) !!},
    csrf: document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
    broadcastDriver: '{{ config('broadcasting.default', 'null') }}',
    broadcastConfig: {!! json_encode([
        'reverb' => [
            'key'    => config('broadcasting.connections.reverb.key'),
            'host'   => config('broadcasting.connections.reverb.options.host', 'localhost'),
            'port'   => config('broadcasting.connections.reverb.options.port', 8080),
            'scheme' => config('broadcasting.connections.reverb.options.scheme', 'http'),
        ],
        'pusher' => [
            'key'     => config('broadcasting.connections.pusher.key'),
            'cluster' => config('broadcasting.connections.pusher.options.cluster', 'eu'),
            'host'    => config('broadcasting.connections.pusher.options.host'),
            'port'    => config('broadcasting.connections.pusher.options.port', 443),
            'scheme'  => config('broadcasting.connections.pusher.options.scheme', 'https'),
        ],
    ]) !!},
};

function isimSehirGame() {
    return {
	        state: window.ISIM_SEHIR_BOOTSTRAP.state,
	        answers: {},
	        answerRoundId: null,
	        remainingLabel: '--',
        tickTimer: null,
			        pollTimer: null,
			        refreshInFlight: false,
			        refreshFailures: 0,
			        refreshWarning: '',
			        nextRefreshDelay: 1500,
			        realtimeChannelName: null,
			        beforeUnloadHandler: null,
				        draftInFlight: false,
			        draftQueued: false,
				        actionInFlight: false,
				        draftStatus: '',
				        finishedNotified: false,
				        settingsOpen: false,
				        settingsSaving: false,
				        settingsStatus: '',
				        settingsRoundSeconds: window.ISIM_SEHIR_BOOTSTRAP.state.session?.round_time_seconds || 420,
				        settingsCategories: [...(window.ISIM_SEHIR_BOOTSTRAP.state.categories || [])],
				        newCategory: '',
				        settingsDirty: false,

			        init() {
			            this.hydrateAnswers();
			            this.resetSettingsForm();
			            this.startTicker();
		            this.subscribeRealtime();
		            this.scheduleRefresh();
		            this.beforeUnloadHandler = () => this.cleanup();
		            window.addEventListener('beforeunload', this.beforeUnloadHandler);
			            gamePost(window.GAME_EVENTS.LOADED, {
		                roomId: window.ISIM_SEHIR_BOOTSTRAP.roomId,
		                sessionId: window.ISIM_SEHIR_BOOTSTRAP.sessionId,
		            });
	        },

		        hydrateAnswers() {
	            this.answers = { ...(this.state.my_submission?.answers || {}) };
	            for (const category of this.state.categories || []) {
	                if (!(category in this.answers)) this.answers[category] = '';
	            }
		            this.answerRoundId = this.state.round?.id || null;
		        },

		        destroy() {
		            this.cleanup();
		        },

		        cleanup() {
		            clearInterval(this.tickTimer);
		            clearTimeout(this.pollTimer);
		            this.cleanupRealtime();
		            if (this.beforeUnloadHandler) {
		                window.removeEventListener('beforeunload', this.beforeUnloadHandler);
		                this.beforeUnloadHandler = null;
		            }
		        },

		        resetSettingsForm() {
		            this.settingsRoundSeconds = Number(this.state.session?.round_time_seconds || 420);
		            this.settingsCategories = [...(this.state.categories || [])];
		            this.newCategory = '';
		            this.settingsDirty = false;
		            this.settingsStatus = '';
		        },

        startTicker() {
            clearInterval(this.tickTimer);
            const tick = () => {
                const deadline = this.state.round?.submission_deadline ? new Date(this.state.round.submission_deadline).getTime() : 0;
                if (!deadline || this.state.round?.status !== 'collecting') {
                    this.remainingLabel = '--';
                    return;
                }
                const left = Math.max(0, Math.floor((deadline - Date.now()) / 1000));
                this.remainingLabel = `${Math.floor(left / 60)}:${String(left % 60).padStart(2, '0')}`;
	                if (left === 0 && !this.refreshInFlight) this.refresh();
            };
            tick();
            this.tickTimer = setInterval(tick, 1000);
        },

        roundStatusText() {
            if (!this.state.round) return 'Oyuncular bekleniyor.';
            if (this.state.round.status === 'collecting') return this.isLocked() ? 'Cevabın kilitlendi.' : 'Cevaplar toplanıyor.';
            return 'Tur kapandı, puanlar yayınlandı.';
        },

		        isCollecting() { return this.state.round?.status === 'collecting'; },
		        isLocked() { return !!this.state.my_submission?.is_locked; },
		        isFinished() { return this.state.session?.status === 'finished' || this.state.session?.status === 'cancelled'; },
		        activeParticipants() {
		            return (this.state.participants || []).filter(p => p.is_active);
		        },
		        canBeginRound() {
		            const active = this.activeParticipants();
		            return !this.isCollecting() && !this.isFinished() && active.length >= 2 && active.every(p => p.is_ready);
		        },
		        myParticipant() {
	            return (this.state.participants || []).find(p => Number(p.user_id) === Number(window.ISIM_SEHIR_BOOTSTRAP.currentUserId));
	        },

		        async request(path, body = {}) {
		            const r = await fetch(path, {
		                method: 'POST',
		                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': window.ISIM_SEHIR_BOOTSTRAP.csrf, 'Accept': 'application/json' },
		                body: JSON.stringify(body),
		            });
		            const data = await r.json().catch(() => ({}));
		            if (!r.ok) {
		                const error = new Error(data.message || Object.values(data.errors || {})?.[0]?.[0] || 'İşlem başarısız');
		                error.status = r.status;
		                error.errors = data.errors || {};
		                throw error;
		            }
		            if (data.state) this.applyState(data.state);
		            return data;
		        },

	        guardedRequest(path, body = {}) {
	            if (this.actionInFlight) return Promise.resolve({});
	            this.actionInFlight = true;
	            return this.request(path, body).finally(() => { this.actionInFlight = false; });
	        },

		        applyState(state) {
		            const previousRoundId = this.state.round?.id || null;
		            const nextRoundId = state.round?.id || null;
		            const nextLocked = !!state.my_submission?.is_locked;
			            this.state = state;
			            if (!this.settingsDirty && !this.settingsSaving) this.resetSettingsForm();
		            if (nextRoundId !== previousRoundId || nextLocked || !this.answerRoundId) {
	                this.hydrateAnswers();
	            } else {
	                for (const category of this.state.categories || []) {
	                    if (!(category in this.answers)) this.answers[category] = state.my_submission?.answers?.[category] || '';
	                }
		            }
		            this.startTicker();
			            if (this.isFinished() && !this.finishedNotified) {
			                this.finishedNotified = true;
			                gamePost(window.GAME_EVENTS.FINISHED, {
			                    roomId: window.ISIM_SEHIR_BOOTSTRAP.roomId,
			                    sessionId: window.ISIM_SEHIR_BOOTSTRAP.sessionId,
			                });
		            }
		        },

        refreshDelay() {
            return this.isCollecting() ? 10000 : 1500;
        },

        scheduleRefresh(delay = null) {
            clearTimeout(this.pollTimer);
            this.pollTimer = setTimeout(() => this.refresh(), delay ?? this.nextRefreshDelay ?? this.refreshDelay());
        },

        handleRefreshRedirect(status) {
            const fallback = `/rooms/${window.ISIM_SEHIR_BOOTSTRAP.roomId}`;
            if (status === 401) {
                showToast('Oturum süresi doldu. Giriş sayfasına yönlendiriliyorsun.', 'error');
                setTimeout(() => { window.location.href = '/login'; }, 1200);
                return true;
            }
            if (status === 403 || status === 404) {
                showToast(status === 403 ? 'Bu oyuna erişim iznin yok.' : 'Oyun bulunamadı.', 'error');
                setTimeout(() => { window.location.href = fallback; }, 1200);
                return true;
            }
            return false;
        },

        refresh() {
            if (this.refreshInFlight) return Promise.resolve();
            this.refreshInFlight = true;

            return fetch(`/rooms/${window.ISIM_SEHIR_BOOTSTRAP.roomId}/games/${window.ISIM_SEHIR_BOOTSTRAP.sessionId}/state`, { headers: { 'Accept': 'application/json' } })
                .then(async r => {
                    const data = await r.json().catch(() => ({}));
                    if (!r.ok) {
                        const error = new Error(data.message || Object.values(data.errors || {})?.[0]?.[0] || 'Oyun durumu güncellenemedi');
                        error.status = r.status;
                        error.errors = data.errors || {};
                        throw error;
                    }
                    this.refreshFailures = 0;
                    this.refreshWarning = '';
                    this.applyState(data);
                    this.nextRefreshDelay = this.refreshDelay();
                })
                .catch(error => {
                    if (this.handleRefreshRedirect(error.status)) return;

                    this.refreshFailures += 1;
                    this.nextRefreshDelay = Math.min(60000, 5000 * (2 ** Math.min(this.refreshFailures, 3)));
                    this.refreshWarning = `Oyun durumu güncellenemedi. ${Math.round(this.nextRefreshDelay / 1000)} sn içinde tekrar denenecek.`;

                    if (error.status === 422) {
                        showToast(error.message || 'Oyun durumu güncellenemedi', 'error');
                    } else if (this.refreshFailures === 1 || this.refreshFailures % 3 === 0) {
                        showToast('Bağlantı sorunu nedeniyle oyun durumu yenilenemedi', 'error');
                    }
                })
                .finally(() => {
                    this.refreshInFlight = false;
                    if (!this.isFinished()) this.scheduleRefresh();
                });
        },

	        join() { return this.guardedRequest(`/rooms/${window.ISIM_SEHIR_BOOTSTRAP.roomId}/games/${window.ISIM_SEHIR_BOOTSTRAP.sessionId}/join`).catch(e => showToast(e.message, 'error')); },
	        leave() { return this.guardedRequest(`/rooms/${window.ISIM_SEHIR_BOOTSTRAP.roomId}/games/${window.ISIM_SEHIR_BOOTSTRAP.sessionId}/leave`).catch(e => showToast(e.message, 'error')); },
		        ready() { return this.guardedRequest(`/rooms/${window.ISIM_SEHIR_BOOTSTRAP.roomId}/games/${window.ISIM_SEHIR_BOOTSTRAP.sessionId}/ready`, { is_ready: !this.myParticipant()?.is_ready }).catch(e => showToast(e.message, 'error')); },
		        beginRound() { return this.guardedRequest(`/rooms/${window.ISIM_SEHIR_BOOTSTRAP.roomId}/games/${window.ISIM_SEHIR_BOOTSTRAP.sessionId}/begin-round`).catch(e => showToast(e.message, 'error')); },
	        finalizeRound() {
	            if (!this.state.round) return;
	            return this.guardedRequest(`/rooms/${window.ISIM_SEHIR_BOOTSTRAP.roomId}/games/${window.ISIM_SEHIR_BOOTSTRAP.sessionId}/rounds/${this.state.round.id}/finalize`).catch(e => showToast(e.message, 'error'));
	        },
			        finish() {
		            this.draftQueued = false;
		            return this.guardedRequest(`/rooms/${window.ISIM_SEHIR_BOOTSTRAP.roomId}/games/${window.ISIM_SEHIR_BOOTSTRAP.sessionId}/finish`)
		                .then(() => {
		                    showToast('Oyun bitirildi');
		                })
		                .catch(e => showToast(e.message, 'error'));
			        },

		        toggleSettings() {
		            this.settingsOpen = !this.settingsOpen;
		            if (this.settingsOpen) this.resetSettingsForm();
		        },

		        settingsDurationLabel() {
		            const seconds = Number(this.settingsRoundSeconds || 0);
		            if (!seconds) return '';
		            const minutes = Math.floor(seconds / 60);
		            const rest = seconds % 60;
		            return rest ? `${minutes} dk ${rest} sn` : `${minutes} dk`;
		        },

		        normalizeCategory(value) {
		            return String(value || '').trim().slice(0, 30);
		        },

		        addCategory() {
		            const category = this.normalizeCategory(this.newCategory);
		            if (!category) return;
		            const exists = this.settingsCategories.some(item => item.toLocaleLowerCase('tr-TR') === category.toLocaleLowerCase('tr-TR'));
		            if (exists) {
		                this.newCategory = '';
		                return;
		            }
		            if (this.settingsCategories.length >= 10) return;
		            this.settingsCategories.push(category);
		            this.newCategory = '';
		            this.settingsDirty = true;
		        },

		        removeCategory(category) {
		            if (this.settingsCategories.length <= 2) return;
		            this.settingsCategories = this.settingsCategories.filter(item => item !== category);
		            this.settingsDirty = true;
		        },

		        saveSettings() {
		            const categories = this.settingsCategories.map(category => this.normalizeCategory(category)).filter(Boolean);
		            const roundTime = Number(this.settingsRoundSeconds || 0);
		            this.settingsSaving = true;
		            this.settingsStatus = 'Kaydediliyor...';
		            return this.request(`/rooms/${window.ISIM_SEHIR_BOOTSTRAP.roomId}/games/${window.ISIM_SEHIR_BOOTSTRAP.sessionId}/settings`, {
		                round_time_seconds: roundTime,
		                categories,
		            })
		                .then(() => {
		                    this.settingsDirty = false;
		                    this.settingsStatus = 'Ayarlar kaydedildi';
		                    showToast('Oyun ayarları güncellendi');
		                })
		                .catch(e => {
		                    this.settingsStatus = e.message || 'Ayarlar kaydedilemedi';
		                    showToast(this.settingsStatus, 'error');
		                })
		                .finally(() => { this.settingsSaving = false; });
		        },

			        collectAnswers() {
		            const clean = {};
		            for (const category of this.state.categories || []) {
		                clean[category] = this.answers?.[category] || '';
		            }
		            return clean;
		        },

		        queueDraft() {
		            this.draftQueued = true;
		            this.saveDraft();
		        },

		        saveDraft() {
		            if (!this.state.round || !this.isCollecting() || this.isLocked()) {
		                this.draftQueued = false;
		                return;
		            }
		            if (this.draftInFlight) return;
		            const roundId = this.state.round.id;
		            const payload = { answers: this.collectAnswers() };
		            this.draftInFlight = true;
		            this.draftQueued = false;
		            this.draftStatus = 'Kaydediliyor...';
		            this.request(`/rooms/${window.ISIM_SEHIR_BOOTSTRAP.roomId}/games/${window.ISIM_SEHIR_BOOTSTRAP.sessionId}/rounds/${roundId}/draft`, payload)
		                .then(() => {
		                    if (this.state.round?.id === roundId && this.isCollecting() && !this.isLocked()) {
		                        this.draftStatus = 'Kaydedildi';
		                    }
		                })
		                .catch((error) => {
		                    const roundClosed = error.status === 422 && (error.errors?.round || (error.message || '').includes('tur'));
		                    this.draftStatus = roundClosed ? 'Tur kapandı, artık kaydedilemez.' : (error.message || 'Kayıt hatası');
		                    if (roundClosed) this.refresh();
		                })
		                .finally(() => {
		                    this.draftInFlight = false;
		                    if (this.draftQueued && this.state.round?.id === roundId && this.isCollecting() && !this.isLocked()) {
		                        this.saveDraft();
		                    }
		                });
		        },

	        submit() {
	            if (!this.state.round) return;
	            this.draftQueued = false;
		            return this.guardedRequest(`/rooms/${window.ISIM_SEHIR_BOOTSTRAP.roomId}/games/${window.ISIM_SEHIR_BOOTSTRAP.sessionId}/rounds/${this.state.round.id}/submit`, { answers: this.collectAnswers() })
	                .then(() => showToast('Cevaplar kilitlendi'))
	                .catch(e => showToast(e.message, 'error'));
	        },

	        subscribeRealtime() {
	            if (this.realtimeChannelName) return;
	            if (!window.KANKIO_ECHO && window.LaravelEcho && window.Pusher) {
	                const cfg = window.ISIM_SEHIR_BOOTSTRAP.broadcastConfig;
	                const driver = window.ISIM_SEHIR_BOOTSTRAP.broadcastDriver;
                const c = driver === 'pusher' ? (cfg.pusher || {}) : (cfg.reverb || {});
                window.KANKIO_ECHO = new window.LaravelEcho({
                    broadcaster: driver === 'pusher' ? 'pusher' : 'reverb',
                    key: c.key,
                    cluster: c.cluster,
                    wsHost: c.host || window.location.hostname,
                    wsPort: c.port || 80,
                    wssPort: c.port || 443,
                    forceTLS: (c.scheme || 'https') === 'https',
                    enabledTransports: ['ws', 'wss'],
                    authEndpoint: '/broadcasting/auth',
                    auth: { headers: { 'X-CSRF-TOKEN': window.ISIM_SEHIR_BOOTSTRAP.csrf } },
	                });
	            }
	            if (window.KANKIO_ECHO) {
	                this.realtimeChannelName = `room.${window.ISIM_SEHIR_BOOTSTRAP.roomId}.game`;
	                window.KANKIO_ECHO.private(this.realtimeChannelName)
	                    .listen('.game.session', (payload) => {
	                        if (Number(payload.game_session_id) === Number(window.ISIM_SEHIR_BOOTSTRAP.sessionId)) {
	                            this.refresh();
	                        }
	                    });
	            }
	        },

	        cleanupRealtime() {
	            if (!this.realtimeChannelName || !window.KANKIO_ECHO) return;
	            window.KANKIO_ECHO.leave(this.realtimeChannelName);
	            this.realtimeChannelName = null;
	        },
	    };
	}
window.__kankioLoadAlpine?.();
</script>
@endpush
