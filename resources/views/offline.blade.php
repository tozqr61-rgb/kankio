<!DOCTYPE html>
<html lang="tr" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kankio — Bağlantı Yok</title>
    <meta name="theme-color" content="#09090b">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            background: #09090b;
            color: #fff;
            font-family: system-ui, -apple-system, sans-serif;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            height: 100dvh;
            gap: 1.5rem;
            padding: 2rem;
            text-align: center;
        }
        .icon {
            width: 72px; height: 72px;
            background: rgba(52,211,153,0.1);
            border: 1px solid rgba(52,211,153,0.25);
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            animation: pulse 2s ease-in-out infinite;
        }
        @keyframes pulse {
            0%, 100% { box-shadow: 0 0 0 0 rgba(52,211,153,0.3); }
            50%       { box-shadow: 0 0 0 12px rgba(52,211,153,0); }
        }
        svg { width: 36px; height: 36px; color: #34d399; }
        h1  { font-size: 1.5rem; font-weight: 300; letter-spacing: 0.05em; color: rgba(255,255,255,0.9); }
        p   { font-size: 0.875rem; color: rgba(255,255,255,0.4); max-width: 28ch; line-height: 1.6; }
        .dots span {
            display: inline-block;
            width: 6px; height: 6px;
            border-radius: 50%;
            background: #34d399;
            margin: 0 3px;
            animation: dot 1.4s ease-in-out infinite;
        }
        .dots span:nth-child(2) { animation-delay: 0.2s; }
        .dots span:nth-child(3) { animation-delay: 0.4s; }
        @keyframes dot { 0%,80%,100%{opacity:0.2} 40%{opacity:1} }
        button {
            padding: 0.625rem 1.5rem;
            border-radius: 0.75rem;
            border: 1px solid rgba(52,211,153,0.3);
            background: rgba(52,211,153,0.1);
            color: #34d399;
            font-size: 0.875rem;
            cursor: pointer;
            transition: background 0.2s;
        }
        button:hover { background: rgba(52,211,153,0.2); }
    </style>
</head>
<body>
    <div class="icon">
        <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
            <path stroke-linecap="round" stroke-linejoin="round"
                  d="M8.288 15.038a5.25 5.25 0 017.424 0M5.106 11.856c3.807-3.808 9.98-3.808 13.788 0M1.924 8.674c5.565-5.565 14.587-5.565 20.152 0M12.53 18.22l-.53.53-.53-.53a.75.75 0 011.06 0z"/>
        </svg>
    </div>

    <div>
        <h1>İnternet Bağlantısı Bekleniyor</h1>
    </div>

    <p>Bağlantı kurulduğunda Kankio otomatik olarak devam edecek.</p>

    <div class="dots">
        <span></span><span></span><span></span>
    </div>

    <button onclick="window.location.reload()">Tekrar Dene</button>

    <script>
        /* Auto-reload when connection restored */
        window.addEventListener('online', () => window.location.reload());
    </script>
</body>
</html>
