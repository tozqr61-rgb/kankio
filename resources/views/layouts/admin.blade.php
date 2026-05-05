<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kankio Admin</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @vite('resources/css/app.css')
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=EB+Garamond:wght@400;300&display=swap');
        .font-serif { font-family: 'EB Garamond', Georgia, serif; }
        ::-webkit-scrollbar { width: 5px; } ::-webkit-scrollbar-track { background: transparent; } ::-webkit-scrollbar-thumb { background: #27272a; border-radius: 9999px; }
    </style>
</head>
<body class="flex h-screen bg-gray-950 text-white overflow-hidden">

    <!-- Sidebar -->
    <aside class="w-64 bg-zinc-900 border-r border-white/5 p-4 flex flex-col gap-1 shrink-0">
        <div class="mb-5 px-2">
            <h2 class="text-lg font-bold text-white flex items-center gap-2">
                <svg class="h-5 w-5 text-rose-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.955 11.955 0 01.25 12c0 6.627 5.373 12 12 12 6.627 0 12-5.373 12-12 0-2.285-.638-4.423-1.748-6.253M9 12.75L11.25 15 15 9.75"/>
                </svg>
                Admin Panel
            </h2>
        </div>
        @php $current = request()->route()->getName(); @endphp
        @foreach([
            ['admin.dashboard', route('admin.dashboard'), 'Dashboard', 'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6'],
            ['admin.users', route('admin.users'), 'Kullanıcılar', 'M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z'],
            ['admin.rooms', route('admin.rooms'), 'Odalar', 'M2.25 12.76c0 1.6 1.123 2.994 2.707 3.227 1.087.16 2.185.283 3.293.369V21l4.076-4.076a1.526 1.526 0 011.037-.443 48.282 48.282 0 005.68-.494c1.584-.233 2.707-1.626 2.707-3.228V6.741c0-1.602-1.123-2.995-2.707-3.228A48.394 48.394 0 0012 3c-2.392 0-4.744.175-7.043.513C3.373 3.746 2.25 5.14 2.25 6.741v6.018z'],
            ['admin.invites', route('admin.invites'), 'Davetiyeler', 'M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z'],
        ] as [$route, $href, $label, $icon])
        <a href="{{ $href }}"
           class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm transition-all {{ $current === $route ? 'bg-white/10 text-white font-medium' : 'text-zinc-400 hover:text-white hover:bg-white/5' }}">
            <svg class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="{{ $icon }}"/>
            </svg>
            {{ $label }}
        </a>
        @endforeach

        <div class="mt-auto pt-4 border-t border-white/5">
            <a href="{{ route('chat.index') }}" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm text-zinc-400 hover:text-white hover:bg-white/5 transition-all">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18"/>
                </svg>
                Uygulamaya Dön
            </a>
        </div>
    </aside>

    <!-- Main -->
    <main class="flex-1 overflow-y-auto p-8 bg-gray-950">
        @yield('admin-content')
    </main>

    <div id="toast-container" class="fixed bottom-6 right-6 z-[100] space-y-2 pointer-events-none"></div>

    <script>
    const CSRF    = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
    const APP_URL = '{{ rtrim(url("/"), "/") }}';
    function showToast(msg, type = 'success') {
        const el = document.createElement('div');
        el.className = `px-4 py-3 rounded-xl text-sm font-medium shadow-2xl pointer-events-auto border ${type === 'success' ? 'bg-zinc-900 border-emerald-500/30 text-emerald-400' : 'bg-zinc-900 border-rose-500/30 text-rose-400'}`;
        document.getElementById('toast-container').appendChild(el);
        el.textContent = msg;
        setTimeout(() => { el.style.opacity = '0'; el.style.transition = 'opacity 0.3s'; setTimeout(() => el.remove(), 300); }, 3000);
    }
    </script>
    @stack('admin-scripts')
</body>
</html>
