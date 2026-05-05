<!DOCTYPE html>
<html lang="tr" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>İsim-Şehir</title>
    @vite('resources/css/app.css')
    <script src="https://cdn.jsdelivr.net/npm/pusher-js@8.4.0/dist/web/pusher.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/laravel-echo@1.16.1/dist/echo.iife.js"></script>
    <script>
        window.__kankioLoadAlpine = () => {
            if (window.__kankioAlpineLoading || window.Alpine) return;
            window.__kankioAlpineLoading = true;
            const script = document.createElement('script');
            script.defer = true;
            script.src = 'https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js';
            document.head.appendChild(script);
        };

        window.KANKIO_PARENT_ORIGIN = window.location.origin;
        window.GAME_EVENTS = {
            CLOSE: 'game:close',
            LOADED: 'game:loaded',
            FINISHED: 'game:session-finished',
            TOAST: 'game:toast',
            ERROR: 'game:error',
        };

        function gamePost(type, payload = {}) {
            window.parent?.postMessage({ type, ...payload }, window.KANKIO_PARENT_ORIGIN);
        }

        function showToast(message, level = 'info') {
            gamePost(window.GAME_EVENTS.TOAST, { message, level });
        }
    </script>
    <style>
        html, body { height: 100%; overflow: hidden; background: #000; color: #fff; }
        [x-cloak] { display: none !important; }
    </style>
</head>
<body>
    @yield('room-content')
    @stack('scripts')
</body>
</html>
