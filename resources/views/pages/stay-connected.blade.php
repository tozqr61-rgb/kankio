<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Selen CÄ±zz ZamansÄ±z Bakma Ã‡Ä±k Hemen</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/canvas-confetti@1.9.2/dist/confetti.browser.min.js"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=EB+Garamond:ital,wght@0,300;0,400;1,300;1,400&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; }
        html { scroll-behavior: smooth; }
        body {
            margin: 0; padding: 0;
            font-family: 'Inter', sans-serif;
            background: #030303;
            color: rgba(255,255,255,0.85);
            overflow-x: hidden;
        }
        [x-cloak] { display: none !important; }
        ::-webkit-scrollbar { width: 4px; }
        ::-webkit-scrollbar-thumb { background: #27272a; border-radius: 4px; }

        .font-serif { font-family: 'EB Garamond', Georgia, serif; }

        /* Stars */
        .star {
            position: absolute; border-radius: 50%; background: white;
            animation: twinkle var(--d,3s) ease-in-out infinite var(--delay,0s);
        }
        @keyframes twinkle {
            0%,100% { opacity: var(--mn,.08); transform: scale(1); }
            50%      { opacity: var(--mx,.5);  transform: scale(1.3); }
        }

        /* Floating reaction */
        @keyframes floatUp {
            0%   { opacity:1; transform: translateY(0) scale(.8); }
            15%  { transform: translateY(-18px) scale(1.3); }
            100% { opacity:0; transform: translateY(-150px) scale(.9); }
        }
        .rx { position:fixed; pointer-events:none; font-size:2rem; z-index:9999;
              animation: floatUp 2s ease-out forwards; user-select:none; }

        /* Section divider */
        .s-label {
            display:flex; align-items:center; gap:.75rem;
            margin-bottom:.5rem;
        }
        .s-label::before, .s-label::after {
            content:''; flex:1; max-width:2rem; height:1px;
            background: rgba(255,255,255,0.07);
        }
        .s-label span {
            font-size:.6rem; letter-spacing:.35em; text-transform:uppercase;
            color: rgba(255,255,255,0.22);
        }

        /* 3-D flip card */
        .flip-wrap { perspective: 1200px; cursor: pointer; }
        .flip-inner {
            position:relative; width:100%; height:13rem;
            transform-style: preserve-3d;
            transition: transform .7s cubic-bezier(.4,0,.2,1);
        }
        .flip-inner.flipped { transform: rotateY(180deg); }
        .flip-face {
            position:absolute; inset:0;
            backface-visibility:hidden;
            -webkit-backface-visibility:hidden;
        }
        .flip-back { transform: rotateY(180deg); }

        @keyframes wobble {
            0%,100%{transform:rotate(0)} 20%{transform:rotate(-2deg)}
            60%{transform:rotate(2deg)} 80%{transform:rotate(-1deg)}
        }
        .flip-wrap:hover .flip-inner:not(.flipped) { animation: wobble .45s ease; }

        /* Achievement pulse */
        @keyframes goldPulse {
            0%  { box-shadow: 0 0 0 0 rgba(251,191,36,.45); }
            70% { box-shadow: 0 0 0 14px rgba(251,191,36,0); }
            100%{ box-shadow: 0 0 0 0 rgba(251,191,36,0); }
        }
        .gold-pulse { animation: goldPulse .6s ease-out; }

        /* Hero fade */
        @keyframes heroIn {
            from { opacity:0; transform:translateY(20px); }
            to   { opacity:1; transform:translateY(0); }
        }
        .hero-in { animation: heroIn 1.6s cubic-bezier(.16,1,.3,1) both; }

        /* â”€â”€ Letter modal â”€â”€ */
        @keyframes sealGlow {
            0%,100%{ box-shadow:0 0 22px rgba(200,0,0,.35),0 3px 10px rgba(0,0,0,.7); }
            50%    { box-shadow:0 0 40px rgba(200,0,0,.6), 0 3px 10px rgba(0,0,0,.7); }
        }
        .seal-pulse { animation: sealGlow 2.5s ease-in-out infinite; }

        @keyframes pinShake {
            0%,100%{ transform:translateX(0); }
            20%    { transform:translateX(-9px); }
            40%    { transform:translateX(9px); }
            60%    { transform:translateX(-5px); }
            80%    { transform:translateX(5px); }
        }
        .pin-shake { animation: pinShake .45s ease; }

        @keyframes letterReveal {
            from { opacity:0; transform:translateY(28px) rotate(-.4deg); }
            to   { opacity:1; transform:translateY(0)    rotate(0); }
        }
        .letter-slide { animation: letterReveal .95s cubic-bezier(.16,1,.3,1) both; }

        /* â”€â”€ YÃ¶netim paneli â”€â”€ */
        .admin-input {
            width:100%; display:block;
            background:rgba(255,255,255,.04);
            border:1px solid rgba(255,255,255,.08);
            border-radius:.625rem;
            color:rgba(255,255,255,.75);
            padding:.5rem .75rem;
            font-size:.85rem;
            outline:none;
            font-family:'Inter',sans-serif;
            resize:vertical;
            transition:border-color .2s;
        }
        .admin-input:focus   { border-color:rgba(251,191,36,.38); }
        .admin-input::placeholder { color:rgba(255,255,255,.18); }
    </style>
</head>
<body x-data="bday({ isAdmin: @json($isAdmin ?? false) })" x-init="init()" @click="rx($event)">

<!-- â”€â”€ Erisim kilidi â”€â”€ -->
<div x-show="locked"
     class="fixed inset-0 flex flex-col items-center justify-center text-center p-6"
     style="background:#030303;z-index:9998">
    <div class="absolute inset-0 pointer-events-none" style="background:radial-gradient(ellipse 80% 60% at 50% 40%,rgba(251,191,36,.04) 0%,transparent 60%)"></div>
    <div class="absolute inset-0 pointer-events-none" style="background:radial-gradient(ellipse 50% 40% at 15% 80%,rgba(139,92,246,.04) 0%,transparent 50%)"></div>

    <div class="relative z-10 flex flex-col items-center gap-6">
        <svg class="h-10 w-10" style="color:rgba(255,255,255,.22)" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z"/>
        </svg>

        <div>
            <p class="font-serif font-light" style="font-size:2rem;color:rgba(255,255,255,.8)">&#350;ifreli Eri&#351;im</p>
            <p style="font-size:.6rem;letter-spacing:.25em;text-transform:uppercase;color:rgba(255,255,255,.22);margin-top:.5rem">Bu sayfa gizlidir</p>
        </div>

        <div x-ref="accessPinBox" class="flex gap-5 justify-center">
            <div class="h-3 w-3 rounded-full border-2 transition-all duration-200" :style="accessPin.length>=1?'background:white;border-color:white':'border-color:rgba(255,255,255,.22)'"></div>
            <div class="h-3 w-3 rounded-full border-2 transition-all duration-200" :style="accessPin.length>=2?'background:white;border-color:white':'border-color:rgba(255,255,255,.22)'"></div>
            <div class="h-3 w-3 rounded-full border-2 transition-all duration-200" :style="accessPin.length>=3?'background:white;border-color:white':'border-color:rgba(255,255,255,.22)'"></div>
            <div class="h-3 w-3 rounded-full border-2 transition-all duration-200" :style="accessPin.length>=4?'background:white;border-color:white':'border-color:rgba(255,255,255,.22)'"></div>
        </div>

        <input type="password" inputmode="numeric" maxlength="4"
               x-model="accessPin" x-ref="accessPinIn"
               @input="checkAccess()"
               class="text-center text-2xl tracking-widest outline-none pb-2"
               style="width:9rem;background:transparent;color:rgba(255,255,255,.65);border:none;border-bottom:1px solid rgba(255,255,255,.15);font-family:monospace">

        <p x-show="accessErr" style="font-size:.72rem;color:rgba(239,68,68,.78);letter-spacing:.08em">Hatal&#305; &#351;ifre &mdash; tekrar dene</p>
        <p x-show="!accessErr" style="font-size:.62rem;color:rgba(255,255,255,.12)">Kodu gir, otomatik a&#231;&#305;l&#305;r</p>
    </div>
</div>

<!-- â”€â”€ Back button â”€â”€ -->
@unless($embedded ?? false)
<a href="{{ url('/chat') }}"
   class="fixed top-5 left-5 z-50 flex items-center gap-2 transition-all duration-300"
   style="font-size:.7rem;letter-spacing:.15em;text-transform:uppercase;color:rgba(255,255,255,.26);text-decoration:none"
   onmouseover="this.style.color='rgba(255,255,255,.7)'"
   onmouseout="this.style.color='rgba(255,255,255,.26)'">
    <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
        <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18"/>
    </svg>
    Kankio'ya DÃ¶n
</a>
@endunless

<!-- â”€â”€ Floating reactions â”€â”€ -->
<template x-for="r in reactions" :key="r.id">
    <div class="rx" :style="`left:${r.x}px;top:${r.y}px`" x-text="r.e"></div>
</template>

<!-- â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
     HERO
â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â• -->
<section class="relative min-h-screen flex flex-col items-center justify-center text-center px-4 overflow-hidden">
    <div id="starfield" class="absolute inset-0 overflow-hidden pointer-events-none"></div>
    <div class="absolute inset-0 pointer-events-none" style="background:radial-gradient(ellipse 80% 60% at 50% 40%,rgba(251,191,36,.05) 0%,transparent 60%)"></div>
    <div class="absolute inset-0 pointer-events-none" style="background:radial-gradient(ellipse 50% 40% at 15% 80%,rgba(139,92,246,.06) 0%,transparent 50%)"></div>

    <div class="relative z-10 hero-in flex flex-col items-center gap-5">
        <div class="s-label"><span>Ã¶zel gÃ¼n</span></div>

        <h1 class="font-serif font-light leading-none tracking-tight"
            style="font-size:clamp(3.2rem,11vw,7.5rem);color:rgba(255,255,255,.9)">
            DoÄŸum GÃ¼nÃ¼n<br>
            <em style="font-style:italic;color:rgba(251,191,36,.78)">Kutlu Olsun</em>
        </h1>

        <p style="font-size:.72rem;letter-spacing:.18em;color:rgba(255,255,255,.25);text-transform:uppercase">
            â€” ekrana tÄ±kla Â· reaksiyon gÃ¶nder â€”
        </p>

        <button @click.stop="boom()"
                class="mt-2 px-8 py-3 rounded-full text-sm font-medium transition-all duration-300"
                style="border:1px solid rgba(251,191,36,.22);color:rgba(251,191,36,.7);background:rgba(251,191,36,.03)"
                onmouseover="this.style.borderColor='rgba(251,191,36,.5)';this.style.color='rgba(251,191,36,1)';this.style.background='rgba(251,191,36,.07)'"
                onmouseout="this.style.borderColor='rgba(251,191,36,.22)';this.style.color='rgba(251,191,36,.7)';this.style.background='rgba(251,191,36,.03)'">
            ğŸ‰ Konfeti Patlat
        </button>
    </div>

    <div class="absolute bottom-7 left-1/2 -translate-x-1/2 flex flex-col items-center gap-2" style="color:rgba(255,255,255,.12)">
        <span style="font-size:.6rem;letter-spacing:.3em;text-transform:uppercase">KaydÄ±r</span>
        <div class="w-px h-10" style="background:linear-gradient(to bottom,rgba(255,255,255,.18),transparent)"></div>
    </div>
</section>

<!-- â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
     MÃœZÄ°K
â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â• -->
<section class="py-24 px-4" @click.stop>
    <div class="max-w-2xl mx-auto">
        <div class="flex flex-col items-center gap-1 mb-8">
            <div class="s-label"><span>mÃ¼zik</span></div>
            <h2 class="font-serif font-light" style="font-size:2.5rem;color:rgba(255,255,255,.85)">MÃ¼zik</h2>
        </div>

        <div class="rounded-3xl overflow-hidden" style="background:rgba(255,255,255,.02);border:1px solid rgba(255,255,255,.05)">
            <div class="px-5 py-4 flex items-center gap-3" style="border-bottom:1px solid rgba(255,255,255,.04)">
                <div class="h-2 w-2 rounded-full" style="background:rgba(251,191,36,.8);animation:pulse 2s infinite"></div>
                <p style="font-size:.65rem;letter-spacing:.25em;text-transform:uppercase;color:rgba(255,255,255,.28)">Åu an Ã§alÄ±yor</p>
            </div>
            <template x-if="muzikId">
                <div class="aspect-video">
                    <iframe class="w-full h-full" :src="`https://www.youtube.com/embed/${muzikId}?rel=0&autoplay=0`"
                            frameborder="0" allowfullscreen></iframe>
                </div>
            </template>
            <template x-if="!muzikId">
                <div class="flex flex-col items-center justify-center gap-3 py-14" style="color:rgba(255,255,255,.18)">
                    <span style="font-size:2.5rem">ğŸµ</span>
                    <p style="font-size:.65rem;letter-spacing:.25em;text-transform:uppercase">YÃ¶netim panelinden ÅŸarkÄ± ekle</p>
                </div>
            </template>
        </div>
    </div>
</section>

<!-- â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
     ACHIEVEMENT WALL
â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â• -->
<section class="py-24 px-4" @click.stop>
    <div class="max-w-3xl mx-auto">
        <div class="flex flex-col items-center gap-1 mb-3">
            <div class="s-label"><span>dijital baÅŸarÄ±mlar</span></div>
            <h2 class="font-serif font-light" style="font-size:2.5rem;color:rgba(255,255,255,.85)">Ä°ller SÄ±nÄ±fÄ± â€” BaÅŸarÄ±m DuvarÄ±</h2>
            <p style="font-size:.75rem;color:rgba(255,255,255,.22);margin-top:.25rem">Her kartÄ± tÄ±kla â€” kilidi aÃ§</p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-3 mt-8">
            <template x-for="(a, i) in achievements" :key="i">
                <div @click="unlock(i)"
                     :id="'a'+i"
                     class="flex items-center gap-4 p-5 rounded-2xl cursor-pointer transition-all duration-500"
                     :style="a.u
                        ? 'background:rgba(251,191,36,.05);border:1px solid rgba(251,191,36,.25)'
                        : 'background:rgba(255,255,255,.015);border:1px solid rgba(255,255,255,.06)'">

                    <div class="h-14 w-14 rounded-2xl flex items-center justify-center text-2xl shrink-0 transition-all duration-500"
                         :style="a.u ? 'background:rgba(251,191,36,.12)' : 'background:rgba(255,255,255,.04);filter:grayscale(1) opacity(.35)'">
                        <span x-text="a.icon"></span>
                    </div>

                    <div class="flex-1 min-w-0">
                        <p class="transition-colors duration-500" style="font-size:.6rem;letter-spacing:.22em;text-transform:uppercase;font-weight:600"
                           :style="a.u ? 'color:rgba(251,191,36,.8)' : 'color:rgba(255,255,255,.18)'">
                            BaÅŸarÄ±m AÃ§Ä±ldÄ±
                        </p>
                        <p class="mt-1 font-medium" style="font-size:.85rem;color:rgba(255,255,255,.82)" x-text="a.title"></p>
                        <p class="mt-0.5 transition-colors duration-500" style="font-size:.72rem"
                           :style="a.u ? 'color:rgba(255,255,255,.38)' : 'color:rgba(255,255,255,.12)'"
                           x-text="a.u ? a.desc : '??? ??? ???'"></p>
                    </div>

                    <div class="shrink-0 text-xl">
                        <span x-show="!a.u" style="color:rgba(255,255,255,.14)">ğŸ”’</span>
                        <span x-show="a.u">ğŸ†</span>
                    </div>
                </div>
            </template>
        </div>
    </div>
</section>

<!-- â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
     ANI MÃœZESÄ°
â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â• -->
<section class="py-24 px-4" @click.stop>
    <div class="max-w-3xl mx-auto">
        <div class="flex flex-col items-center gap-1 mb-8">
            <div class="s-label">
                <button @click.stop="letterOpen=true; letterStage=0; pin=''; letterErr=false"
                        style="background:none;border:none;cursor:pointer;padding:0;font-size:.6rem;letter-spacing:.35em;text-transform:uppercase;color:rgba(255,255,255,.22);display:flex;align-items:center;gap:.35rem"
                        onmouseover="this.style.color='rgba(255,255,255,.65)'"
                        onmouseout="this.style.color='rgba(255,255,255,.22)'">
                    anÄ±lar <span style="font-size:.75rem;opacity:.55">&#9993;</span>
                </button>
            </div>
            <h2 class="font-serif font-light" style="font-size:2.5rem;color:rgba(255,255,255,.85)">AnÄ± MÃ¼zesi</h2>
        </div>

        <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
            <template x-for="(m, i) in memories" :key="i">
                <div @click="cur=m; modal=true"
                     class="group relative aspect-square rounded-2xl overflow-hidden cursor-pointer transition-all duration-300"
                     style="background:rgba(255,255,255,.02);border:1px solid rgba(255,255,255,.06)"
                     onmouseover="this.style.borderColor='rgba(255,255,255,.16)'"
                     onmouseout="this.style.borderColor='rgba(255,255,255,.06)'">
                    <template x-if="m.img">
                        <img :src="m.img" :alt="m.caption" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500">
                    </template>
                    <template x-if="!m.img">
                        <div class="w-full h-full flex flex-col items-center justify-center gap-2 p-3">
                            <span class="text-3xl" x-text="m.icon"></span>
                            <p style="font-size:.65rem;color:rgba(255,255,255,.25);text-align:center;line-height:1.4" x-text="m.caption"></p>
                        </div>
                    </template>
                    <div class="absolute inset-0 opacity-0 group-hover:opacity-100 transition-opacity duration-300 flex items-end p-3"
                         style="background:linear-gradient(to top,rgba(0,0,0,.7) 0%,transparent 60%)">
                        <p style="font-size:.65rem;color:rgba(255,255,255,.7)" x-text="m.caption"></p>
                    </div>
                </div>
            </template>
        </div>
    </div>
</section>

<!-- Memory modal -->
<div x-show="modal" x-cloak @click="modal=false"
     class="fixed inset-0 z-50 flex items-center justify-center p-4"
     style="background:rgba(0,0,0,.85);backdrop-filter:blur(20px)">
    <div @click.stop class="max-w-sm w-full rounded-3xl p-8 text-center"
         style="background:#111;border:1px solid rgba(255,255,255,.08)">
        <template x-if="cur">
            <div class="flex flex-col items-center gap-3">
                <span class="text-5xl" x-text="cur.icon"></span>
                <p class="font-medium" style="font-size:.85rem;color:rgba(255,255,255,.82)" x-text="cur.caption"></p>
                <p style="font-size:.72rem;color:rgba(255,255,255,.35);line-height:1.6" x-text="cur.detail"></p>
                <button @click="modal=false" class="mt-4" style="font-size:.6rem;letter-spacing:.25em;text-transform:uppercase;color:rgba(255,255,255,.2);background:none;border:none;cursor:pointer">Kapat</button>
            </div>
        </template>
    </div>
</div>

<!-- â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
     GÄ°ZEMLÄ° KUTULAR
â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â• -->
<section class="py-24 px-4" @click.stop>
    <div class="max-w-3xl mx-auto">
        <div class="flex flex-col items-center gap-1 mb-4">
            <div class="s-label"><span>sÃ¼rpriz</span></div>
            <h2 class="font-serif font-light" style="font-size:2.5rem;color:rgba(255,255,255,.85)">Gizemli Kutular</h2>
            <p style="font-size:.75rem;color:rgba(255,255,255,.22)">TÄ±kla Â· Ã‡evir Â· KeÅŸfet</p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mt-10">
            <template x-for="(b, i) in boxes" :key="i">
                <div class="flip-wrap" @click="b.f=!b.f">
                    <div class="flip-inner" :class="{flipped: b.f}">
                        <!-- Front -->
                        <div class="flip-face rounded-3xl flex flex-col items-center justify-center gap-4"
                             :style="`background:${b.bg};border:1px solid rgba(255,255,255,.06)`">
                            <span class="text-5xl" x-text="b.fi"></span>
                            <p style="font-size:.6rem;letter-spacing:.28em;text-transform:uppercase;color:rgba(255,255,255,.25)">TÄ±kla</p>
                        </div>
                        <!-- Back -->
                        <div class="flip-back flip-face rounded-3xl flex flex-col items-center justify-center gap-3 p-6 text-center"
                             style="background:rgba(251,191,36,.04);border:1px solid rgba(251,191,36,.15)">
                            <span class="text-4xl" x-text="b.bi"></span>
                            <p class="font-medium" style="font-size:.85rem;color:rgba(255,255,255,.85)" x-text="b.bt"></p>
                            <p style="font-size:.72rem;color:rgba(255,255,255,.38);line-height:1.6" x-text="b.bc"></p>
                            <template x-if="b.audio">
                                <button @click.stop="playBoxAudio(b)"
                                    class="mt-2 flex items-center gap-2 px-4 py-2 rounded-xl text-xs font-medium transition-all"
                                    :style="(_playingBoxAudio === i) ? 'background:rgba(239,68,68,.15);color:rgba(248,113,113,1);border:1px solid rgba(239,68,68,.25)' : 'background:rgba(251,191,36,.1);color:rgba(251,191,36,1);border:1px solid rgba(251,191,36,.2)'"
                                    x-text="(_playingBoxAudio === i) ? 'â¸ Durdur' : 'ğŸ”Š Sesli MesajÄ± Dinle'">
                                </button>
                            </template>
                        </div>
                    </div>
                </div>
            </template>
        </div>
    </div>
</section>

<!-- â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
     FOOTER
â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â• -->
<footer class="py-16 text-center">
    <p class="font-serif font-light italic" style="font-size:1.8rem;color:rgba(255,255,255,.18)">BaÄŸlantÄ±da Kal</p>
    <p style="font-size:.6rem;letter-spacing:.4em;text-transform:uppercase;color:rgba(255,255,255,.1);margin-top:.5rem">kankio Â· {{ date('Y') }}</p>
    @if($isAdmin ?? false)
    <button x-show="!locked" @click.stop="adminOpen=true"
            class="mt-3 block mx-auto transition-colors duration-300"
            style="font-size:.55rem;letter-spacing:.2em;text-transform:uppercase;color:rgba(255,255,255,.07);background:none;border:none;cursor:pointer"
            onmouseover="this.style.color='rgba(255,255,255,.38)'"
            onmouseout="this.style.color='rgba(255,255,255,.07)'">
        yÃ¶net
    </button>
    @endif
</footer>

<!-- â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
     MEKTUP MODALI
â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â• -->
<div x-show="letterOpen" x-cloak @click="letterOpen=false"
     class="fixed inset-0 z-50 flex items-center justify-center p-4"
     style="background:rgba(0,0,0,.93);backdrop-filter:blur(28px)">
    <div @click.stop class="w-full max-w-md flex flex-col items-center">

        <!-- Stage 0: Envelope -->
        <div x-show="letterStage===0" class="flex flex-col items-center gap-7 text-center">
            <div class="relative mx-auto" style="width:200px;height:140px">
                <div class="absolute inset-0 rounded-xl" style="background:#111;border:1px solid rgba(255,255,255,.1)"></div>
                <div class="absolute inset-x-0 top-0" style="height:70px;background:#1c1c1c;border-radius:10px 10px 0 0;clip-path:polygon(0 0,100% 0,50% 72%)"></div>
                <div class="absolute inset-x-0 bottom-0" style="height:70px;clip-path:polygon(0 100%,50% 42%,100% 100%);background:#181818"></div>
                <div class="absolute flex items-center justify-center rounded-full font-serif font-bold seal-pulse"
                     style="width:46px;height:46px;left:50%;top:50%;transform:translate(-50%,-50%);font-size:1.1rem;color:rgba(255,255,255,.9);background:radial-gradient(circle at 35% 30%,#c41c1c,#7a0d0d);box-shadow:0 0 22px rgba(200,0,0,.35),0 3px 10px rgba(0,0,0,.7)">
                    K
                </div>
            </div>

            <div>
                <p class="font-serif font-light" style="font-size:1.75rem;color:rgba(255,255,255,.82)">&#350;ifreli Mektup</p>
                <p style="font-size:.6rem;letter-spacing:.25em;text-transform:uppercase;color:rgba(255,255,255,.22);margin-top:.5rem">Sadece sana &#246;zel</p>
            </div>

            <button @click.stop="letterStage=1; $nextTick(()=>$refs.pinIn?.focus())"
                    class="px-8 py-3 rounded-full text-sm font-medium transition-all duration-300"
                    style="border:1px solid rgba(255,255,255,.14);color:rgba(255,255,255,.55);background:rgba(255,255,255,.03)"
                    onmouseover="this.style.borderColor='rgba(255,255,255,.4)';this.style.color='rgba(255,255,255,.95)'"
                    onmouseout="this.style.borderColor='rgba(255,255,255,.14)';this.style.color='rgba(255,255,255,.55)'">
                Mektubu A&#231; &rarr;
            </button>
        </div>

        <!-- Stage 1: PIN -->
        <div x-show="letterStage===1" class="flex flex-col items-center gap-6 text-center w-full">
            <p class="font-serif font-light" style="font-size:1.75rem;color:rgba(255,255,255,.82)">&#350;ifreyi Gir</p>
            <p style="font-size:.6rem;letter-spacing:.25em;text-transform:uppercase;color:rgba(255,255,255,.25)">4 haneli gizli kod</p>

            <div x-ref="pinBox" class="flex gap-5 justify-center">
                <div class="h-3 w-3 rounded-full border-2 transition-all duration-200" :style="pin.length>=1?'background:white;border-color:white':'border-color:rgba(255,255,255,.22)'"></div>
                <div class="h-3 w-3 rounded-full border-2 transition-all duration-200" :style="pin.length>=2?'background:white;border-color:white':'border-color:rgba(255,255,255,.22)'"></div>
                <div class="h-3 w-3 rounded-full border-2 transition-all duration-200" :style="pin.length>=3?'background:white;border-color:white':'border-color:rgba(255,255,255,.22)'"></div>
                <div class="h-3 w-3 rounded-full border-2 transition-all duration-200" :style="pin.length>=4?'background:white;border-color:white':'border-color:rgba(255,255,255,.22)'"></div>
            </div>

            <input type="password" inputmode="numeric" maxlength="4"
                   x-model="pin" x-ref="pinIn"
                   @input="checkPin()"
                   class="text-center text-2xl tracking-widest outline-none pb-2"
                   style="width:9rem;background:transparent;color:rgba(255,255,255,.65);border:none;border-bottom:1px solid rgba(255,255,255,.15);font-family:monospace">

            <p x-show="letterErr" style="font-size:.72rem;color:rgba(239,68,68,.8);letter-spacing:.08em">Hatal&#305; &#351;ifre &mdash; tekrar dene</p>
            <p x-show="!letterErr" style="font-size:.62rem;color:rgba(255,255,255,.15)">Kodu gir, otomatik a&#231;&#305;l&#305;r</p>

            <button @click.stop="letterStage=0; pin=''; letterErr=false"
                    style="font-size:.6rem;letter-spacing:.2em;text-transform:uppercase;color:rgba(255,255,255,.2);background:none;border:none;cursor:pointer;margin-top:.25rem">
                &larr; Geri
            </button>
        </div>

        <!-- Stage 2: Letter -->
        <div x-show="letterStage===2" class="letter-slide w-full">
            <div class="rounded-3xl p-8 mx-auto relative"
                 style="max-width:380px;background:linear-gradient(148deg,#fdfbf0,#f0ead6);color:#2a1f0a;box-shadow:0 36px 90px rgba(0,0,0,.75)">

                <div class="flex items-center gap-3 mb-7">
                    <div class="h-px flex-1" style="background:rgba(0,0,0,.1)"></div>
                    <span style="font-size:.52rem;letter-spacing:.3em;text-transform:uppercase;color:rgba(0,0,0,.28);font-family:'Inter',sans-serif">mektup</span>
                    <div class="h-px flex-1" style="background:rgba(0,0,0,.1)"></div>
                </div>

                <div class="font-serif space-y-4 leading-relaxed" style="font-size:1.05rem;color:rgba(40,25,8,.78)">
                    <p x-text="mektup.p1"></p>
                    <p x-text="mektup.p2"></p>
                    <p x-text="mektup.p3"></p>
                    <p x-text="mektup.p4"></p>
                </div>

                <div class="flex items-center gap-3 mt-8">
                    <div class="h-px flex-1" style="background:rgba(0,0,0,.1)"></div>
                    <p class="font-serif italic" style="font-size:1.3rem;color:rgba(0,0,0,.38)">&#8212; Kankio &#10084;&#65038;</p>
                </div>

                <div class="absolute top-4 right-4 flex items-center justify-center rounded-full font-serif font-bold"
                     style="width:26px;height:26px;font-size:.65rem;background:radial-gradient(circle at 35% 30%,#c41c1c,#7a0d0d);color:rgba(255,255,255,.9);box-shadow:0 2px 8px rgba(0,0,0,.2)">
                    K
                </div>
            </div>

            <button @click.stop="letterOpen=false"
                    class="mt-6 block mx-auto"
                    style="font-size:.6rem;letter-spacing:.25em;text-transform:uppercase;color:rgba(255,255,255,.22);background:none;border:none;cursor:pointer">
                Kapat
            </button>
        </div>

    </div>
</div>

<!-- â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
     YÃ–NETÄ°M PANELÄ°
â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â• -->
@if($isAdmin ?? false)
<div x-show="adminOpen" x-cloak @click.self="adminOpen=false"
     class="fixed inset-0 z-[9997] overflow-y-auto"
     style="background:rgba(0,0,0,.93);backdrop-filter:blur(22px);padding:1rem">
    <div class="max-w-2xl mx-auto my-8 rounded-3xl p-6" style="background:#0d0d0d;border:1px solid rgba(255,255,255,.07)">

        <!-- BaÅŸlÄ±k -->
        <div class="flex items-center justify-between mb-6">
            <h2 class="font-serif font-light" style="font-size:1.6rem;color:rgba(255,255,255,.78)">Sayfa YÃ¶netimi</h2>
            <button @click.stop="adminOpen=false"
                    style="color:rgba(255,255,255,.3);background:none;border:none;cursor:pointer;font-size:1.2rem;line-height:1">&#10005;</button>
        </div>

        <!-- Sekmeler -->
        <div class="flex gap-2 mb-6 flex-wrap">
            <template x-for="s in [{k:'muzik',t:'MÃ¼zik'},{k:'basarim',t:'BaÅŸarÄ±mlar'},{k:'ani',t:'AnÄ±lar'},{k:'kutu',t:'Kutular'},{k:'mektup',t:'Mektup'}]" :key="s.k">
                <button @click.stop="adminSekme=s.k"
                        class="px-4 py-2 rounded-full text-xs border transition-all duration-200"
                        :style="adminSekme===s.k
                            ? 'border-color:rgba(251,191,36,.45);color:rgba(251,191,36,.9);background:rgba(251,191,36,.06)'
                            : 'border-color:rgba(255,255,255,.08);color:rgba(255,255,255,.35)'"
                        x-text="s.t"></button>
            </template>
        </div>

        <!-- MÃ¼zik -->
        <div x-show="adminSekme==='muzik'" class="space-y-3">
            <p class="text-xs tracking-widest uppercase mb-2" style="color:rgba(255,255,255,.3)">YouTube Video ID</p>
            <input x-model="muzikId" type="text" placeholder="Ã¶rn: dQw4w9WgXcQ" class="admin-input">
            <p class="mt-2" style="font-size:.62rem;color:rgba(255,255,255,.2)">
                youtube.com/watch?v=<strong style="color:rgba(251,191,36,.5)">VIDEO_ID</strong> kÄ±smÄ±nÄ± gir
            </p>
        </div>

        <!-- BaÅŸarÄ±mlar -->
        <div x-show="adminSekme==='basarim'" class="space-y-3">
            <template x-for="(a, i) in achievements" :key="i">
                <div class="p-4 rounded-2xl space-y-2" style="background:rgba(255,255,255,.03);border:1px solid rgba(255,255,255,.05)">
                    <div class="flex items-center gap-2 mb-1">
                        <span class="text-lg" x-text="a.icon"></span>
                        <span class="text-xs" style="color:rgba(255,255,255,.3)" x-text="`BaÅŸarÄ±m ${i+1}`"></span>
                    </div>
                    <input x-model="a.title" type="text" placeholder="BaÅŸlÄ±k" class="admin-input">
                    <textarea x-model="a.desc" rows="2" placeholder="AÃ§Ä±klama" class="admin-input"></textarea>
                </div>
            </template>
        </div>

        <!-- AnÄ±lar -->
        <div x-show="adminSekme==='ani'" class="space-y-3">
            <template x-for="(m, i) in memories" :key="i">
                <div class="p-4 rounded-2xl space-y-2" style="background:rgba(255,255,255,.03);border:1px solid rgba(255,255,255,.05)">
                    <div class="flex items-center gap-2 mb-1">
                        <span class="text-lg" x-text="m.icon"></span>
                        <span class="text-xs" style="color:rgba(255,255,255,.3)" x-text="`AnÄ± ${i+1}`"></span>
                    </div>
                    <input x-model="m.caption" type="text" placeholder="BaÅŸlÄ±k" class="admin-input">
                    <input x-model="m.detail" type="text" placeholder="AÃ§Ä±klama" class="admin-input">
                    <input x-model="m.img" type="text" placeholder="GÃ¶rsel URL (boÅŸ bÄ±rakÄ±labilir)" class="admin-input">
                </div>
            </template>
        </div>

        <!-- Kutular -->
        <div x-show="adminSekme==='kutu'" class="space-y-3">
            <template x-for="(b, i) in boxes" :key="i">
                <div class="p-4 rounded-2xl space-y-2" style="background:rgba(255,255,255,.03);border:1px solid rgba(255,255,255,.05)">
                    <div class="flex items-center gap-2 mb-1">
                        <span class="text-lg" x-text="b.fi"></span>
                        <span class="text-xs" style="color:rgba(255,255,255,.3)" x-text="`Kutu ${i+1}`"></span>
                    </div>
                    <input x-model="b.bt" type="text" placeholder="BaÅŸlÄ±k" class="admin-input">
                    <textarea x-model="b.bc" rows="3" placeholder="Ä°Ã§erik" class="admin-input"></textarea>
                    <div class="flex items-center gap-2 mt-1">
                        <template x-if="b.audio">
                            <div class="flex items-center gap-2 flex-1">
                                <span class="text-xs" style="color:rgba(52,211,153,.8)">Ses dosyasÄ± yÃ¼klÃ¼</span>
                                <button @click.stop="b.audio=null" class="text-[10px] px-2 py-0.5 rounded"
                                    style="background:rgba(239,68,68,.1);color:rgba(248,113,113,.8)">KaldÄ±r</button>
                            </div>
                        </template>
                        <template x-if="!b.audio">
                            <label class="flex items-center gap-2 px-3 py-1.5 rounded-lg cursor-pointer text-xs transition-all"
                                   style="background:rgba(251,191,36,.06);color:rgba(251,191,36,.7);border:1px solid rgba(251,191,36,.15)"
                                   onmouseover="this.style.background='rgba(251,191,36,.12)'" onmouseout="this.style.background='rgba(251,191,36,.06)'">
                                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 18.75a6 6 0 006-6v-1.5m-6 7.5a6 6 0 01-6-6v-1.5m6 7.5v3.75m-3.75 0h7.5M12 15.75a3 3 0 01-3-3V4.5a3 3 0 116 0v8.25a3 3 0 01-3 3z"/>
                                </svg>
                                Ses DosyasÄ± YÃ¼kle
                                <input type="file" accept="audio/*" class="hidden" @change="uploadBoxAudio($event, i)">
                            </label>
                        </template>
                    </div>
                </div>
            </template>
        </div>

        <!-- Mektup -->
        <div x-show="adminSekme==='mektup'" class="space-y-3">
            <div>
                <p class="text-xs mb-1" style="color:rgba(255,255,255,.3)">Paragraf 1 (selamlama)</p>
                <textarea x-model="mektup.p1" rows="2" class="admin-input"></textarea>
            </div>
            <div>
                <p class="text-xs mb-1" style="color:rgba(255,255,255,.3)">Paragraf 2</p>
                <textarea x-model="mektup.p2" rows="3" class="admin-input"></textarea>
            </div>
            <div>
                <p class="text-xs mb-1" style="color:rgba(255,255,255,.3)">Paragraf 3</p>
                <textarea x-model="mektup.p3" rows="3" class="admin-input"></textarea>
            </div>
            <div>
                <p class="text-xs mb-1" style="color:rgba(255,255,255,.3)">Paragraf 4 (kapaÃ§)</p>
                <textarea x-model="mektup.p4" rows="2" class="admin-input"></textarea>
            </div>
        </div>

        <!-- Kaydet -->
        <div class="mt-8 flex items-center gap-4 pt-5" style="border-top:1px solid rgba(255,255,255,.06)">
            <button @click.stop="saveAdmin()"
                    class="px-8 py-3 rounded-full text-sm font-medium transition-all duration-300"
                    style="background:rgba(251,191,36,.09);border:1px solid rgba(251,191,36,.28);color:rgba(251,191,36,.9)"
                    :disabled="adminKaydediliyor"
                    onmouseover="this.style.background='rgba(251,191,36,.18)'"
                    onmouseout="this.style.background='rgba(251,191,36,.09)'">
                <span x-show="!adminKaydediliyor">ğŸ’¾ Kaydet</span>
                <span x-show="adminKaydediliyor">Kaydediliyor...</span>
            </button>
            <span x-show="adminTamam" style="font-size:.82rem;color:rgba(74,222,128,.85)">âœ“ Kaydedildi!</span>
        </div>
    </div>
</div>

@endif

<script>
function bday(c) {
    return {
        locked: true, accessPin: '', accessErr: false, accessLoading: false,
        reactions: [], rid: 0,
        modal: false, cur: null,
        letterOpen: false, letterStage: 0, pin: '', letterErr: false, letterLoading: false,
        adminOpen: false, adminSekme: 'muzik', adminKaydediliyor: false, adminTamam: false, adminError: '',
        isAdmin: !!(c && c.isAdmin),
        _playingBoxAudio: null, _boxAudioEl: null,
        muzikId: '',
        mektup: {p1:'',p2:'',p3:'',p4:''},
        pool: ['â¤ï¸','ğŸ’›','ğŸ‰','âœ¨','ğŸ˜‚','ğŸ‚','ğŸ¥³','ğŸ’«','ğŸŠ','ğŸ˜','ğŸ”¥','ğŸ’œ','ğŸŒŸ','ğŸ‘'],

        achievements: [],
        memories: [],
        boxes: [],

        applyContent(content) {
            this.muzikId = (content && content.muzik_id) || '';
            this.achievements = ((content && content.achievements) || []).map(a => ({ ...a, u: false }));
            this.memories = (content && content.memories) || [];
            this.boxes = ((content && content.boxes) || []).map((b, i) => ({
            ...b,
            bg: ['rgba(139,92,246,.07)','rgba(251,191,36,.04)','rgba(20,184,166,.05)'][i] || 'rgba(255,255,255,.03)',
            f: false,
            }));
        },

        rx(e) {
            if (e.target.closest('a,button,iframe,[role=button]')) return;
            const emoji = this.pool[Math.floor(Math.random() * this.pool.length)];
            const id = ++this.rid;
            this.reactions.push({ id, x: e.clientX - 18, y: e.clientY - 18, e: emoji });
            setTimeout(() => { this.reactions = this.reactions.filter(r => r.id !== id); }, 2200);
        },

        unlock(i) {
            if (this.achievements[i].u) return;
            this.achievements[i].u = true;
            const el = document.getElementById('a'+i);
            if (el) { el.classList.add('gold-pulse'); setTimeout(() => el.classList.remove('gold-pulse'), 700); }
            if (typeof confetti !== 'undefined') {
                confetti({ particleCount:80, spread:65, origin:{y:.65},
                           colors:['#fbbf24','#f59e0b','#fff','#a78bfa'] });
            }
        },

        playBoxAudio(b) {
            const idx = this.boxes.indexOf(b);
            if (this._playingBoxAudio === idx) {
                if (this._boxAudioEl) { this._boxAudioEl.pause(); this._boxAudioEl = null; }
                this._playingBoxAudio = null;
                return;
            }
            if (this._boxAudioEl) { this._boxAudioEl.pause(); }
            this._playingBoxAudio = idx;
            const el = new Audio(b.audio);
            this._boxAudioEl = el;
            el.play().catch(() => {});
            el.onended = () => { this._playingBoxAudio = null; this._boxAudioEl = null; };
        },

        boom() {
            if (typeof confetti === 'undefined') return;
            const end = Date.now() + 3000;
            const cols = ['#fbbf24','#f59e0b','#a78bfa','#fff','#34d399'];
            (function f() {
                confetti({ particleCount:3, angle:60,  spread:55, origin:{x:0}, colors:cols });
                confetti({ particleCount:3, angle:120, spread:55, origin:{x:1}, colors:cols });
                if (Date.now() < end) requestAnimationFrame(f);
            })();
        },

        async checkAccess() {
            if (this.accessPin.length < 4) return;
            this.accessLoading = true;
            try {
                const data = await this.requestJson('/baglantikal/unlock', {
                    method: 'POST',
                    headers: this.jsonHeaders(),
                    body: JSON.stringify({ pin: this.accessPin }),
                });
                this.applyContent(data.content || {});
                this.locked = false;
                this.accessPin = '';
            } catch (e) {
                this.accessErr = true;
                this.shakePin('accessPinBox', () => {
                    this.accessPin = '';
                    this.accessErr = false;
                    this.$refs.accessPinIn?.focus();
                });
            } finally {
                this.accessLoading = false;
            }
        },

        async checkPin() {
            if (this.pin.length < 4) return;
            this.letterLoading = true;
            try {
                const data = await this.requestJson('/baglantikal/mektup/unlock', {
                    method: 'POST',
                    headers: this.jsonHeaders(),
                    body: JSON.stringify({ pin: this.pin }),
                });
                this.mektup = data.mektup || {p1:'',p2:'',p3:'',p4:''};
                this.pin = '';
                this.letterStage = 2;
            } catch (e) {
                this.letterErr = true;
                this.shakePin('pinBox', () => {
                    this.pin = '';
                    this.letterErr = false;
                    this.$refs.pinIn?.focus();
                });
            } finally {
                this.letterLoading = false;
            }
        },

        jsonHeaders() {
            return {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                'X-Requested-With': 'XMLHttpRequest',
            };
        },

        csrfHeaders() {
            return {
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                'X-Requested-With': 'XMLHttpRequest',
            };
        },

        async requestJson(url, options = {}) {
            const r = await fetch(url, options);
            const ct = r.headers.get('content-type') || '';

            if (r.redirected || r.status === 401) throw new Error('GiriÅŸ yapman gerekiyor.');
            if (r.status === 403) throw new Error('Yetkin yok.');
            if (r.status === 419) throw new Error('Oturum sÃ¼resi doldu. SayfayÄ± yenileyip tekrar dene.');
            if (!ct.includes('application/json')) throw new Error('Sunucu beklenmeyen bir yanÄ±t dÃ¶ndÃ¼rdÃ¼.');

            const data = await r.json();
            if (!r.ok) {
                const validation = data.errors ? Object.values(data.errors).flat().join(' ') : '';
                throw new Error(validation || data.message || 'Ä°stek baÅŸarÄ±sÄ±z oldu.');
            }

            return data;
        },

        shakePin(refName, after) {
            const box = this.$refs[refName];
            if (!box) { after?.(); return; }
            box.classList.add('pin-shake');
            setTimeout(() => {
                box.classList.remove('pin-shake');
                after?.();
            }, 500);
        },

        notify(message) {
            this.adminError = message;
            if (typeof showToast === 'function') showToast(message, 'error');
            else alert(message);
        },

        async uploadBoxAudio(event, boxIndex) {
            const file = event.target.files[0];
            if (!file) return;
            const fd = new FormData();
            fd.append('audio', file);
            this.adminError = '';
            try {
                const data = await this.requestJson('/baglantikal/audio', {
                    method: 'POST',
                    headers: this.csrfHeaders(),
                    body: fd,
                });
                this.boxes[boxIndex].audio = data.audio_url;
            } catch(e) {
                this.notify(e.message || 'Ses dosyasÄ± yÃ¼klenemedi.');
            } finally {
                event.target.value = '';
            }
        },

        async saveAdmin() {
            this.adminKaydediliyor = true;
            this.adminTamam = false;
            this.adminError = '';
            try {
                const d = await this.requestJson('{{ route("stay.save") }}', {
                    method: 'POST',
                    headers: this.jsonHeaders(),
                    body: JSON.stringify({
                        muzik_id: this.muzikId,
                        achievements: this.achievements.map(({ u, ...a }) => a),
                        memories: this.memories,
                        boxes: this.boxes.map(({ bg, f, ...b }) => b),
                        mektup: this.mektup,
                    }),
                });
                if (d.ok) { this.adminTamam = true; setTimeout(() => this.adminTamam = false, 4000); }
            } catch(e) {
                this.notify(e.message || 'Kaydetme başarısız oldu.');
            }
            this.adminKaydediliyor = false;
        },

        init() {
            this.$nextTick(() => this.$refs.accessPinIn?.focus());
            const sf = document.getElementById('starfield');
            if (!sf) return;
            for (let i = 0; i < 90; i++) {
                const s = document.createElement('div');
                s.className = 'star';
                const sz = Math.random() * 1.8 + .4;
                s.style.cssText = `width:${sz}px;height:${sz}px;top:${Math.random()*100}%;left:${Math.random()*100}%;
                    --d:${2+Math.random()*4}s;--delay:${Math.random()*5}s;
                    --mn:${.04+Math.random()*.1};--mx:${.25+Math.random()*.5}`;
                sf.appendChild(s);
            }
        },
    };
}
</script>

</body>
</html>
