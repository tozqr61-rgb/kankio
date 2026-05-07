import { createVoiceRuntime, stopPermissionStream } from './voice-client';
import { GAME_EVENTS, handleGameMessage, normalizeGameUrl } from './game-overlay';
import { isDocumentHidden } from './presence';
import { safeJson } from './message-actions';

const boot = window.KANKIO_CHAT_BOOTSTRAP || {};
let ROOM_ID = String(boot.roomId || '');
const CURRENT_USER = boot.currentUser || {};
const IS_ADMIN = !!boot.isAdmin;
const INIT_MSGS = boot.initMsgs || [];
const ROOM_TYPE = boot.roomType || 'global';
const BROADCAST_DRIVER = boot.broadcastDriver || window.KANKIO_BROADCAST_DRIVER || 'null';
const BROADCAST_CONFIG = boot.broadcastConfig || window.KANKIO_BROADCAST_CONFIG || {};
const NOTIFICATIONS_ENABLED = !!boot.notificationsEnabled;
const ARCHIVED_COUNT = Number(boot.archivedCount || 0);
const ROOM_NAME = boot.roomName || '';
/* LiveKit runtime — NOT inside Alpine reactive state to avoid DataCloneError
   when LiveKit SDK internally calls structuredClone on proxied objects. */
const KankioVoiceRuntime = createVoiceRuntime();

function getVoiceRoom() { return KankioVoiceRuntime.lkRoom; }
function setVoiceRoom(room) { KankioVoiceRuntime.lkRoom = room; }
function resetVoiceRuntime() {
    KankioVoiceRuntime.lkRoom = null;
    KankioVoiceRuntime.audioEls = {};
    KankioVoiceRuntime.micToggleInFlight = false;
    KankioVoiceRuntime.lastAppliedMicEnabled = null;
    KankioVoiceRuntime.permissionStream = null;
}

function stopPermissionStreamIfAny() {
    stopPermissionStream(KankioVoiceRuntime);
}

window.addEventListener('pagehide', stopPermissionStreamIfAny);
window.addEventListener('beforeunload', stopPermissionStreamIfAny);
document.addEventListener('visibilitychange', () => {
    if (document.visibilityState === 'hidden' && !getVoiceRoom()) {
        stopPermissionStreamIfAny();
    }
});

