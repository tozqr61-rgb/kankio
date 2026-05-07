/* ═══════════════════════════════════════════════════════════════
 * Kankio Service Worker
 * Strategies:
 *   • BYPASS   — API, WebSocket, broadcasting auth, non-GET
 *   • CACHE FIRST  — CDN static assets (JS, CSS, fonts, images)
 *   • NETWORK FIRST — App pages (HTML), with offline fallback
 * ═══════════════════════════════════════════════════════════════ */

const CACHE_VERSION = 'kankio-v3';
const OFFLINE_URL   = 'offline';        /* relative to SW scope */

/* ── Patterns that must NEVER be intercepted ── */
const BYPASS_PATTERNS = [
    /\/broadcasting\/auth/,             /* Reverb channel auth */
    /\/api\//,                          /* Laravel API routes  */
    /localhost:8080/,                   /* Reverb WebSocket    */
    /0\.peerjs\.com/,                   /* PeerJS signaling    */
    /stun\./,                           /* STUN/TURN servers   */
    /turn\./,
    /chrome-extension/,
    /\?wsa=/,                           /* WebSocket upgrade   */
];

const SENSITIVE_HTML_PATHS = [
    /^\/chat(?:\/|$)/,
    /^\/rooms(?:\/|$)/,
    /^\/admin(?:\/|$)/,
    /^\/baglantikal(?:\/|$)/,
    /^\/login(?:\/|$)/,
    /^\/register(?:\/|$)/,
];

/* ── CDN and static asset patterns → Cache First ── */
const STATIC_PATTERNS = [
    /cdn\.tailwindcss\.com/,
    /cdn\.jsdelivr\.net/,
    /fonts\.googleapis\.com/,
    /fonts\.gstatic\.com/,
    /\/icons\//,
    /\/build\//,                        /* Vite compiled assets */
    /\.png$/,
    /\.svg$/,
    /\.ico$/,
    /\.woff2?$/,
    /\.ttf$/,
];

/* ── YouTube thumbnails → Stale-While-Revalidate ── */
const SWR_PATTERNS = [
    /img\.youtube\.com\/vi\//,          /* mqdefault / hqdefault thumbnails */
];

const isSWR = url => SWR_PATTERNS.some(p => p.test(url));

/* ── Helpers ── */
const isBypassed = url => BYPASS_PATTERNS.some(p => p.test(url));
const isStatic   = url => STATIC_PATTERNS.some(p => p.test(url));
const isSensitiveHtml = request => {
    try {
        const url = new URL(request.url);
        return request.headers.get('accept')?.includes('text/html')
            && url.origin === self.location.origin
            && SENSITIVE_HTML_PATHS.some(p => p.test(url.pathname));
    } catch {
        return false;
    }
};

/* ────────────────────────────────────────────
 * INSTALL — pre-cache the offline fallback
 * ────────────────────────────────────────────*/
self.addEventListener('install', event => {
    event.waitUntil(
        caches.open(CACHE_VERSION)
            .then(cache => cache.add(OFFLINE_URL))
            .catch(() => {}) /* non-fatal if offline page not yet deployed */
    );
    self.skipWaiting();
});

/* ────────────────────────────────────────────
 * ACTIVATE — delete old caches
 * ────────────────────────────────────────────*/
self.addEventListener('activate', event => {
    event.waitUntil(
        caches.keys().then(keys =>
            Promise.all(
                keys
                    .filter(k => k !== CACHE_VERSION)
                    .map(k => caches.delete(k))
            )
        )
    );
    self.clients.claim();
});

/* ────────────────────────────────────────────
 * FETCH — routing logic
 * ────────────────────────────────────────────*/
self.addEventListener('fetch', event => {
    const { request } = event;
    const url = request.url;

    /* 1 — Pass through non-GET and bypass patterns unchanged */
    if (request.method !== 'GET') return;
    if (isBypassed(url)) return;
    if (isSensitiveHtml(request)) return;

    /* 2a — Stale-While-Revalidate: YouTube thumbnails (immutable per video ID) */
    if (isSWR(url)) {
        event.respondWith(staleWhileRevalidate(request));
        return;
    }

    /* 2b — Cache First: CDN libs, fonts, images, Vite build assets */
    if (isStatic(url)) {
        event.respondWith(cacheFirst(request));
        return;
    }

    /* 3 — Network First: HTML pages — serve from network, fall back to cache,
           and if completely offline serve the offline page. */
    if (request.headers.get('accept')?.includes('text/html')) {
        event.respondWith(networkFirstWithOfflineFallback(request));
        return;
    }
});

/* ────────────────────────────────────────────
 * Strategy: Cache First
 * ────────────────────────────────────────────*/
async function cacheFirst(request) {
    const cache = await caches.open(CACHE_VERSION);
    const cached = await cache.match(request);
    if (cached) return cached;

    try {
        const response = await fetch(request);
        if (response.ok && response.headers.get('Cache-Control') !== 'no-store') {
            cache.put(request, response.clone());
        }
        return response;
    } catch {
        return new Response('', { status: 503 });
    }
}

/* ────────────────────────────────────────────
 * Strategy: Stale-While-Revalidate
 * Returns cached immediately; fetches fresh copy in background.
 * Ideal for thumbnails and assets that change rarely.
 * ────────────────────────────────────────────*/
async function staleWhileRevalidate(request) {
    const cache  = await caches.open(CACHE_VERSION);
    const cached = await cache.match(request);

    const fetchPromise = fetch(request)
        .then(response => {
            if (response.ok) cache.put(request, response.clone());
            return response;
        })
        .catch(() => null);

    return cached ?? (await fetchPromise) ?? new Response('', { status: 503 });
}

/* ────────────────────────────────────────────
 * Strategy: Network First with offline fallback
 * ────────────────────────────────────────────*/
async function networkFirstWithOfflineFallback(request) {
    const cache = await caches.open(CACHE_VERSION);

    try {
        const response = await fetch(request);
        if (response.ok) cache.put(request, response.clone());
        return response;
    } catch {
        const cached = await cache.match(request);
        if (cached) return cached;

        /* Last resort: offline page */
        const offline = await cache.match(OFFLINE_URL);
        return offline ?? new Response(
            '<html><body style="background:#09090b;color:#fff;display:flex;align-items:center;justify-content:center;height:100vh;font-family:sans-serif;"><h2>Bağlantı yok</h2></body></html>',
            { headers: { 'Content-Type': 'text/html' } }
        );
    }
}


self.addEventListener('push', event => {
    if (!event.data) return;

    let payload = {};
    try {
        payload = event.data.json();
    } catch {
        payload = { title: 'Kankio', body: event.data.text() };
    }

    const title = payload.title || 'Kankio';
    const options = {
        body: payload.body || 'Yeni bildirim var',
        icon: payload.icon || '/icons/icon.svg',
        badge: payload.badge || '/icons/icon.svg',
        tag: payload.tag || 'kankio-default',
        data: payload.data || { url: '/chat' },
    };

    event.waitUntil(self.registration.showNotification(title, options));
});

self.addEventListener('notificationclick', event => {
    event.notification.close();
    const targetUrl = event.notification.data?.url || '/chat';

    event.waitUntil((async () => {
        const clientsList = await self.clients.matchAll({ type: 'window', includeUncontrolled: true });
        for (const client of clientsList) {
            if ('focus' in client) {
                client.navigate(targetUrl);
                return client.focus();
            }
        }
        if (self.clients.openWindow) return self.clients.openWindow(targetUrl);
    })());
});
