<!DOCTYPE html>
<html lang="tr" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, interactive-widget=resizes-content">
    <title>Kankio</title>
    <meta name="theme-color" content="#09090b">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <!-- PWA -->
    <link rel="manifest" href="{{ url('manifest.json') }}">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="Kankio">
    <link rel="apple-touch-icon" href="{{ url('icons/icon.svg') }}">
    @vite('resources/css/app.css')
    <script src="https://cdn.jsdelivr.net/npm/pusher-js@8.4.0/dist/web/pusher.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/laravel-echo@1.16.1/dist/echo.iife.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/livekit-client/dist/livekit-client.umd.min.js"></script>
    @php
        $broadcastConfig = [
            'reverb' => [
                'key' => config('broadcasting.connections.reverb.key'),
                'host' => config('broadcasting.connections.reverb.options.host'),
                'port' => config('broadcasting.connections.reverb.options.port'),
                'scheme' => config('broadcasting.connections.reverb.options.scheme'),
            ],
            'pusher' => [
                'key' => config('broadcasting.connections.pusher.key'),
                'cluster' => config('broadcasting.connections.pusher.options.cluster'),
                'scheme' => config('broadcasting.connections.pusher.options.scheme') ?: 'https',
            ],
        ];
    @endphp
    <script>
        window.KANKIO_BROADCAST_DRIVER = @json(config('broadcasting.default'));
        window.KANKIO_BROADCAST_CONFIG = @json($broadcastConfig);
        window.__kankioLoadAlpine = () => {
            if (window.__kankioAlpineLoading || window.Alpine) return;
            window.__kankioAlpineLoading = true;
            const script = document.createElement('script');
            script.defer = true;
            script.src = 'https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js';
            document.head.appendChild(script);
        };
    </script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=EB+Garamond:ital,wght@0,400;0,300;1,400&display=swap');
        .font-serif { font-family: 'EB Garamond', Georgia, serif; }
        html, body { height: 100dvh; width: 100%; overflow: hidden; background: #000; color: #fff; }
        ::-webkit-scrollbar { width: 5px; height: 5px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: #27272a; border-radius: 9999px; }
        ::-webkit-scrollbar-thumb:hover { background: #3f3f46; }
        .scrollable { overflow-y: auto; overscroll-behavior-y: contain; -webkit-overflow-scrolling: touch; }
        @keyframes pulseSlow { 0%,100%{opacity:0.3}50%{opacity:0.5} }
        .animate-pulse-slow { animation: pulseSlow 6s ease-in-out infinite; }
        @keyframes speakPulse { 0%,100%{box-shadow:0 0 0 2px rgba(52,211,153,0.5)} 50%{box-shadow:0 0 0 5px rgba(52,211,153,0.15)} }
        /* ── Equalizer bars animation ── */
        @keyframes eq1 { 0%,100%{height:30%} 50%{height:100%} }
        @keyframes eq2 { 0%,100%{height:90%} 50%{height:25%} }
        /* ── PWA Native feel ── */
        html { overscroll-behavior: none; }
        * { -webkit-tap-highlight-color: transparent; touch-action: manipulation; }
        button:active, [role=button]:active { opacity: 0.75; transform: scale(0.95); transition: transform 0.08s, opacity 0.08s; }
        /* ── Safe-area utilities ── */
        .safe-t  { padding-top:    env(safe-area-inset-top,    0px); }
        .safe-b  { padding-bottom: env(safe-area-inset-bottom, 0px); }
        .safe-l  { padding-left:   env(safe-area-inset-left,   0px); }
        .safe-r  { padding-right:  env(safe-area-inset-right,  0px); }
        /* ── Slide-up modal ── */
        .slide-up-enter  { animation: slideUp   0.32s cubic-bezier(0.32,0.72,0,1) forwards; }
        .slide-up-leave  { animation: slideDown 0.28s cubic-bezier(0.32,0.72,0,1) forwards; }
        @keyframes slideUp   { from{transform:translateY(100%)} to{transform:translateY(0)} }
        @keyframes slideDown { from{transform:translateY(0)} to{transform:translateY(100%)} }
        @keyframes slideInLeft { from{transform:translateX(-100%)}to{transform:translateX(0)} }
        @keyframes slideInRight { from{transform:translateX(100%)}to{transform:translateX(0)} }
        /* Chat input bottom clearance — dynamic via Alpine binding on mobile */
        .chat-input-pb { padding-bottom: 1.25rem; }
        .slide-left { animation: slideInLeft 0.3s ease-out; }
        .slide-right { animation: slideInRight 0.3s ease-out; }
        [x-cloak] { display: none !important; }
        .glass-drawer {
            position: fixed; top: 0; left: 0; bottom: 0;
            width: 320px; z-index: 50;
            background: rgba(0,0,0,0.85);
            backdrop-filter: blur(24px);
            border-right: 1px solid rgba(255,255,255,0.05);
        }
        .drawer-overlay {
            position: fixed; inset: 0; z-index: 49;
            background: rgba(0,0,0,0.5);
            backdrop-filter: blur(4px);
        }
        @media(min-width:1024px) {
            .glass-drawer { position: relative; animation: none; }
            .drawer-overlay { display: none; }
        }
    </style>
</head>
<body class="relative flex h-screen w-full overflow-hidden bg-black text-white"
      x-data="chatLayout()" x-init="init()"
      @toggle-left-sidebar.window="toggleLeft()"
      @open-stay-connected.window="stayOpen=true">

    <!-- ── Admin Duyuru Banneri ────────────────────────────────────────── -->
    @php $ann = \App\Models\Announcement::active(); @endphp
    @if($ann)
    <div x-data="{ dismissed: localStorage.getItem('ann_dismissed') === '{{ $ann->id }}' }"
         x-show="!dismissed" x-cloak
         class="fixed top-0 inset-x-0 z-[999] flex items-center gap-3 px-4 py-2.5 text-sm font-medium"
         style="{{ $ann->type === 'danger'  ? 'background:rgba(220,38,38,0.92);color:#fff'  :
                  ($ann->type === 'warning' ? 'background:rgba(217,119,6,0.92);color:#fff'  :
                                              'background:rgba(37,99,235,0.92);color:#fff') }};backdrop-filter:blur(8px)">
        <span class="shrink-0 text-base">{{ $ann->type === 'danger' ? '🚨' : ($ann->type === 'warning' ? '⚠️' : 'ℹ️') }}</span>
        <span class="flex-1 text-center">{{ $ann->message }}</span>
        <button @click="dismissed=true; localStorage.setItem('ann_dismissed','{{ $ann->id }}')"
                class="shrink-0 opacity-70 hover:opacity-100 transition-opacity text-lg leading-none"
                title="Kapat">×</button>
    </div>
    @endif

    <!-- Ambient Background -->
    <div class="fixed inset-0 pointer-events-none z-0">
        <div class="absolute w-[600px] h-[600px] rounded-full blur-[150px] animate-pulse-slow"
             style="top:-10%;left:-10%;background:rgba(136,19,55,0.07)"></div>
        <div class="absolute w-[600px] h-[600px] rounded-full blur-[150px] animate-pulse-slow"
             style="bottom:-10%;right:-10%;background:rgba(49,46,129,0.07)"></div>
    </div>

    <!-- Left Sidebar (Desktop: permanent, Mobile: drawer) -->
    <!-- Mobile Overlay -->
    <div x-show="leftOpen && isMobile" x-cloak @click="leftOpen=false"
         class="drawer-overlay" x-transition:enter="transition-opacity duration-300"
         x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
         x-transition:leave="transition-opacity duration-300"
         x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"></div>

    <!-- Sidebar Panel -->
    <div x-show="leftOpen" x-cloak
         :class="isMobile ? 'glass-drawer' : 'relative w-80 h-full shrink-0 border-r z-10 bg-transparent flex flex-col'"
         style="border-color:rgba(255,255,255,0.05)"
         x-transition:enter="transition-transform duration-300"
         x-transition:enter-start="-translate-x-full" x-transition:enter-end="translate-x-0"
         x-transition:leave="transition-transform duration-300"
         x-transition:leave-start="translate-x-0" x-transition:leave-end="-translate-x-full">

        @include('partials.room-list')
    </div>

    <!-- Main Content -->
    <main class="relative z-10 flex-1 flex flex-col min-w-0 h-full w-full bg-transparent">
        @yield('chat-content')
    </main>

    <!-- Bağlantıda Kal Overlay (keeps chat/voice document alive) -->
    <div x-show="stayOpen" x-cloak
         class="fixed inset-0 z-[9800] bg-black"
         x-transition.opacity>
        <div class="absolute top-4 left-4 right-4 z-10 flex items-center justify-between pointer-events-none">
            <button @click="stayOpen=false"
                    class="pointer-events-auto flex items-center gap-2 rounded-full px-4 py-2 text-xs font-medium tracking-[0.16em] uppercase transition-all"
                    style="background:rgba(9,9,11,0.78);border:1px solid rgba(255,255,255,0.12);color:rgba(255,255,255,0.72);backdrop-filter:blur(16px)">
                <span style="font-size:1rem;line-height:1">&larr;</span>
                Sohbete Dön
            </button>
        </div>
        <template x-if="stayOpen">
            <iframe src="{{ route('stay.connected', ['embedded' => 1]) }}"
                    title="Bağlantıda Kal"
                    class="h-full w-full border-0"
                    style="background:#030303"></iframe>
        </template>
    </div>

    <!-- Profile Modal -->
    @include('partials.profile-modal')

    <!-- Create Room Modal -->
    @include('partials.create-room-modal')

    <!-- Toast Container -->
    <div id="toast-container" class="fixed bottom-6 right-6 z-[100] space-y-2 pointer-events-none"></div>

    <!-- ── Offline Banner ── -->
    <div id="offline-banner"
         style="display:none;position:fixed;top:0;left:0;right:0;z-index:9999;
                background:rgba(239,68,68,0.15);backdrop-filter:blur(12px);
                border-bottom:1px solid rgba(239,68,68,0.3);
                padding:0.5rem 1rem;text-align:center;
                color:rgba(252,165,165,1);font-size:0.75rem;letter-spacing:0.05em;">
        ⚠ İnternet bağlantısı yok — bağlantı bekleniyor
        <div style="display:inline-block;margin-left:8px;">
            <span style="animation:dot 1.4s ease-in-out infinite 0s;opacity:0.3">●</span>
            <span style="animation:dot 1.4s ease-in-out infinite 0.2s;opacity:0.3">●</span>
            <span style="animation:dot 1.4s ease-in-out infinite 0.4s;opacity:0.3">●</span>
        </div>
    </div>

    <!-- iOS Safari: Add-to-Home-Screen tip (still shown as overlay) -->
    <div x-data="{}" x-show="$store.pwa && $store.pwa.showIosTip" x-cloak x-transition
         style="position:fixed;bottom:2rem;left:50%;transform:translateX(-50%);
                z-index:200;width:min(22rem,calc(100vw - 2rem));">
        <div class="rounded-2xl p-4 space-y-2"
             style="background:rgba(9,9,11,0.97);border:1px solid rgba(52,211,153,0.3);
                    backdrop-filter:blur(20px);box-shadow:0 20px 60px rgba(0,0,0,0.6);">
            <div class="flex items-start justify-between gap-3">
                <div class="flex items-center gap-2">
                    <img src="{{ url('icons/icon.svg') }}" class="h-8 w-8 rounded-xl" alt="Kankio">
                    <div>
                        <p class="text-sm font-medium text-white">Kankio'yu Yükle</p>
                        <p class="text-[10px] text-zinc-500">Ana ekrana ekle</p>
                    </div>
                </div>
                <button @click="$store.pwa.showIosTip=false" class="text-zinc-500 hover:text-zinc-300 mt-0.5">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>
            <p class="text-xs text-zinc-400 leading-relaxed">
                Safari'de <strong class="text-zinc-300">Paylaş</strong> butonuna dokun,
                ardından <strong class="text-white">"Ana Ekrana Ekle"</strong> seç.
            </p>
        </div>
    </div>

    @stack('scripts')

    <script>
    const CSRF    = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
    const APP_URL = '{{ rtrim(url('/'), '/') }}';
    window.KANKIO_VAPID_PUBLIC_KEY = @json(config('services.webpush.public_key'));

    /* ── Alpine Store (shared reactive state between components) ── */
    document.addEventListener('alpine:init', () => {
        Alpine.store('chat', {
            onlineUsers  : [],
            unreadCounts : {},
            currentUser  : @json(auth()->user()),
            activeRoomId : '{{ request()->route("roomId") ?? "" }}',
        });

        Alpine.store('lightbox', { src: null, name: null });
    });

    /* ── Expose chatLayout for backward-compat ── */
    document.addEventListener('DOMContentLoaded', () => {
        setTimeout(() => {
            const el = document.querySelector('[x-data="chatLayout()"]');
            if (el) window._chatLayout = Alpine.$data ? Alpine.$data(el) : (el._x_dataStack && el._x_dataStack[0]);
        }, 500);
    });

    /* ── PWA: Service Worker + Install Prompt (skip in Tauri desktop) ── */
    if ('serviceWorker' in navigator && !window.__KANKIO_DESKTOP__) {
        window.addEventListener('load', () => {
            window.KANKIO_SW_REG_PROMISE = navigator.serviceWorker.register('/sw.js', {
                scope: '/'
            })
            .then(reg => {
                /* Check for SW updates every 60 min */
                setInterval(() => reg.update(), 60 * 60 * 1000);
                return reg;
            })
            .catch(() => null);
        });
    }

    /* ── Offline / Online banner ── */
    (function() {
        const banner = document.getElementById('offline-banner');
        const show   = () => { if (banner) banner.style.display = 'block'; };
        const hide   = () => { if (banner) banner.style.display = 'none';  };
        window.addEventListener('offline', show);
        window.addEventListener('online',  hide);
        if (!navigator.onLine) show();
    })();

    /* ── PWA state as Alpine store — accessible by sidebar and overlays ── */
    document.addEventListener('alpine:init', () => {
        Alpine.store('pwa', {
            canInstall: false,
            showIosTip: false,
            _prompt:    null,

            init() {
                const ua           = navigator.userAgent.toLowerCase();
                const isIos        = /iphone|ipad|ipod/.test(ua) && !window.MSStream;
                const isStandalone = window.navigator.standalone ||
                    window.matchMedia('(display-mode: standalone)').matches;

                if (isIos && !isStandalone) {
                    const key = 'kankio_ios_tip_shown';
                    if (!sessionStorage.getItem(key)) {
                        setTimeout(() => { this.showIosTip = true; }, 4000);
                        sessionStorage.setItem(key, '1');
                    }
                }

                if (!window.__KANKIO_DESKTOP__) {
                    window.addEventListener('beforeinstallprompt', (e) => {
                        e.preventDefault();
                        this._prompt    = e;
                        this.canInstall = true;
                    });
                }

                window.addEventListener('appinstalled', () => {
                    this.canInstall = false;
                    this._prompt    = null;
                    showToast('Kankio uygulama olarak yüklendi! 🎉', 'success');
                });
            },

            async install() {
                if (!this._prompt) return;
                this._prompt.prompt();
                const { outcome } = await this._prompt.userChoice;
                this._prompt = null;
                if (outcome === 'accepted') this.canInstall = false;
            },
        });

        Alpine.store('pwa').init();
    });

    function showToast(msg, type = 'success') {
        const el = document.createElement('div');
        el.className = `px-4 py-3 rounded-xl text-sm font-medium shadow-2xl pointer-events-auto transition-all duration-300 border ${
            type === 'success'
                ? 'bg-zinc-900/95 border-emerald-500/30 text-emerald-400'
                : 'bg-zinc-900/95 border-rose-500/30 text-rose-400'
        }`;
        el.textContent = msg;
        el.style.backdropFilter = 'blur(12px)';
        document.getElementById('toast-container').appendChild(el);
        setTimeout(() => { el.style.opacity = '0'; setTimeout(() => el.remove(), 300); }, 3000);
    }

    function chatLayout() {
        return {
            leftOpen: window.innerWidth >= 1024,
            stayOpen: false,
            isMobile: window.innerWidth < 1024,
            currentUser: @json(auth()->user()),
            onlineUsers: [],
            presenceInterval: null,

            init() {
                window.addEventListener('resize', () => {
                    this.isMobile = window.innerWidth < 1024;
                    if (!this.isMobile) this.leftOpen = true;
                });
                this.startPresence();
            },

            toggleLeft() { this.leftOpen = !this.leftOpen; },

            async startPresence() {
                const update = async () => {
                    if (document.hidden) return; /* skip when tab not visible */
                    try {
                        const r = await fetch(`/api/presence`, {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF },
                            body: JSON.stringify({
                                status: Alpine.store('chat').currentUser?.presence_mode === 'invisible' ? 'offline' : 'online'
                            })
                        });
	                        const data = await r.json();
	                        this.onlineUsers = data.users || [];
	                        Alpine.store('chat').onlineUsers = data.users || [];
                    } catch(e) {}
                };

                const goOffline = () => {
                    /* sendBeacon is guaranteed to fire even on page unload */
                    const body = new FormData();
                    body.append('_token', CSRF);
                    body.append('status', 'offline');
                    navigator.sendBeacon(`/api/presence`, body);
                };

	                /* When tab becomes visible again, immediately refresh */
	                document.addEventListener('visibilitychange', () => {
	                    if (!document.hidden) update();
	                    else goOffline();
	                });
	                window.addEventListener('presence-mode-changed', () => update());

                /* Immediate offline on tab/window close */
                window.addEventListener('beforeunload', goOffline);

                update();
                /* Ping every 15s — stale timeout is 60s on server side */
                this.presenceInterval = setInterval(update, 15000);
            },

            async deleteRoom(roomId) {
                if (!confirm('Bu odayı kalıcı olarak silmek istediğinize emin misiniz?')) return;
                try {
                    await fetch(`/api/rooms/${roomId}`, {
                        method: 'DELETE',
                        headers: { 'X-CSRF-TOKEN': CSRF }
                    });
                    showToast('Oda silindi');
                    window.location.href = `${APP_URL}/chat`;
                } catch(e) { showToast('Oda silinemedi', 'error'); }
            }
        }
    }
    if (!window.KANKIO_CHAT_BOOTSTRAP && !window.ISIM_SEHIR_BOOTSTRAP) {
        window.__kankioLoadAlpine?.();
    }
    </script>
</body>
</html>
