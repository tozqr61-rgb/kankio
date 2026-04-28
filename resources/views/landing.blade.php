<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Kankio</title>
    <meta name="theme-color" content="#09090b">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=EB+Garamond:ital,wght@0,400;0,300;1,400&display=swap');
        .font-serif { font-family: 'EB Garamond', Georgia, serif; }
        body { background: #000; overflow: hidden; }

        /* Butterfly animation */
        @keyframes flyUp {
            0%   { opacity: 0; transform: translate(0, 0) scale(1); }
            20%  { opacity: 0.6; }
            80%  { opacity: 0.4; }
            100% { opacity: 0; transform: translate(var(--dx), -180px) scale(var(--ds)); }
        }
        @keyframes wingFlap {
            0%, 100% { d: path("M12 12C12 12 8 2 3 8C-2 10 2 15 6 15C10 15 12 12 12 12Z"); }
            50%       { d: path("M12 12C12 12 8 8 3 9C-2 10 2 15 6 15C10 15 12 12 12 12Z"); }
        }
        .butterfly {
            position: absolute;
            pointer-events: none;
            animation: flyUp var(--dur, 12s) ease-in-out var(--delay, 0s) infinite;
        }
        .butterfly path { animation: wingFlap 0.2s ease-in-out infinite alternate; }

        @keyframes fadeUp {
            from { opacity: 0; transform: translateY(30px); }
            to   { opacity: 1; transform: translateY(0); }
        }
        .anim-1 { animation: fadeUp 1.5s ease-out forwards; }
        .anim-2 { animation: fadeUp 1s ease-out 0.5s both; }
        .anim-3 { animation: fadeUp 1.5s ease-out 1.5s both; }
        .anim-4 { animation: fadeUp 1s ease-out 2s both; }
        .anim-footer { animation: fadeIn 2s ease-out 4s both; }
        @keyframes fadeIn { from{opacity:0}to{opacity:0.5} }
        @keyframes pulseSlow { 0%,100%{opacity:0.3}50%{opacity:0.5} }
        .animate-pulse-slow { animation: pulseSlow 6s ease-in-out infinite; }
    </style>
</head>
<body class="relative min-h-screen w-full flex flex-col items-center justify-center overflow-hidden bg-black text-white select-none">

    <!-- Ambient blobs -->
    <div class="fixed inset-0 pointer-events-none z-0">
        <div class="absolute top-1/4 left-1/4 w-96 h-96 rounded-full blur-[100px] animate-pulse-slow" style="background:rgba(59,130,246,0.07)"></div>
        <div class="absolute bottom-1/4 right-1/4 w-96 h-96 rounded-full blur-[100px] animate-pulse-slow" style="background:rgba(168,85,247,0.07)"></div>
    </div>

    <!-- Butterflies -->
    <div class="absolute inset-0 overflow-hidden pointer-events-none z-0" id="butterflies">
        <div class="butterfly" style="left:120px;bottom:0;--dx:30px;--ds:1.1;--dur:13s;--delay:0s">
            <svg width="36" height="36" viewBox="-3 5 30 15" fill="none">
                <path d="M12 12C12 12 8 2 3 8C-2 10 2 15 6 15C10 15 12 12 12 12Z" fill="rgba(255,255,255,0.25)"/>
                <path d="M12 12C12 12 16 2 21 8C26 10 22 15 18 15C14 15 12 12 12 12Z" fill="rgba(255,255,255,0.25)"/>
            </svg>
        </div>
        <div class="butterfly" style="left:60px;bottom:0;--dx:-25px;--ds:0.7;--dur:10s;--delay:2s">
            <svg width="28" height="28" viewBox="-3 5 30 15" fill="none">
                <path d="M12 12C12 12 8 2 3 8C-2 10 2 15 6 15C10 15 12 12 12 12Z" fill="rgba(200,230,255,0.2)"/>
                <path d="M12 12C12 12 16 2 21 8C26 10 22 15 18 15C14 15 12 12 12 12Z" fill="rgba(200,230,255,0.2)"/>
            </svg>
        </div>
        <div class="butterfly" style="right:150px;bottom:0;--dx:15px;--ds:1.2;--dur:15s;--delay:5s">
            <svg width="44" height="44" viewBox="-3 5 30 15" fill="none">
                <path d="M12 12C12 12 8 2 3 8C-2 10 2 15 6 15C10 15 12 12 12 12Z" fill="rgba(255,200,220,0.15)"/>
                <path d="M12 12C12 12 16 2 21 8C26 10 22 15 18 15C14 15 12 12 12 12Z" fill="rgba(255,200,220,0.15)"/>
            </svg>
        </div>
        <div class="butterfly" style="left:40%;bottom:0;--dx:-20px;--ds:0.8;--dur:11s;--delay:8s">
            <svg width="24" height="24" viewBox="-3 5 30 15" fill="none">
                <path d="M12 12C12 12 8 2 3 8C-2 10 2 15 6 15C10 15 12 12 12 12Z" fill="rgba(255,255,255,0.2)"/>
                <path d="M12 12C12 12 16 2 21 8C26 10 22 15 18 15C14 15 12 12 12 12Z" fill="rgba(255,255,255,0.2)"/>
            </svg>
        </div>
    </div>

    <!-- Content -->
    <div class="relative z-10 text-center px-6 max-w-4xl w-full anim-1">
        <h1 class="anim-2 text-8xl md:text-9xl font-thin tracking-tighter mb-6 font-serif"
            style="background:linear-gradient(to bottom, #fff, rgba(255,255,255,0.4));-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;">
            Kank
        </h1>

        <p class="anim-3 text-lg md:text-xl font-light tracking-wide mb-12 italic" style="color:rgba(161,161,170,1)">
            "Mesafeler sadece dokunamadığın andadır, hissettiğin an biter."
        </p>

        <div class="anim-4 flex flex-col sm:flex-row gap-6 justify-center items-center">
            <a href="{{ route('login') }}">
                <button class="rounded-full px-8 py-4 text-base font-medium bg-white text-black hover:bg-zinc-200 transition-all duration-500 cursor-pointer"
                    style="box-shadow:0 0 40px -10px rgba(255,255,255,0.3)">
                    Giriş Yap
                </button>
            </a>
            <a href="{{ route('login') }}?mode=signup">
                <button class="rounded-full px-8 py-4 text-base font-light border text-zinc-300 hover:bg-zinc-900 hover:text-white transition-all duration-500 cursor-pointer backdrop-blur-sm"
                    style="border-color:rgba(39,39,42,1)">
                    Aramıza Katıl
                </button>
            </a>
        </div>
    </div>

    <!-- Footer -->
    <div class="anim-footer absolute bottom-10 text-xs tracking-widest uppercase" style="color:rgba(82,82,91,1)">
        Her kanat çırpışı bir fırtına başlatır
    </div>

</body>
</html>
