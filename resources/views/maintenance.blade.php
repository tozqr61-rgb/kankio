<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bakım Modu — Kankio</title>
    <meta name="description" content="Kankio şu an bakım modunda. Kısa süre içinde geri döneceğiz.">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;800&display=swap" rel="stylesheet">
    <style>
        *{margin:0;padding:0;box-sizing:border-box}
        body{
            font-family:'Inter',system-ui,sans-serif;
            background:#09090b;
            color:#fff;
            min-height:100dvh;
            display:flex;
            align-items:center;
            justify-content:center;
            overflow:hidden;
            position:relative;
        }
        /* Animated gradient orbs */
        .orb{
            position:fixed;
            border-radius:50%;
            filter:blur(100px);
            opacity:0.25;
            animation:float 8s ease-in-out infinite;
        }
        .orb-1{width:400px;height:400px;top:-100px;left:-100px;background:#f43f5e;animation-delay:0s}
        .orb-2{width:350px;height:350px;bottom:-80px;right:-80px;background:#8b5cf6;animation-delay:-3s}
        .orb-3{width:250px;height:250px;top:50%;left:50%;transform:translate(-50%,-50%);background:#06b6d4;animation-delay:-5s}
        @keyframes float{
            0%,100%{transform:translateY(0) scale(1)}
            50%{transform:translateY(-30px) scale(1.05)}
        }
        .container{
            position:relative;
            z-index:1;
            text-align:center;
            max-width:480px;
            padding:2rem;
        }
        .icon{
            font-size:4rem;
            margin-bottom:1.5rem;
            animation:pulse 2s ease-in-out infinite;
        }
        @keyframes pulse{
            0%,100%{transform:scale(1);opacity:1}
            50%{transform:scale(1.1);opacity:0.8}
        }
        h1{
            font-size:2rem;
            font-weight:800;
            letter-spacing:-0.02em;
            margin-bottom:0.75rem;
            background:linear-gradient(135deg,#f43f5e,#a855f7,#06b6d4);
            -webkit-background-clip:text;
            -webkit-text-fill-color:transparent;
            background-clip:text;
        }
        p{
            font-size:1rem;
            color:rgba(255,255,255,0.5);
            line-height:1.7;
            margin-bottom:2rem;
        }
        .status-badge{
            display:inline-flex;
            align-items:center;
            gap:8px;
            padding:8px 20px;
            border-radius:999px;
            background:rgba(255,255,255,0.05);
            border:1px solid rgba(255,255,255,0.08);
            font-size:0.8rem;
            font-weight:600;
            color:rgba(255,255,255,0.6);
            margin-bottom:1.5rem;
        }
        .dot{
            width:8px;height:8px;
            border-radius:50%;
            background:#f59e0b;
            animation:blink 1.5s ease-in-out infinite;
        }
        @keyframes blink{
            0%,100%{opacity:1}50%{opacity:0.3}
        }
        .login-link{
            display:inline-block;
            padding:10px 28px;
            border-radius:12px;
            border:1px solid rgba(255,255,255,0.08);
            color:rgba(255,255,255,0.4);
            text-decoration:none;
            font-size:0.8rem;
            font-weight:600;
            transition:all 0.2s;
        }
        .login-link:hover{
            background:rgba(255,255,255,0.05);
            color:rgba(255,255,255,0.7);
            border-color:rgba(255,255,255,0.15);
        }
    </style>
</head>
<body>
    <div class="orb orb-1"></div>
    <div class="orb orb-2"></div>
    <div class="orb orb-3"></div>

    <div class="container">
        <div class="status-badge"><span class="dot"></span> Bakım Modu Aktif</div>
        <h1>Kankio Şu An Bakımda</h1>
        <p>
            Platformumuzu sizin için daha iyi hale getirmek adına kısa bir bakım çalışması yapıyoruz.
            Lütfen biraz sonra tekrar deneyin. Anlayışınız için teşekkürler! 💜
        </p>
        <!-- Gizli admin girişi: logoya 5 kez tıkla -->
        <div class="secret-tap" @click="tapLogin()" style="cursor:default;user-select:none">🔧</div>
    </div>
    <script>
        let _tc = 0, _tt = null;
        function tapLogin() {
            _tc++;
            clearTimeout(_tt);
            _tt = setTimeout(() => _tc = 0, 2000);
            if (_tc >= 5) { _tc = 0; window.location.href = '/login'; }
        }
        document.querySelector('.secret-tap').addEventListener('click', tapLogin);
    </script>
</body>
</html>