window.chatRoom = function chatRoom() {
    return {
        /* Chat state */
        messages: [],
        inputValue: '',
        replyingTo: null,
        sending: false,
        connected: false,
        lastMessageAt: null,
        currentUser: CURRENT_USER,
        isAdmin: IS_ADMIN,
        notifyEnabled: NOTIFICATIONS_ENABLED,
        browserNotificationPermission: (typeof Notification !== 'undefined' ? Notification.permission : 'default'),

        /* Poll state - adaptive */
        _pollTimer: null,
        _stayConnectedPollTimer: null,
        _lastStayConnectedTriggerId: null,
        _idleCount: 0,
        _hidden: false,
        _realtimeReady: false,

        /* Right sidebar state */
        musicOpen: false,
        gameOpen: false,
        gameUrl: '',

        /* Music state */
        musicState: { video_id: null, video_title: null, is_playing: false, position: 0, queue: [] },
        musicPlaybackUnlocked: false,
        showMusicUnlockPrompt: false,
        _ytIframe: null,             /* direct iframe element */
        _ytCurVid: '',               /* video_id currently in the iframe */
        _ytCurPlay: null,            /* is_playing state currently applied */
        _lastMusicHash: '',
        _playerMuted: true,           /* true = muted, false = sound on */

        /* Room type */
        roomType: ROOM_TYPE,
        announcementTitle: '',

        /* Archived messages */
        archivedCount: ARCHIVED_COUNT,
        showArchived: false,
        archivedMessages: [],
        archivedLoading: false,
        archivedPage: 1,
        archivedHasMore: false,

        /* Mobile navigation */
        mobileTab: 'chat',       /* 'chat' | 'voice' */
        _speakRaf: null,         /* rAF handle for local speaking detection */
        _remoteRafs: {},         /* { userId: rafId } for remote speaking loops */
        _videoEndedAt: 0,        /* debounce timestamp for _onVideoEnded */
        _endTimer: null,          /* JS timer that fires when video duration runs out */
        _knownDuration: 0,        /* duration captured from infoDelivery (no API key needed) */
        _ytPollTimer: null,       /* interval: polls getDuration/getCurrentTime from iframe */
        _lastCurrentTime: 0,      /* last known currentTime from iframe poll */

        /* Voice message recording */
        _voiceRecording: false,
        _voiceMediaRec: null,
        _voiceChunks: [],
        _voiceRecordSecs: 0,
        _voiceRecTimer: null,
        _voiceRecStream: null,

        /* Audio playback */
        _playingAudioId: null,
        _audioEl: null,
        _audioProgress: 0,
        _audioProgressTimer: null,

        /* Message seen tracking */
        _seenTimer: null,
        typingUsers: {},
        _typingTimer: null,
        _typingSent: false,

        /* SPA room state */
        _roomId:   ROOM_ID,
        _roomName: ROOM_NAME,

        /* Voice state */
        voiceState: {
            in_voice: false, is_muted: false, is_deafened: false, can_speak: true,
            connection_quality: 'unknown', reconnect_count: 0, can_moderate: false,
            settings: {}, participants: []
        },
        _voiceRoomId: null,   /* room where voice is LOCKED — never changes during navigation */
        _voiceTimer: null,
        _manualVoiceLeave: false,
        _voiceReconnectAttempts: 0,
        _voiceReconnectTimer: null,
        _voiceJoinInFlight: false,      /* prevents concurrent voiceJoin() races */
        voiceConnectionStatus: 'idle', /* idle | connecting | connected | reconnecting | failed */
        voicePrefs: { noiseSuppression: true, echoCancellation: true, pushToTalk: false, lowBandwidth: false },
        _pttDown: false,

        /* Active speaker detection */
        _speakingUsers: {},   /* { userId: bool } */
        _speakingTimer: null,

        /* Shared Echo instance */
        _echo: null,

        /* PWA background state */
        _silentAudio: null,     /* AudioBufferSourceNode — keeps audio pipeline alive */
        _isBackground: false,
        _reconnectTimer: null,

        init() {
            const pathMatch = window.location.pathname.match(/\/chat\/(\d+)/);
            if (pathMatch) {
                this._roomId = String(pathMatch[1]);
                ROOM_ID = this._roomId;
            }
            if (typeof Alpine !== 'undefined' && Alpine.store('chat')) {
                Alpine.store('chat').activeRoomId = this._roomId;
            }

            this.messages = INIT_MSGS;
            if (this.messages.length > 0) {
                this.lastMessageAt = this.messages[this.messages.length - 1].created_at;
            }
            this.$nextTick(() => this.scrollToBottom());

            /* Expose layout reference (Alpine v3 compatible) */
            setTimeout(() => {
                const el = document.querySelector('[x-data="chatLayout()"]');
                if (el) window._chatLayout = (typeof Alpine !== 'undefined' && Alpine.$data) ? Alpine.$data(el) : (el._x_dataStack?.[0]);
            }, 500);

            /* Expose changeRoom globally for sidebar SPA links */
            window._changeRoom = (id, name) => this.changeRoom(id, name);

            window.addEventListener('message', (event) => {
                handleGameMessage(event, {
                    close: () => this.closeIsimSehirGame(),
                    loaded: () => { this.gameOpen = true; },
                    finished: () => showToast('Oyun bitti. Sonuçlar oyun ekranında kalıyor.'),
                    toast: (data) => showToast(data.message || 'Oyun işlemi tamamlanamadı', data.level === 'error' ? 'error' : 'success'),
                });
            });

            window.addEventListener('presence-mode-changed', (event) => {
                this.currentUser.presence_mode = event.detail?.presence_mode || 'online';
                if (this.currentUser.presence_mode === 'invisible') {
                    this.sendTyping(false);
                }
            });

            window.addEventListener('popstate', async () => {
                const m = window.location.pathname.match(/\/chat\/(\d+)/);
                if (!m) return;

                const roomId = m[1];
                if (String(roomId) === String(this._roomId)) return;

                await this.changeRoom(roomId, '', { pushState: false });
            });

            /* Pause polling when tab hidden — saves battery/CPU */
            document.addEventListener('visibilitychange', () => {
                this._hidden = isDocumentHidden();
                if (!isDocumentHidden()) this._schedulePoll(500);
            });

            this._schedulePoll(1000);
            this._initEcho();
            this._initBrowserNotifications();
            this._initYT();
            this._initBackgroundHandlers();
            this._initTauri();
            this._startSeenTracking();
            this._startStayConnectedFallback();
            this._initPushToTalk();
        },

        /* ── Room Transition ── */
        _openStayConnectedSurprise(triggeredBy = null) {
            window.dispatchEvent(new CustomEvent('open-stay-connected'));
            const name = triggeredBy?.username || 'Admin';
            showToast(`${name} surprizi baslatti`, 'success');
        },

        _handleStayConnectedTrigger(trigger = {}) {
            if (trigger.id && trigger.id === this._lastStayConnectedTriggerId) return;
            this._lastStayConnectedTriggerId = trigger.id || String(Date.now());
            this._openStayConnectedSurprise(trigger.triggered_by);
        },

        _startStayConnectedFallback() {
            clearInterval(this._stayConnectedPollTimer);
            const check = async () => {
                if (isDocumentHidden()) return;
                try {
                    const r = await fetch('/api/stay-connected/pending', {
                        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    });
                    if (!r.ok) return;
                    const data = await r.json();
                    if (!data.pending || !data.trigger) return;
                    this._handleStayConnectedTrigger(data.trigger);
                } catch (e) {}
            };

            check();
            this._stayConnectedPollTimer = setInterval(check, 2500);
        },

        async changeRoom(id, name, options = {}) {
            id = String(id);
            const pushState = options.pushState !== false;
            if (String(this._roomId) === id) return;
            if (this.gameOpen) {
                this.closeIsimSehirGame();
                showToast('Oyun ekranı kapatıldı');
            }

            try {
                this.connected = false;
                this._leaveRoomRealtimeChannels();

                const r = await fetch(`/api/chat/${id}/bootstrap`, {
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    }
                });

                if (!r.ok) {
                    showToast('Oda yüklenemedi', 'error');
                    this.connected = true;
                    this._joinRoomRealtimeChannels();
                    return;
                }

                const data = await r.json();

                this._roomId = String(data.room?.id || id);
                this._roomName = data.room?.name || name || this._roomName;
                this.roomType = data.room?.type || this.roomType;
                ROOM_ID = this._roomId;
                if (typeof Alpine !== 'undefined' && Alpine.store('chat')) {
                    Alpine.store('chat').activeRoomId = this._roomId;
                }

                this.messages = Array.isArray(data.messages) ? data.messages : [];
                this.lastMessageAt = this.messages.length
                    ? this.messages[this.messages.length - 1].created_at
                    : null;

                this.archivedMessages = [];
                this.archivedPage = 1;
                this.archivedHasMore = false;
                this.archivedCount = data.archived_count || 0;
                this.archivedLoading = false;
                this.showArchived = false;

                this.typingUsers = {};
                this.replyingTo = null;
                this.inputValue = '';
                this.announcementTitle = '';
                this.mobileTab = 'chat';
                this.connected = true;

                if (pushState) {
                    window.history.pushState({}, '', `/chat/${this._roomId}`);
                }

                this._joinRoomRealtimeChannels();

                this.$nextTick(() => this.scrollToBottom());
                this._schedulePoll(300);
            } catch (e) {
                console.error('[room] changeRoom failed', e);
                showToast('Oda değiştirilemedi', 'error');
                this.connected = true;
                this._joinRoomRealtimeChannels();
            }
        },


        /* ── Tauri Desktop Integration ── */
        _initTauri() {
            /* Alt+M global shortcut → sent by src-tauri/src/lib.rs via win.eval() */
            window.addEventListener('tauri-mute-toggle', async () => {
                if (this.voiceState.in_voice) {
                    await this.voiceToggleMute();
                } else {
                    showToast('Ses kanalına girmeden mikrofon açılamaz', 'error');
                }
            });

            /* Auto-update notification from Tauri updater */
            window.addEventListener('tauri-update-available', (e) => {
                const v = e.detail?.version ?? '';
                showToast(`Kankio ${v} güncelleniyor — uygulamayı kapatınca yüklenir`, 'success');
            });
        },

        /* ── Adaptive polling ── */
        _schedulePoll(delay) {
            clearTimeout(this._pollTimer);
            if (this._hidden) return;
            this._pollTimer = setTimeout(() => this._doPoll(), delay);
        },

        async _doPoll() {
            if (this._hidden) return;
            try {
                if (this._realtimeReady) {
                    await this._syncMusic();
                    this._schedulePoll(15000);
                    return;
                }

                /* Messages */
                const activeRoomId = String(this._roomId || ROOM_ID);
                const url = `/api/chat/${activeRoomId}/messages${this.lastMessageAt ? '?since=' + encodeURIComponent(this.lastMessageAt) : ''}`;
                const r = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
                if (!r.ok) { this.connected = false; this._schedulePoll(5000); return; }
                /* If session expired, server returns login page HTML instead of JSON */
                const ct = r.headers.get('content-type') || '';
                if (!ct.includes('application/json')) {
                    window.location.href = `${APP_URL}/login`; return;
                }
                this.connected = true;
                const msgs = await r.json();
                if (msgs.length > 0) {
                    /* Always advance cursor using MAX — never let poll regress the pointer
                       that sendMessage() may have already moved forward. */
                    const tail = msgs[msgs.length - 1].created_at;
                    if (!this.lastMessageAt || tail > this.lastMessageAt) {
                        this.lastMessageAt = tail;
                    }
                    /* Deduplicate: skip messages already in the array (by real numeric ID).
                       This prevents doubles when the poll races with sendMessage()'s
                       optimistic-replace path. */
                    const existingIds = new Set(
                        this.messages.map(m => String(m.id)).filter(id => !id.startsWith('_tmp_'))
                    );
                    const fresh = msgs.filter(m => !existingIds.has(String(m.id)));
                    if (fresh.length > 0) {
                        this.messages.push(...fresh);
                        this.$nextTick(() => this.scrollToBottom());
                        if (this.notifyEnabled) this._playSound();
                        this._idleCount = 0;
                    } else {
                        this._idleCount++;
                    }
                } else {
                    this._idleCount++;
                }
                /* Music sync */
                await this._syncMusic();
            } catch(e) { this.connected = false; }

            /* Adaptive interval: 3s normal, 6s after 20 idle cycles (~1 min quiet) */
            const next = this._idleCount > 20 ? 6000 : 3000;
            this._schedulePoll(next);
        },

        /* Slash command detection — routes to music command API */
        _isSlashCommand(text) {
            return /^\/(play|durdur|stop|pause|ge[cç]|skip|next|s[ıi]ra|queue|devam|resume)\b/i.test(text);
        },

        async _handleSlashCommand(text) {
            this.sending = true;
            this.inputValue = '';
            this.sendTyping(false);

            /* ── User gesture context: unlock iframe audio BEFORE async fetch ──
               Browser autoplay policy requires audio-producing elements to be
               created synchronously within a user gesture (keydown/click).
               After await, the gesture context is consumed → audio blocked.
               Solution: create/unlock the iframe NOW, swap video after fetch. */
            if (this._isSlashCommand(text) && this.voiceState.in_voice) {
                this._playerMuted = false;
                this._ensureIframeReady();
            }

            try {
                const r = await fetch(`/api/music/${ROOM_ID}/command`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
                    body: JSON.stringify({ command: text })
                });
                const ct = r.headers.get('content-type') || '';
                if (!ct.includes('application/json')) {
                    showToast('Oturum süresi dolmuş olabilir — sayfayı yenileyin', 'error'); return;
                }
                const data = await r.json();
                if (!r.ok) {
                    showToast(data.error || data.message || 'Komut başarısız', 'error');
                    return;
                }
                if (data.state) {
                    /* Unmute for ALL music commands when in voice (not just /play) */
                    if (this.voiceState.in_voice) this._playerMuted = false;
                    this.musicState = data.state;
                    this._applyMusicState(data.state);
                }
                /* Force immediate poll to pick up system messages */
                this._idleCount = 0;
                this._schedulePoll(300);
            } catch(e) { showToast('Komut gönderilemedi', 'error'); }
            finally {
                this.sending = false;
                this.$nextTick(() => {
                    const inp = document.querySelector('#input-wrapper input[type="text"]');
                    if (inp) inp.focus();
                });
            }
        },

        /* Send message — Optimistic UI */
        async sendMessage() {
            const content = this.inputValue.trim();
            if (!content || this.sending) return;

            /* Intercept slash commands */
            if (this._isSlashCommand(content)) {
                return this._handleSlashCommand(content);
            }

            this.sending = true;
            const replyId = this.replyingTo ? this.replyingTo.id : null;
            this.replyingTo = null;
            this.inputValue = '';
            this.sendTyping(false);

            /* ── Optimistic: insert message immediately, replace after server confirms ── */
            const tempId = '_tmp_' + Date.now();
            const optimistic = {
                id: tempId,
                content,
                sender: this.currentUser,
                reply_to: replyId ? (this.messages.find(m => m.id === replyId) || null) : null,
                created_at: new Date().toISOString(),
                is_system_message: false,
                _pending: true,
            };
            this.messages.push(optimistic);
            this._idleCount = 0;
            this.$nextTick(() => this.scrollToBottom());

            try {
                const body = { content, reply_to: replyId };
                if (this.roomType === 'announcements') body.title = this.announcementTitle.trim();
                const r = await fetch(`/api/chat/${ROOM_ID}/messages`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF },
                    body: JSON.stringify(body)
                });
                if (!r.ok) {
                    this.messages = this.messages.filter(m => m.id !== tempId);
                    showToast('Mesaj gönderilemedi', 'error'); return;
                }
                const msg = await r.json();
                /* Replace temp placeholder with the confirmed server message.
                   Also remove any copy the poll may have snuck in concurrently
                   so we never end up with two identical real messages. */
                const pollDupIdx = this.messages.findIndex(
                    m => String(m.id) === String(msg.id) && m.id !== tempId
                );
                if (pollDupIdx !== -1) this.messages.splice(pollDupIdx, 1);
                const idx = this.messages.findIndex(m => m.id === tempId);
                if (idx !== -1) this.messages.splice(idx, 1, msg);
                else this.messages.push(msg);
                /* Advance cursor only forward — guard against poll regression */
                if (!this.lastMessageAt || msg.created_at > this.lastMessageAt) {
                    this.lastMessageAt = msg.created_at;
                }
                if (this.roomType === 'announcements') this.announcementTitle = '';
            } catch(e) {
                this.messages = this.messages.filter(m => m.id !== tempId);
                showToast('Mesaj gönderilemedi', 'error');
            } finally {
                this.sending = false;
                this.$nextTick(() => {
                    const inp = document.querySelector('#input-wrapper input[type="text"]');
                    if (inp) inp.focus();
                });
            }
        },

        async deleteMsg(msgId) {
            if (!confirm('Yönetici İşlemi: Bu mesajı silmek üzeresiniz.')) return;
            try {
                await fetch(`/api/chat/${ROOM_ID}/messages/${msgId}`, {
                    method: 'DELETE', headers: { 'X-CSRF-TOKEN': CSRF }
                });
                this.messages = this.messages.filter(m => m.id !== msgId);
            } catch(e) { showToast('Silinemedi', 'error'); }
        },

        setReply(msg) { this.replyingTo = msg; },

        isContinual(idx) {
            if (idx === 0) return false;
            const p = this.messages[idx-1], c = this.messages[idx];
            if (!p || !c || p.is_system_message || c.is_system_message) return false;
            return p.sender && c.sender && p.sender.id === c.sender.id;
        },

        scrollToBottom() {
            const el = this.$refs.msgContainer;
            if (el) el.scrollTop = el.scrollHeight;
        },

        formatTime(iso) {
            if (!iso) return '';
            return new Date(iso).toLocaleTimeString('tr-TR', { hour: '2-digit', minute: '2-digit' });
        },

        _playSound() {
            try {
                const ctx = new (window.AudioContext || window.webkitAudioContext)();
                const o = ctx.createOscillator(), g = ctx.createGain();
                o.connect(g); g.connect(ctx.destination);
                o.type = 'triangle';
                o.frequency.setValueAtTime(1200, ctx.currentTime);
                o.frequency.exponentialRampToValueAtTime(600, ctx.currentTime + 0.25);
                g.gain.setValueAtTime(0.4, ctx.currentTime);
                g.gain.exponentialRampToValueAtTime(0.01, ctx.currentTime + 0.25);
                o.start(); o.stop(ctx.currentTime + 0.25);
            } catch(e) {}
        },

        async _initBrowserNotifications() {
            if (!('serviceWorker' in navigator) || !('PushManager' in window) || !('Notification' in window)) return;
            if (!this.notifyEnabled) return;
            await this._syncPushSubscription();
        },

        async enableBrowserNotifications() {
            if (!('Notification' in window)) {
                showToast('Bu cihaz tarayıcı bildirimlerini desteklemiyor', 'error');
                return;
            }
            const permission = await Notification.requestPermission();
            this.browserNotificationPermission = permission;
            if (permission !== 'granted') {
                showToast('Tarayıcı bildirimi izni verilmedi', 'error');
                return;
            }
            await this._syncPushSubscription(true);
            showToast('Tarayıcı bildirimleri etkinleştirildi');
        },

        async _syncPushSubscription(forcePrompt = false) {
            if (!this.notifyEnabled || !('serviceWorker' in navigator) || !('PushManager' in window) || !window.KANKIO_VAPID_PUBLIC_KEY) return;
            if (Notification.permission === 'denied') {
                this.browserNotificationPermission = 'denied';
                return;
            }
            if (Notification.permission !== 'granted') {
                if (!forcePrompt) return;
                const permission = await Notification.requestPermission();
                this.browserNotificationPermission = permission;
                if (permission !== 'granted') return;
            }
            const reg = await (window.KANKIO_SW_REG_PROMISE || navigator.serviceWorker.ready);
            if (!reg) return;
            let sub = await reg.pushManager.getSubscription();
            if (!sub) {
                sub = await reg.pushManager.subscribe({
                    userVisibleOnly: true,
                    applicationServerKey: this._urlBase64ToUint8Array(window.KANKIO_VAPID_PUBLIC_KEY),
                });
            }
            await fetch('/api/push/subscribe', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
                body: JSON.stringify(sub.toJSON()),
            });
        },

        async _unsubscribePush() {
            if (!('serviceWorker' in navigator)) return;
            const reg = await (window.KANKIO_SW_REG_PROMISE || navigator.serviceWorker.ready);
            if (!reg) return;
            const sub = await reg.pushManager.getSubscription();
            if (!sub) return;
            await fetch('/api/push/subscribe', {
                method: 'DELETE',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
                body: JSON.stringify({ endpoint: sub.endpoint }),
            }).catch(() => {});
            await sub.unsubscribe().catch(() => {});
        },

        _maybeShowBrowserNotification(msg) {
            if (!this.notifyEnabled || !msg || !msg.sender || String(msg.sender.id) === String(this.currentUser.id)) return;
            if (document.visibilityState === 'visible') return;
            const title = msg.sender.username || 'Yeni mesaj';
            const body = (msg.content || 'Yeni mesaj').slice(0, 160);
            if ('serviceWorker' in navigator) {
                navigator.serviceWorker.ready.then(reg => {
                    reg.showNotification(title, {
                        body,
                        icon: '/icons/icon.svg',
                        badge: '/icons/icon.svg',
                        tag: `room-${this._roomId}`,
                        data: { url: `/chat/${this._roomId}` },
                    });
                }).catch(() => {});
            }
        },

        _urlBase64ToUint8Array(base64String) {
            const padding = '='.repeat((4 - base64String.length % 4) % 4);
            const base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
            const rawData = window.atob(base64);
            const outputArray = new Uint8Array(rawData.length);
            for (let i = 0; i < rawData.length; ++i) outputArray[i] = rawData.charCodeAt(i);
            return outputArray;
        },

        _appendRealtimeMessage(msg) {
            if (!msg || !msg.id) return;
            const exists = this.messages.some(m => String(m.id) === String(msg.id));
            if (exists) return;
            this.messages.push(msg);
            if (!this.lastMessageAt || msg.created_at > this.lastMessageAt) this.lastMessageAt = msg.created_at;
            this._idleCount = 0;
            this._playSound();
            this._maybeShowBrowserNotification(msg);
            this.$nextTick(() => this.scrollToBottom());
            this._handleBotData(msg.bot_data);
        },

        _handleBotData(botData) {
            if (!botData || !botData.action) return;
            if (botData.action === 'game:open' && botData.game_url) {
                this.gameUrl  = botData.game_url;
                this.gameOpen = true;
            }
        },

        async sendTyping(isTyping) {
            if (this.roomType === 'announcements') return;
            if (this.currentUser?.presence_mode === 'invisible' && isTyping) return;
            if (isTyping && !this.inputValue.trim()) isTyping = false;
            if (this._typingSent === isTyping) return;
            this._typingSent = isTyping;
            clearTimeout(this._typingTimer);
            if (isTyping) {
                this._typingTimer = setTimeout(() => this.sendTyping(false), 2500);
            }
            try {
                await fetch(`/api/chat/${ROOM_ID}/typing`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF },
                    body: JSON.stringify({ is_typing: isTyping })
                });
            } catch(e) {}
        },

        _applyTyping(user, isTyping) {
            if (!user || user.id == this.currentUser.id) return;
            if (!isTyping) {
                delete this.typingUsers[user.id];
            } else {
                this.typingUsers[user.id] = user;
                setTimeout(() => { delete this.typingUsers[user.id]; this.typingUsers = { ...this.typingUsers }; }, 3500);
            }
            this.typingUsers = { ...this.typingUsers };
        },

        _applyReadReceipt(readerId, messageIds) {
            if (!Array.isArray(messageIds)) return;
            const ids = new Set(messageIds.map(id => String(id)));
            this.messages = this.messages.map(m => {
                if (!ids.has(String(m.id))) return m;
                const readers = new Set(m.read_by || []);
                readers.add(readerId);
                return { ...m, read_by: Array.from(readers), read_count: readers.size };
            });
        },

        toggleLeft() {
            window.dispatchEvent(new CustomEvent('toggle-left-sidebar'));
        },

        /* ═══════════ MUSIC SYSTEM — DISCORD BOT APPROACH ═══════════
         *
         * SERVER IS THE CLOCK. Position formula:
         *   playing  → pos = floor(Date.now()/1000 - started_at_unix)
         *   paused   → pos = state.position
         * All clients compute identical position from the same timestamp.
         * Reverb WebSocket pushes instant updates; polling is the fallback.
         * ══════════════════════════════════════════════════════════════════ */

        /* ══════════════════════════════════════════════════════════
         * PWA Background State & Network Resilience
         * ══════════════════════════════════════════════════════════ */
        _initBackgroundHandlers() {

            /* \u2500\u2500 visibilitychange: handle app going to background \u2500\u2500 */
            document.addEventListener('visibilitychange', () => {
                if (isDocumentHidden()) {
                    this._isBackground = true;
                    this._startSilentAudio(); /* keep audio pipeline alive on mobile */
                } else {
                    this._isBackground = false;
                    this._stopSilentAudio();
                    /* Immediately re-sync music state when returning */
                    this._syncMusic();
                    /* Resume suspended AudioContexts */
                    if (this._audioCtx?.state === 'suspended') this._audioCtx.resume().catch(() => {});
                }
            });

            /* \u2500\u2500 offline / online: auto-reconnect Reverb + WebRTC \u2500\u2500 */
            window.addEventListener('offline', () => {
                this.connected = false;
            });

            window.addEventListener('online', () => {
                /* Reconnect Echo WebSocket */
                clearTimeout(this._reconnectTimer);
                this._reconnectTimer = setTimeout(() => {
                    if (this._echo) {
                        try { this._echo.connector?.pusher?.connect?.(); } catch(e) {}
                    }
                    /* Re-sync music state */
                    this._syncMusic();
                    /* Re-create broken voice connections */
                    const room = getVoiceRoom();
                    if (this.voiceState.in_voice && room && room.state === 'disconnected') {
                        this._scheduleVoiceReconnect();
                    }
                    this.connected = true;
                }, 1500);
            });
        },

        /* \u2500\u2500 Silent audio trick: plays 1-second silence loop via Web Audio API
           This prevents mobile browsers from throttling/suspending the AudioContext
           and stops WebRTC audio tracks from being paused when the app is backgrounded.
           1 buffer of silence = ~172 bytes RAM. No network usage. \u2500\u2500 */
        _startSilentAudio() {
            if (this._silentAudio || !this.voiceState.in_voice) return;
            try {
                const ctx    = this._audioCtx ?? new (window.AudioContext || window.webkitAudioContext)();
                const buffer = ctx.createBuffer(1, ctx.sampleRate, ctx.sampleRate); /* 1s silence */
                const source = ctx.createBufferSource();
                source.buffer = buffer;
                source.loop   = true;
                source.connect(ctx.destination);
                source.start(0);
                this._silentAudio = source;
            } catch(e) {}
        },

        _stopSilentAudio() {
            if (!this._silentAudio) return;
            try { this._silentAudio.stop(); } catch(e) {}
            this._silentAudio = null;
        },

        /* ── Centralised Echo init — music + voice channels ── */
        _initEcho() {
            if (!window.LaravelEcho || !BROADCAST_DRIVER || BROADCAST_DRIVER === 'null' || BROADCAST_DRIVER === 'log') return;
            try {
                let echoConfig;
                if (BROADCAST_DRIVER === 'pusher') {
                    const c = BROADCAST_CONFIG.pusher;
                    echoConfig = {
                        broadcaster:  'pusher',
                        key:          c.key,
                        cluster:      c.cluster,
                        forceTLS:     c.scheme === 'https',
                        disableStats: true,
                    };
                } else {
                    /* reverb */
                    const c = BROADCAST_CONFIG.reverb;
                    echoConfig = {
                        broadcaster:       'reverb',
                        key:               c.key,
                        wsHost:            c.host,
                        wsPort:            c.port,
                        wssPort:           c.port,
                        forceTLS:          c.scheme === 'https',
                        disableStats:      true,
                        enabledTransports: ['ws'],
                    };
                }
                this._echo = window.KANKIO_ECHO || new window.LaravelEcho({
                    ...echoConfig,
                    auth: {
                        headers: { 'X-CSRF-TOKEN': CSRF },
                    },
                    authEndpoint: `/broadcasting/auth`,
                });
                window.KANKIO_ECHO = this._echo;

                this._joinUserRealtimeChannel();
                this._joinRoomRealtimeChannels();
                this._realtimeReady = true;

            } catch(e) {
                console.warn('[echo] init failed, polling fallback active', e);
            }
        },

        _joinUserRealtimeChannel() {
            if (!this._echo || this._stayConnectedUserChannelJoined) return;
            const userId = this.currentUser?.id;
            if (!userId) return;

            this._echo.private(`App.Models.User.${userId}`)
                .listen('.stay.connected', ({ id, triggered_by }) => {
                    this._handleStayConnectedTrigger({ id, triggered_by });
                });

            this._stayConnectedUserChannelJoined = true;
        },

        _leaveRoomRealtimeChannels() {
            if (!this._echo) return;

            const roomId = String(this._roomId || '');
            if (!roomId) return;

            try { this._echo.leave(`private-room.${roomId}.chat`); } catch (_) {}
            try { this._echo.leave(`private-room.${roomId}.music`); } catch (_) {}
            try { this._echo.leave(`private-room.${roomId}.voice`); } catch (_) {}

            try { this._echo.leave(`room.${roomId}.chat`); } catch (_) {}
            try { this._echo.leave(`room.${roomId}.music`); } catch (_) {}
            try { this._echo.leave(`room.${roomId}.voice`); } catch (_) {}
        },

        _joinRoomRealtimeChannels() {
            if (!this._echo) return;

            const roomId = String(this._roomId || '');
            if (!roomId) return;

            /* Chat events: realtime primary; polling remains only as a quiet fallback */
            this._echo.private(`room.${roomId}.chat`)
                .listen('.message.sent', ({ message }) => {
                    this._appendRealtimeMessage(message);
                })
                .listen('.message.deleted', ({ message_id }) => {
                    this.messages = this.messages.filter(m => String(m.id) !== String(message_id));
                })
                .listen('.typing', ({ user, is_typing }) => {
                    this._applyTyping(user, is_typing);
                })
                .listen('.messages.read', ({ reader_id, message_ids }) => {
                    this._applyReadReceipt(reader_id, message_ids);
                })
                .listen('.stay.connected', ({ id, triggered_by }) => {
                    this._handleStayConnectedTrigger({ id, triggered_by });
                });

            /* Music state push */
            this._echo.private(`room.${roomId}.music`)
                .listen('.music.state', (state) => {
                    this.musicState = state;
                    this._applyMusicState(state);
                });

            /* Voice state push updates the visible room only; LiveKit stays locked to _voiceRoomId. */
            this._echo.private(`room.${roomId}.voice`)
                .listen('.voice.state', (data) => {
                    if (String(this._voiceRoomId || '') !== String(this._roomId || '')) return;

                    this._applyVoiceState({
                        participants: data.participants || [],
                        settings: data.settings || this.voiceState.settings
                    });
                })
                .listen('.voice.participant', ({ action, participant }) => {
                    if (String(this._voiceRoomId || '') !== String(this._roomId || '')) return;
                    this._applyVoiceParticipant(action, participant);
                })
                .listen('.voice.mute', ({ user_id, is_muted }) => {
                    if (String(this._voiceRoomId || '') !== String(this._roomId || '')) return;
                    this._applyVoiceParticipant('updated', { id: user_id, is_muted });
                });
        },

        _initYT() {
            /* YouTube postMessage events (ended, error) */
            window.addEventListener('message', (ev) => {
                try {
                    const d = JSON.parse(ev.data);
                    /* Primary: onStateChange with info=0 means video ended */
                    if (d.event === 'onStateChange' && d.info === 0)
                        this._onVideoEnded();
                    /* infoDelivery: captures responses from getDuration/getCurrentTime polls */
                    if (d.event === 'infoDelivery' && d.info) {
                        const info = d.info;
                        if (info.playerState === 0) this._onVideoEnded();

                        /* Store currentTime from periodic poll */
                        if (typeof info.currentTime === 'number' && info.currentTime > 0)
                            this._lastCurrentTime = info.currentTime;

                        /* Capture duration — works even without YouTube API key */
                        if (typeof info.duration === 'number' && info.duration > 0 && this.musicState.video_id) {
                            if (info.duration !== this._knownDuration) {
                                this._knownDuration = info.duration;
                                /* Set end timer if not already set */
                                if (!this._endTimer && this.musicState.is_playing && this.musicState.started_at_unix) {
                                    const ms = (this.musicState.started_at_unix + info.duration) * 1000 - Date.now();
                                    if (ms > 0 && ms < 10800000)
                                        this._endTimer = setTimeout(() => { this._endTimer = null; this._onVideoEnded(); }, ms + 2000);
                                }
                            }
                            /* Direct: currentTime reached end */
                            if (this._lastCurrentTime > 0 && this._lastCurrentTime >= info.duration - 2)
                                this._onVideoEnded();
                        }
                    }
                    if (d.event === 'onError') {
                        const msg = (d.info === 101 || d.info === 150)
                            ? 'Bu video embed edilemiyor — başka video deneyin'
                            : `YouTube hatası: ${d.info}`;
                        showToast(msg, 'error');
                    }
                } catch(e) {}
            });

            /* Periodic poll: ask YouTube player for currentTime+duration every 8s.
               This captures duration without API key AND detects end via currentTime. */
            this._ytPollTimer = setInterval(() => {
                if (this._ytIframe && this.musicState.video_id && this.musicState.is_playing) {
                    this._ytCmd('getDuration');
                    this._ytCmd('getCurrentTime');
                }
            }, 8000);

            /* Initial state fetch */
            this._syncMusic();
        },

        async _syncMusic() {
            try {
                const r = await fetch(`/api/music/${ROOM_ID}`);
                if (!r.ok) return;
                const state = await r.json();
                this.musicState = state;
                this._applyMusicState(state);
            } catch(e) {}
        },

        /* Calculate current playback position.
         * Mirrors the server formula: floor(now - started_at_unix). */
        _calcPos(state) {
            if (state.is_playing && state.started_at_unix) {
                return Math.max(0, Math.floor(Date.now() / 1000 - state.started_at_unix));
            }
            return Math.max(0, state.position || 0);
        },

        _ytSrc(videoId, startSecs, muted, playing) {
            const m  = muted   ? '&mute=1' : '&mute=0';
            const ap = playing ? '&autoplay=1' : '&autoplay=0';
            return `https://www.youtube.com/embed/${videoId}?enablejsapi=1&rel=0&modestbranding=1&controls=0${ap}${m}&start=${Math.max(0, Math.floor(startSecs))}`;
        },

        _ytLoad(videoId, startSecs, muted, playing) {
            const c = document.getElementById('yt-player');
            if (!c) return;
            c.innerHTML = '';
            const f = document.createElement('iframe');
            f.width  = '200'; f.height = '112';
            f.src    = this._ytSrc(videoId, startSecs, muted, playing);
            f.allow  = 'autoplay; encrypted-media';
            f.setAttribute('frameborder', '0');
            f.style.cssText = 'border:0;width:100%;height:100%;display:block;';
            c.appendChild(f);
            this._ytIframe  = f;
            this._ytCurVid  = videoId;
        },

        /* ── Ensure iframe exists in user gesture context ──
           Creates a blank YouTube embed so the browser associates audio
           permission with it. Subsequent loadVideoById calls inherit this
           permission without needing a new user gesture. */
        _ensureIframeReady() {
            if (this._ytIframe) {
                /* Iframe exists — just unmute it via postMessage (user gesture) */
                this._ytCmd('unMute');
                this._ytCmd('setVolume', [100]);
                return;
            }
            const c = document.getElementById('yt-player');
            if (!c) return;
            c.innerHTML = '';
            const f = document.createElement('iframe');
            f.width = '200'; f.height = '112';
            /* Load minimal embed with autoplay+sound — this "locks" audio permission
               to this iframe element. loadVideoById will reuse the permission. */
            f.src = 'https://www.youtube.com/embed/?enablejsapi=1&autoplay=0&mute=0';
            f.allow = 'autoplay; encrypted-media';
            f.setAttribute('frameborder', '0');
            f.style.cssText = 'border:0;width:100%;height:100%;display:block;';
            c.appendChild(f);
            this._ytIframe = f;
        },

        _ytCmd(func, args) {
            if (!this._ytIframe || !this._ytIframe.contentWindow) return;
            this._ytIframe.contentWindow.postMessage(
                JSON.stringify({ event: 'command', func, args: args ?? '' }), '*'
            );
        },

        /* ── Core sync — called on every push/poll result ── */
        _applyMusicState(state) {
            /* Always clear pending end timer — will be reset below if needed */
            if (this._endTimer) { clearTimeout(this._endTimer); this._endTimer = null; }

            /* Discord-style: music only plays when user is in voice chat */
            if (!this.voiceState.in_voice) {
                if (this._ytIframe) { this._ytCmd('pauseVideo'); }
                this.showMusicUnlockPrompt = false;
                return;
            }

            if (!state.video_id) {
                if (this._ytIframe) { this._ytIframe.src = ''; this._ytIframe = null; }
                this._ytCurVid = ''; this._ytCurPlay = null;
                this._knownDuration = 0; this._lastCurrentTime = 0;
                this._playerMuted = true; /* reset: next track starts muted */
                this.musicPlaybackUnlocked = false;
                this.showMusicUnlockPrompt = false;
                return;
            }

            if (!this.musicPlaybackUnlocked && state.is_playing) {
                this.showMusicUnlockPrompt = true;
                return;
            }

            const pos = this._calcPos(state);

            /* ① New track */
            if (this._ytCurVid !== state.video_id) {
                this._knownDuration = 0; this._lastCurrentTime = 0;
                if (this._ytIframe && !this._playerMuted) {
                    /* CRITICAL: Reuse existing iframe via postMessage API.
                       loadVideoById inherits the audio permission that was
                       granted when the iframe was created in user gesture context.
                       Creating a new iframe here would LOSE audio permission
                       because _applyMusicState runs in async/event context. */
                    this._ytCmd('loadVideoById', [{ videoId: state.video_id, startSeconds: Math.floor(pos) }]);
                    this._ytCmd('unMute');
                    this._ytCmd('setVolume', [100]);
                    if (!state.is_playing) this._ytCmd('pauseVideo');
                    this._ytCurVid = state.video_id;
                } else {
                    /* No iframe yet, or player is muted — safe to create new iframe */
                    const shouldMute = this._playerMuted;
                    this._ytLoad(state.video_id, pos, shouldMute, state.is_playing);
                    this._playerMuted = shouldMute;
                }
                this._ytCurPlay = state.is_playing;
                /* End timer set below */
            }
            /* ② Play state changed */
            else if (state.is_playing !== this._ytCurPlay) {
                this._ytCurPlay = state.is_playing;
                if (state.is_playing) {
                    if (!this._playerMuted && this._ytIframe) {
                        /* Reuse iframe — seek + play preserves audio lock */
                        this._ytCmd('seekTo', [Math.floor(pos), true]);
                        this._ytCmd('playVideo');
                        this._ytCmd('unMute');
                    } else {
                        /* Muted or no iframe: safe to reload for position sync */
                        this._ytLoad(state.video_id, pos, this._playerMuted, true);
                    }
                } else {
                    this._ytCmd('pauseVideo');
                }
            }
            /* ③ Same track, same state — let YouTube play naturally */

            /* Set JS end timer — works even if iframe postMessage doesn't fire */
            const dur = state.video_duration || this._knownDuration;
            if (state.is_playing && state.started_at_unix && dur > 0) {
                const msLeft = (state.started_at_unix + dur) * 1000 - Date.now();
                if (msLeft > 0 && msLeft < 10800000)
                    this._endTimer = setTimeout(() => { this._endTimer = null; this._onVideoEnded(); }, msLeft + 2000);
                else if (msLeft <= 0)
                    this._onVideoEnded();
            }
        },

        /* ── Sesi Aç / Kapat ── */
        muteToggle() {
            if (!this.musicState.video_id) return;
            if (this._playerMuted) {
                this.musicPlaybackUnlocked = true;
                this.showMusicUnlockPrompt = false;
                /* User gesture context (click/tap): reload iframe with sound.
                   This is the ONLY reliable way on iOS Safari — postMessage
                   unMute() is NOT treated as a user gesture inside the iframe.
                   On Chrome, postMessage works, but reload is universally safe. */
                const pos = this._calcPos(this.musicState);
                this._ytLoad(this.musicState.video_id, pos, false, this.musicState.is_playing);
                this._ytCurVid = this.musicState.video_id;
                this._ytCurPlay = this.musicState.is_playing;
                this._playerMuted = false;
                /* Re-set end timer for new iframe */
                if (this._endTimer) { clearTimeout(this._endTimer); this._endTimer = null; }
                const dur = this.musicState.video_duration || this._knownDuration;
                if (this.musicState.is_playing && this.musicState.started_at_unix && dur > 0) {
                    const ms = (this.musicState.started_at_unix + dur) * 1000 - Date.now();
                    if (ms > 0) this._endTimer = setTimeout(() => { this._endTimer = null; this._onVideoEnded(); }, ms + 2000);
                }
            } else {
                this._ytCmd('mute');
                this._playerMuted = true;
            }
        },

        unlockMusicPlayback() {
            if (!this.musicState.video_id) return;
            this.musicPlaybackUnlocked = true;
            this.showMusicUnlockPrompt = false;
            this._playerMuted = true;
            this.muteToggle();
        },

        async _onVideoEnded() {
            /* Debounce: ignore duplicate events within 5 seconds */
            const now = Date.now();
            if (now - this._videoEndedAt < 5000) return;
            this._videoEndedAt = now;
            if (this._endTimer) { clearTimeout(this._endTimer); this._endTimer = null; }
            /* Auto-skip via slash command API */
            try {
                const r = await fetch(`/api/music/${ROOM_ID}/command`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
                    body: JSON.stringify({ command: '/geç' })
                });
                const data = await r.json();
                if (data.state) {
                    this.musicState = data.state;
                    this._applyMusicState(data.state);
                }
            } catch(e) {}
        },

        async startIsimSehirGame() {
            try {
                const r = await fetch(`/rooms/${this._roomId}/games/start`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
                    body: JSON.stringify({ round_time_seconds: 420, categories: ['isim', 'şehir', 'hayvan', 'eşya', 'bitki'] })
                });
                const data = await this._safeJson(r, 'Oyun API');
                if (!r.ok) throw new Error(data.message || 'Oyun başlatılamadı');
                this.gameUrl = normalizeGameUrl(data.redirect);
                this.gameOpen = true;
            } catch (e) {
                showToast(e.message || 'Oyun başlatılamadı', 'error');
            }
        },

        closeIsimSehirGame() {
            this.gameOpen = false;
            this.gameUrl = '';
        },

        /* ── Per-user accent color: stable mapping by userId % palette ──
           Returns { text, bg, border } CSS rgba strings. */
        _userColor(userId) {
            if (!userId) return { text: 'rgba(113,113,122,1)', bg: 'rgba(24,24,27,0.9)', border: 'rgba(255,255,255,0.05)' };
            const p = [
                { text:'rgba(99,102,241,1)',  bg:'rgba(99,102,241,0.1)',  border:'rgba(99,102,241,0.22)'  },
                { text:'rgba(168,85,247,1)',  bg:'rgba(168,85,247,0.1)', border:'rgba(168,85,247,0.22)'  },
                { text:'rgba(236,72,153,1)',  bg:'rgba(236,72,153,0.1)', border:'rgba(236,72,153,0.22)'  },
                { text:'rgba(20,184,166,1)',  bg:'rgba(20,184,166,0.1)', border:'rgba(20,184,166,0.22)'  },
                { text:'rgba(245,158,11,1)',  bg:'rgba(245,158,11,0.1)', border:'rgba(245,158,11,0.22)'  },
                { text:'rgba(59,130,246,1)',  bg:'rgba(59,130,246,0.1)', border:'rgba(59,130,246,0.22)'  },
                { text:'rgba(34,197,94,1)',   bg:'rgba(34,197,94,0.1)',  border:'rgba(34,197,94,0.22)'   },
                { text:'rgba(249,115,22,1)',  bg:'rgba(249,115,22,0.1)', border:'rgba(249,115,22,0.22)'  },
                { text:'rgba(6,182,212,1)',   bg:'rgba(6,182,212,0.1)',  border:'rgba(6,182,212,0.22)'   },
                { text:'rgba(132,204,22,1)',  bg:'rgba(132,204,22,0.1)', border:'rgba(132,204,22,0.22)'  },
            ];
            return p[parseInt(userId) % p.length];
        },

        /* ══════════ VOICE MESSAGE RECORDING ═══════════ */
        async startVoiceRecord() {
            try {
                this._voiceRecStream = await navigator.mediaDevices.getUserMedia({
                    audio: { echoCancellation: true, noiseSuppression: true }, video: false
                });
            } catch(e) { showToast('Mikrofon erişimi reddedildi', 'error'); return; }

            this._voiceChunks = [];
            this._voiceRecordSecs = 0;
            this._voiceRecording = true;

            const mimeType = MediaRecorder.isTypeSupported('audio/webm;codecs=opus') ? 'audio/webm;codecs=opus'
                           : MediaRecorder.isTypeSupported('audio/mp4') ? 'audio/mp4' : '';

            this._voiceMediaRec = new MediaRecorder(this._voiceRecStream, mimeType ? { mimeType } : {});
            this._voiceMediaRec.ondataavailable = (e) => { if (e.data.size > 0) this._voiceChunks.push(e.data); };
            this._voiceMediaRec.start(250);

            this._voiceRecTimer = setInterval(() => {
                this._voiceRecordSecs++;
                if (this._voiceRecordSecs >= 300) this.stopVoiceRecord();
            }, 1000);
        },

        cancelVoiceRecord() {
            clearInterval(this._voiceRecTimer);
            if (this._voiceMediaRec && this._voiceMediaRec.state !== 'inactive') this._voiceMediaRec.stop();
            if (this._voiceRecStream) this._voiceRecStream.getTracks().forEach(t => t.stop());
            this._voiceRecording = false;
            this._voiceRecordSecs = 0;
        },

        async stopVoiceRecord() {
            if (!this._voiceMediaRec || this._voiceMediaRec.state === 'inactive') return;
            const duration = this._voiceRecordSecs;
            clearInterval(this._voiceRecTimer);

            await new Promise(resolve => {
                this._voiceMediaRec.onstop = resolve;
                this._voiceMediaRec.stop();
            });

            if (this._voiceRecStream) this._voiceRecStream.getTracks().forEach(t => t.stop());
            this._voiceRecording = false;
            this._voiceRecordSecs = 0;

            if (this._voiceChunks.length === 0) return;

            const blob = new Blob(this._voiceChunks, { type: this._voiceMediaRec.mimeType || 'audio/webm' });
            const ext = (this._voiceMediaRec.mimeType || '').includes('mp4') ? 'mp4' : 'webm';

            const fd = new FormData();
            fd.append('audio', blob, `voice.${ext}`);
            fd.append('audio_duration', String(duration));
            if (this.replyingTo) fd.append('reply_to', String(this.replyingTo.id));

            this.replyingTo = null;
            this.sending = true;

            try {
                const r = await fetch(`/api/chat/${ROOM_ID}/messages`, {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': CSRF },
                    body: fd,
                });
                if (!r.ok) { showToast('Sesli mesaj gönderilemedi', 'error'); return; }
                const msg = await r.json();
                this.messages.push(msg);
                if (!this.lastMessageAt || msg.created_at > this.lastMessageAt) this.lastMessageAt = msg.created_at;
                this.$nextTick(() => this.scrollToBottom());
            } catch(e) { showToast('Sesli mesaj gönderilemedi', 'error'); }
            finally { this.sending = false; }
        },

        /* ══════════ AUDIO PLAYBACK ═══════════ */
        playAudio(msg) {
            if (this._playingAudioId === msg.id) {
                if (this._audioEl) { this._audioEl.pause(); this._audioEl = null; }
                clearInterval(this._audioProgressTimer);
                this._playingAudioId = null;
                this._audioProgress = 0;
                return;
            }
            if (this._audioEl) { this._audioEl.pause(); }
            clearInterval(this._audioProgressTimer);

            this._playingAudioId = msg.id;
            this._audioProgress = 0;

            const el = new Audio(msg.audio_url);
            this._audioEl = el;
            el.play().catch(() => {});

            this._audioProgressTimer = setInterval(() => {
                if (el.duration && el.duration > 0) {
                    this._audioProgress = Math.min(100, (el.currentTime / el.duration) * 100);
                }
            }, 200);

            el.onended = () => {
                clearInterval(this._audioProgressTimer);
                this._playingAudioId = null;
                this._audioProgress = 0;
                this._audioEl = null;
            };
        },

        /* ══════════ ARCHIVED MESSAGES ═══════════ */
        async toggleArchived() {
            this.showArchived = !this.showArchived;
            if (this.showArchived && this.archivedMessages.length === 0) {
                await this.loadArchived();
            }
        },

        async loadArchived(page = 1) {
            this.archivedLoading = true;
            try {
                const r = await fetch(`/api/chat/${this._roomId}/archived?page=${page}`);
                if (!r.ok) return;
                const data = await r.json();
                if (page === 1) this.archivedMessages = data.messages;
                else this.archivedMessages.push(...data.messages);
                this.archivedPage = page;
                this.archivedHasMore = data.has_more;
                this.archivedCount = data.total;
            } catch(e) {}
            finally { this.archivedLoading = false; }
        },

        /* ══════════ MESSAGE SEEN TRACKING ═══════════ */
	        _startSeenTracking() {
	            this._seenTimer = setInterval(() => {
	                if (this.currentUser?.presence_mode === 'invisible') return;
	                if (isDocumentHidden() || !this.messages.length) return;
	                const unread = this.messages
	                    .filter(m => m.sender?.id !== this.currentUser.id && !m._seen && !m.is_system_message)
	                    .slice(0, 100)
	                    .map(m => m.id);
                if (unread.length === 0) return;

                this.messages.forEach(m => { if (unread.includes(m.id)) m._seen = true; });

                fetch(`/api/chat/${this._roomId}/seen`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF },
                    body: JSON.stringify({ message_ids: unread })
                }).catch(() => {});
            }, 10000);
        },

        /* ══════════ VOICE CHAT (LiveKit) ═══════════ */
        async voiceJoin(options = {}) {
            const isReconnect = !!options.reconnect;

            if (this._voiceJoinInFlight) {
                console.log('[voice] voiceJoin rejected: join already in flight');
                return;
            }
            if ((this.voiceConnectionStatus === 'connecting' || this.voiceConnectionStatus === 'reconnecting') && !isReconnect) {
                console.log('[voice] voiceJoin rejected: status=' + this.voiceConnectionStatus);
                return;
            }
            if (getVoiceRoom() && this.voiceState?.in_voice && !isReconnect) {
                console.log('[voice] voiceJoin rejected: already connected and in_voice');
                return;
            }

            this._voiceJoinInFlight = true;
            this._manualVoiceLeave = false;
            this.voiceConnectionStatus = isReconnect ? 'reconnecting' : 'connecting';
            this._playerMuted = false;
            this._ensureIframeReady();

            try {
                if (!isReconnect) {
                    try {
                        stopPermissionStreamIfAny();
                        KankioVoiceRuntime.permissionStream = await navigator.mediaDevices.getUserMedia({ audio: true });
                    } catch(e) {
                        this.voiceConnectionStatus = 'failed';
                        showToast('Mikrofon erişimi reddedildi', 'error');
                        return;
                    }
                }

                this._voiceRoomId = this._roomId;
                window.KANKIO_ACTIVE_VOICE_ROOM_ID = this._voiceRoomId;
                console.log('[voice] voiceJoin starting. room=' + this._voiceRoomId + ' reconnect=' + isReconnect);
                const r = await fetch(`/api/voice/${this._voiceRoomId}/join`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
                    body: JSON.stringify({ reconnect: isReconnect })
                });
                const state = await this._safeJson(r, 'Ses API');

                if (!r.ok) {
                    this.voiceConnectionStatus = 'failed';
                    showToast(state.error || 'Ses API hatası oluştu', 'error');
                    return;
                }

                if (!state.livekit_url || !state.livekit_token) {
                    this.voiceConnectionStatus = 'failed';
                    showToast('Sunucu LiveKit bilgilerini döndürmedi. .env dosyasını kontrol edin.', 'error');
                    return;
                }

                this._applyVoiceState(state);
                await this._connectLiveKit(state, options);
                this._voiceReconnectAttempts = 0;
                this.voiceConnectionStatus = 'connected';
                console.log('[voice] voiceJoin succeeded. room=' + this._voiceRoomId);
                showToast(isReconnect ? 'Ses kanalına yeniden bağlanıldı' : 'Ses kanalına bağlanıldı', 'success');
                this._startVoicePolling();

                if (this.musicState.video_id) {
                    this._playerMuted = false;
                    this._applyMusicState(this.musicState);
                }
            } catch (error) {
                console.error('[voice] voiceJoin failed:', error);
                this.voiceConnectionStatus = 'failed';
                showToast('Ses kanalına bağlanılamadı: ' + error.message, 'error');
                this._scheduleVoiceReconnect();
            } finally {
                this._voiceJoinInFlight = false;
            }
        },

        async _connectLiveKit(state, options = {}) {
            const Room = LivekitClient.Room;
            const RoomEvent = LivekitClient.RoomEvent;

            const existingRoom = getVoiceRoom();
            if (existingRoom) {
                const shouldDisconnect = options.forceReconnect || existingRoom.state === 'disconnected' || !existingRoom.localParticipant;
                if (shouldDisconnect) {
                    console.log('[voice] _connectLiveKit: disconnecting existing room. state=' + existingRoom.state);
                    try { await existingRoom.disconnect(); } catch(e) {}
                    setVoiceRoom(null);
                } else {
                    console.log('[voice] _connectLiveKit: reusing healthy room. state=' + existingRoom.state);
                    return;
                }
            }

            const room = new Room({ adaptiveStream: true, dynacast: true });
            setVoiceRoom(room);

            room.on(RoomEvent.TrackSubscribed, (track, publication, participant) => {
                if (track.kind !== 'audio') return;
                const el = track.attach();
                el.muted = !!this.voiceState.is_deafened;
                KankioVoiceRuntime.audioEls[participant.identity] = el;
            });

            room.on(RoomEvent.TrackUnsubscribed, (track, publication, participant) => {
                if (track.kind !== 'audio') return;
                track.detach();
                if (KankioVoiceRuntime.audioEls[participant.identity]) {
                    KankioVoiceRuntime.audioEls[participant.identity].remove();
                    delete KankioVoiceRuntime.audioEls[participant.identity];
                }
            });

            room.on(RoomEvent.ActiveSpeakersChanged, (speakers) => {
                const speakingDict = {};
                for (const p of speakers) speakingDict[p.identity] = true;
                this._speakingUsers = speakingDict;
                this._syncLocalSpeaking(!!speakingDict[String(this.currentUser.id)]);
            });

            if (RoomEvent.ConnectionQualityChanged) {
                room.on(RoomEvent.ConnectionQualityChanged, (quality, participant) => {
                    if (participant && participant.identity !== String(this.currentUser.id)) return;
                    this._sendVoiceQuality(this._mapLiveKitQuality(quality));
                });
            }

            if (RoomEvent.Reconnecting) {
                room.on(RoomEvent.Reconnecting, () => {
                    this.voiceConnectionStatus = 'reconnecting';
                    this._sendVoiceQuality('poor', true);
                });
            }

            if (RoomEvent.Reconnected) {
                room.on(RoomEvent.Reconnected, () => {
                    this.voiceConnectionStatus = 'connected';
                    this._sendVoiceQuality('good', true);
                });
            }

            room.on(RoomEvent.Disconnected, () => {
                if (this._manualVoiceLeave) return;
                this.voiceConnectionStatus = 'reconnecting';
                this._scheduleVoiceReconnect();
            });

            await room.connect(state.livekit_url, state.livekit_token);
            await this._applyLocalMicState();
        },

        async voiceLeave() {
            this._manualVoiceLeave = true;
            clearInterval(this._voiceTimer);
            clearTimeout(this._voiceReconnectTimer);
            await this._disconnectLocalVoice(true);
            this.voiceConnectionStatus = 'idle';
            this.voiceState = {
                in_voice: false, is_muted: false, is_deafened: false, can_speak: true,
                connection_quality: 'unknown', reconnect_count: 0, can_moderate: false,
                settings: {}, participants: []
            };
            if (this._ytIframe) this._ytCmd('pauseVideo');
            this._playerMuted = true;
            KankioVoiceRuntime.lastAppliedMicEnabled = null;
        },

        async _disconnectLocalVoice(notifyServer) {
            this._speakingUsers = {};

            const room = getVoiceRoom();
            if (room) {
                try {
                    const localParticipant = room.localParticipant;
                    if (localParticipant) {
                        try {
                            await localParticipant.setMicrophoneEnabled(false);
                        } catch (e) {
                            console.warn('[voice] failed to disable microphone before disconnect', e);
                        }
                        const trackPubs = Array.from(localParticipant.trackPublications.values?.() || []);
                        for (const pub of trackPubs) {
                            try {
                                if (pub?.kind === 'audio') {
                                    const track = pub.track;
                                    try { await localParticipant.unpublishTrack(track); } catch (e) {}
                                    try { track?.stop?.(); } catch (e) {}
                                }
                            } catch (e) {}
                        }
                    }
                    await room.disconnect();
                } catch (e) {
                    console.warn('[voice] room disconnect failed', e);
                }
            }

            for (const uid in KankioVoiceRuntime.audioEls) {
                if (KankioVoiceRuntime.audioEls[uid]) {
                    try { KankioVoiceRuntime.audioEls[uid].remove(); } catch (e) {}
                }
            }
            KankioVoiceRuntime.audioEls = {};

            if (KankioVoiceRuntime.permissionStream) {
                for (const track of KankioVoiceRuntime.permissionStream.getTracks()) {
                    try { track.stop(); } catch (e) {
                        console.warn('[voice] failed to stop permission stream track', e);
                    }
                }
                KankioVoiceRuntime.permissionStream = null;
            }

            resetVoiceRuntime();
            setVoiceRoom(null);

            if (notifyServer && this._voiceRoomId) {
                fetch(`/api/voice/${this._voiceRoomId}/leave`, {
                    method: 'POST', headers: { 'X-CSRF-TOKEN': CSRF }
                }).catch(() => {});
            }
            this._voiceRoomId = null;
            window.KANKIO_ACTIVE_VOICE_ROOM_ID = null;
        },

        async voiceToggleMute(force = null) {
            if (!this._voiceRoomId) return;
            const newMuted = force === null ? !this.voiceState.is_muted : !!force;
            if (!this.voiceState.can_speak && !newMuted) {
                showToast('Bu odada konuşma izniniz yok', 'error');
                return;
            }
            await this._setLocalMicMuted(newMuted);
            try {
                const r = await fetch(`/api/voice/${this._voiceRoomId}/mute`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
                    body: JSON.stringify({ is_muted: newMuted })
                });
                const data = await this._safeJson(r, 'Ses API');
                if (!r.ok) { showToast(data.error || 'Mikrofon durumu güncellenemedi', 'error'); return; }
                this._applyVoiceState({ is_muted: data.is_muted, participant: data.participant });
            } catch(e) { showToast('Mikrofon durumu güncellenemedi', 'error'); }
        },

        async voiceToggleDeafen() {
            if (!this._voiceRoomId) return;
            const newDeafen = !this.voiceState.is_deafened;
            this._setRemoteAudioMuted(newDeafen);
            try {
                const r = await fetch(`/api/voice/${this._voiceRoomId}/deafen`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
                    body: JSON.stringify({ is_deafened: newDeafen })
                });
                const data = await this._safeJson(r, 'Ses API');
                if (!r.ok) { showToast(data.error || 'Kulaklık durumu güncellenemedi', 'error'); return; }
                this._applyVoiceState({ is_deafened: data.is_deafened, participant: data.participant });
            } catch(e) { showToast('Kulaklık durumu güncellenemedi', 'error'); }
        },

        async voiceMuteAll() {
            if (!this.voiceState.can_moderate || !this._voiceRoomId) return;
            if (!confirm('Ses kanalındaki herkesi susturmak istiyor musunuz?')) return;
            await fetch(`/api/voice/${this._voiceRoomId}/mute-all`, {
                method: 'POST', headers: { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' }
            }).catch(() => {});
        },

        async voiceKick(participant) {
            if (!this.voiceState.can_moderate || !this._voiceRoomId || !participant?.id) return;
            if (!confirm(`${participant.username || 'Kullanıcı'} ses kanalından atılsın mı?`)) return;
            try {
                const r = await fetch(`/api/voice/${this._voiceRoomId}/participants/${participant.id}`, {
                    method: 'DELETE',
                    headers: { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' }
                });
                const data = await this._safeJson(r, 'Ses API');
                if (!r.ok) showToast(data.error || 'Kullanıcı atılamadı', 'error');
            } catch(e) { showToast('Kullanıcı atılamadı', 'error'); }
        },

        async voiceSetSpeakPermission(participant, canSpeak) {
            if (!this.voiceState.can_moderate || !this._voiceRoomId || !participant?.id) return;
            try {
                const r = await fetch(`/api/voice/${this._voiceRoomId}/participants/${participant.id}/speak`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
                    body: JSON.stringify({ can_speak: !!canSpeak })
                });
                const data = await this._safeJson(r, 'Ses API');
                if (!r.ok) { showToast(data.error || 'Konuşma izni güncellenemedi', 'error'); return; }
                this._applyVoiceParticipant('updated', data.participant);
            } catch(e) { showToast('Konuşma izni güncellenemedi', 'error'); }
        },

        async voiceUpdateSettings(patch) {
            if (!this.voiceState.can_moderate || !this._voiceRoomId) return;
            try {
                const r = await fetch(`/api/voice/${this._voiceRoomId}/settings`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
                    body: JSON.stringify(patch)
                });
                const data = await this._safeJson(r, 'Ses API');
                if (!r.ok) { showToast(data.error || 'Ses ayarı güncellenemedi', 'error'); return; }
                this.voiceState.settings = { ...this.voiceState.settings, ...(data.settings || patch) };
            } catch(e) { showToast('Ses ayarı güncellenemedi', 'error'); }
        },

        _startVoicePolling() {
            clearInterval(this._voiceTimer);
            this._voiceTimer = setInterval(async () => {
                if (!this.voiceState.in_voice || !this._voiceRoomId) return;
                try {
                    const sr = await fetch(`/api/voice/${this._voiceRoomId}/state`, { headers: { 'Accept': 'application/json' } });
                    const state = await this._safeJson(sr, 'Ses API');
                    if (!sr.ok) return;
                    if (!state.in_voice && this.voiceState.in_voice) {
                        showToast('Ses kanalından çıkarıldınız', 'error');
                        await this._disconnectLocalVoice(false);
                        this.voiceConnectionStatus = 'idle';
                    }
                    this._applyVoiceState(state);
                } catch(e) {}
            }, 3000);
        },

        _applyVoiceState(state) {
            if (!state) return;
            const token = this.voiceState.livekit_token;
            const url = this.voiceState.livekit_url;
            this.voiceState = { ...this.voiceState, ...state, livekit_token: state.livekit_token || token, livekit_url: state.livekit_url || url };
            if (state.participant) this._applyVoiceParticipant('updated', state.participant);
            this._refreshSpeakingUsers();
            this._applyLocalMicState();
            this._setRemoteAudioMuted(!!this.voiceState.is_deafened);
        },

        isViewingVoiceRoom() {
            return String(this._voiceRoomId || '') === String(this._roomId || '');
        },

        voiceParticipants() {
            if (!this.isViewingVoiceRoom()) return [];
            return this.voiceState.participants || [];
        },

        voiceParticipantCount() {
            return this.voiceParticipants().length;
        },

        _applyVoiceParticipant(action, participant) {
            if (!participant || !participant.id) return;
            if (action === 'kicked' && String(participant.id) === String(this.currentUser.id)) {
                showToast('Ses kanalından çıkarıldınız', 'error');
                this.voiceLeave();
                return;
            }
            const list = [...(this.voiceState.participants || [])];
            const idx = list.findIndex(p => String(p.id) === String(participant.id));
            if (action === 'kicked') {
                this.voiceState.participants = list.filter(p => String(p.id) !== String(participant.id));
                return;
            }
            if (idx === -1) list.push(participant);
            else list[idx] = { ...list[idx], ...participant };
            this.voiceState.participants = list;
            if (String(participant.id) === String(this.currentUser.id)) {
                this.voiceState = { ...this.voiceState, ...participant, in_voice: true };
                this._applyLocalMicState();
            }
            this._refreshSpeakingUsers();
        },

        _refreshSpeakingUsers() {
            const speaking = {};
            for (const p of (this.voiceState.participants || [])) {
                if (p.is_speaking) speaking[p.id] = true;
            }
            this._speakingUsers = { ...speaking, ...this._speakingUsers };
        },

        async _applyLocalMicState() {
            const room = getVoiceRoom();
            if (!room?.localParticipant) {
                console.log('[voice] _applyLocalMicState skipped: no localParticipant');
                return;
            }
            const shouldMute = !!this.voiceState.is_muted || !this.voiceState.can_speak || (this.voicePrefs.pushToTalk && !this._pttDown);
            if (KankioVoiceRuntime.lastAppliedMicEnabled === shouldMute) {
                console.log('[voice] _applyLocalMicState dedup: already ' + shouldMute);
                return;
            }
            KankioVoiceRuntime.lastAppliedMicEnabled = shouldMute;
            await this._setLocalMicMuted(shouldMute, false);
        },

        async _setLocalMicMuted(muted, updateState = true) {
            if (updateState) this.voiceState.is_muted = muted;
            const room = getVoiceRoom();
            const localParticipant = room?.localParticipant;
            if (!localParticipant) {
                console.log('[voice] _setLocalMicMuted skipped: no localParticipant');
                return;
            }
            if (KankioVoiceRuntime.micToggleInFlight) {
                console.log('[voice] _setLocalMicMuted skipped: toggle already in flight');
                return;
            }
            KankioVoiceRuntime.micToggleInFlight = true;
            try {
                console.log('[voice] _setLocalMicMuted: enabled=' + !muted);
                await localParticipant.setMicrophoneEnabled(!muted, {
                    echoCancellation: !!this.voicePrefs.echoCancellation,
                    noiseSuppression: !!this.voicePrefs.noiseSuppression,
                    autoGainControl: true,
                }, {
                    dtx: !!this.voicePrefs.lowBandwidth,
                    audioBitrate: this.voicePrefs.lowBandwidth ? 16000 : 32000,
                });
            } catch(e) {
                console.warn('[voice] _setLocalMicMuted error:', e);
                KankioVoiceRuntime.lastAppliedMicEnabled = null; /* allow retry on failure */
            } finally {
                KankioVoiceRuntime.micToggleInFlight = false;
            }
        },

        _setRemoteAudioMuted(muted) {
            this.voiceState.is_deafened = muted;
            for (const uid in KankioVoiceRuntime.audioEls) {
                KankioVoiceRuntime.audioEls[uid].muted = muted;
            }
        },

        _scheduleVoiceReconnect() {
            if (this._manualVoiceLeave || !this._voiceRoomId) return;
            clearTimeout(this._voiceReconnectTimer);
            const delay = Math.min(15000, 1000 * Math.pow(2, this._voiceReconnectAttempts++));
            this.voiceConnectionStatus = 'reconnecting';
            console.log('[voice] _scheduleVoiceReconnect: attempt=' + this._voiceReconnectAttempts + ' delay=' + delay + 'ms');
            this._voiceReconnectTimer = setTimeout(() => this.voiceJoin({ reconnect: true }), delay);
        },

        async _syncLocalSpeaking(isSpeaking) {
            if (!this._voiceRoomId || this.voiceState._lastSpeaking === isSpeaking) return;
            this.voiceState._lastSpeaking = isSpeaking;
            fetch(`/api/voice/${this._voiceRoomId}/speaking`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF },
                body: JSON.stringify({ is_speaking: isSpeaking })
            }).catch(() => {});
        },

        _sendVoiceQuality(quality, reconnected = false) {
            if (!this._voiceRoomId) return;
            fetch(`/api/voice/${this._voiceRoomId}/quality`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF },
                body: JSON.stringify({ connection_quality: quality, reconnected })
            }).catch(() => {});
        },

        _mapLiveKitQuality(quality) {
            const raw = String(quality || '').toLowerCase();
            if (raw.includes('excellent')) return 'excellent';
            if (raw.includes('good')) return 'good';
            if (raw.includes('poor') || raw.includes('lost')) return 'poor';
            if (quality === 3) return 'excellent';
            if (quality === 2) return 'good';
            if (quality === 1) return 'poor';
            return 'unknown';
        },

        qualityLabel(q) {
            return ({ excellent: 'Çok iyi', good: 'İyi', poor: 'Zayıf', unknown: 'Bilinmiyor' })[q || 'unknown'] || 'Bilinmiyor';
        },

        _initPushToTalk() {
            window.addEventListener('keydown', (e) => {
                if (!this.voicePrefs.pushToTalk || e.code !== 'Space' || this._pttDown) return;
                if (['INPUT','TEXTAREA'].includes(document.activeElement?.tagName)) return;
                this._pttDown = true;
                this._applyLocalMicState();
                e.preventDefault();
            });
            window.addEventListener('keyup', (e) => {
                if (!this.voicePrefs.pushToTalk || e.code !== 'Space') return;
                this._pttDown = false;
                this._applyLocalMicState();
                e.preventDefault();
            });
        },

        async _safeJson(response, label) {
            return safeJson(response, label);
        }
    }
}

window.__kankioChatRoomReady = true;
window.__kankioLoadAlpine?.();
