{{-- ── Android / Mobile Autoplay Unlock Overlay ──
     Chrome/Android blocks audio until the first user gesture.
     This overlay captures that gesture and unlocks AudioContext globally.
     Hidden on desktop (pointer:fine) and when already unlocked. --}}
{{-- Avatar Lightbox --}}
<div x-data="{}" x-show="$store.lightbox && $store.lightbox.src"
     x-cloak
     @click="$store.lightbox.src = null"
     @keydown.escape.window="$store.lightbox.src = null"
     class="fixed inset-0 z-[9500] flex items-center justify-center"
     style="background:rgba(0,0,0,0.88);backdrop-filter:blur(20px)">
    <div @click.stop class="flex flex-col items-center gap-4">
        <img :src="$store.lightbox && $store.lightbox.src"
             class="rounded-2xl shadow-2xl object-contain"
             style="max-width:min(480px,90vw);max-height:80vh;border:1px solid rgba(255,255,255,0.1)">
        <p class="text-sm font-medium" style="color:rgba(255,255,255,0.6)"
           x-text="$store.lightbox && $store.lightbox.name"></p>
    </div>
    <button @click="$store.lightbox.src = null"
            class="absolute top-5 right-5 h-10 w-10 flex items-center justify-center rounded-full"
            style="background:rgba(255,255,255,0.08);color:rgba(255,255,255,0.7)">
        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
        </svg>
    </button>
</div>

<div id="autoplay-overlay"
     style="display:none;position:fixed;inset:0;z-index:9000;
            background:rgba(0,0,0,0.92);backdrop-filter:blur(16px);
            flex-direction:column;align-items:center;justify-content:center;gap:1.5rem;">
    <img src="{{ url('icons/icon.svg') }}" style="width:4rem;height:4rem;border-radius:1rem;opacity:0.9" alt="Kankio">
    <p style="color:rgba(255,255,255,0.85);font-size:1rem;font-weight:500;letter-spacing:0.02em;text-align:center;max-width:20rem;line-height:1.6">
        Sesli sohbet ve müzik için <br><strong style="color:#fff">ekrana bir kez dokunun</strong>
    </p>
    <button onclick="unlockAudio(this)"
            style="padding:0.875rem 2.5rem;border-radius:999px;font-size:0.9rem;font-weight:600;
                   background:rgba(52,211,153,1);color:#000;border:none;cursor:pointer;
                   box-shadow:0 0 30px rgba(52,211,153,0.4)">
        Devam Et
    </button>
</div>
<script>
(function() {
    const KEY = 'kankio_audio_unlocked';
    if (sessionStorage.getItem(KEY)) return;
    /* Only show on mobile/touch devices */
    if (window.matchMedia('(pointer:fine)').matches) return;
    const overlay = document.getElementById('autoplay-overlay');
    if (overlay) overlay.style.display = 'flex';
})();

function unlockAudio(btn) {
    try {
        const ctx = new (window.AudioContext || window.webkitAudioContext)();
        const buf = ctx.createBuffer(1, 1, 22050);
        const src = ctx.createBufferSource();
        src.buffer = buf; src.connect(ctx.destination); src.start(0);
        ctx.resume().catch(() => {});
        window._audioUnlocked = true;
        sessionStorage.setItem('kankio_audio_unlocked', '1');
    } catch(e) {}
    const overlay = document.getElementById('autoplay-overlay');
    if (overlay) overlay.style.display = 'none';
}
</script>
