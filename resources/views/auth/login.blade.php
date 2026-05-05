<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Kankio – Giriş</title>
    <meta name="theme-color" content="#09090b">
    @vite('resources/css/app.css')
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=EB+Garamond:ital,wght@0,400;0,300;1,400&display=swap');
        .font-serif { font-family: 'EB Garamond', Georgia, serif; }
        body { background: #000; overflow: hidden; }
        @keyframes pulseSlow { 0%,100%{opacity:0.3}50%{opacity:0.5} }
        .animate-pulse-slow { animation: pulseSlow 6s ease-in-out infinite; }
        @keyframes flyUp {
            0%   { opacity: 0; transform: translate(0, 0) scale(1); }
            20%  { opacity: 0.5; }
            80%  { opacity: 0.3; }
            100% { opacity: 0; transform: translate(var(--dx), -160px) scale(var(--ds)); }
        }
        .butterfly { position:absolute; pointer-events:none; animation: flyUp var(--dur,12s) ease-in-out var(--delay,0s) infinite; }
        @keyframes slideUp { from{opacity:0;transform:translateY(20px)}to{opacity:1;transform:translateY(0)} }
        .slide-up { animation: slideUp 0.3s ease-out forwards; }
    </style>
</head>
<body class="relative min-h-screen w-full flex flex-col items-center justify-center overflow-hidden bg-black text-white"
      x-data="loginApp()" x-init="init()">

    <!-- Ambient -->
    <div class="fixed inset-0 pointer-events-none z-0">
        <div class="absolute top-1/4 left-1/4 w-96 h-96 rounded-full blur-[100px] animate-pulse-slow" style="background:rgba(59,130,246,0.07)"></div>
        <div class="absolute bottom-1/4 right-1/4 w-96 h-96 rounded-full blur-[100px] animate-pulse-slow" style="background:rgba(168,85,247,0.07)"></div>
    </div>
    <!-- Butterflies -->
    <div class="absolute inset-0 overflow-hidden pointer-events-none z-0">
        <div class="butterfly" style="left:80px;bottom:0;--dx:20px;--ds:1.1;--dur:12s;--delay:0s"><svg width="32" height="32" viewBox="-3 5 30 15" fill="none"><path d="M12 12C12 12 8 2 3 8C-2 10 2 15 6 15C10 15 12 12 12 12Z" fill="rgba(255,255,255,0.2)"/><path d="M12 12C12 12 16 2 21 8C26 10 22 15 18 15C14 15 12 12 12 12Z" fill="rgba(255,255,255,0.2)"/></svg></div>
        <div class="butterfly" style="right:100px;bottom:0;--dx:-15px;--ds:0.8;--dur:9s;--delay:3s"><svg width="24" height="24" viewBox="-3 5 30 15" fill="none"><path d="M12 12C12 12 8 2 3 8C-2 10 2 15 6 15C10 15 12 12 12 12Z" fill="rgba(200,200,255,0.15)"/><path d="M12 12C12 12 16 2 21 8C26 10 22 15 18 15C14 15 12 12 12 12Z" fill="rgba(200,200,255,0.15)"/></svg></div>
    </div>

    <!-- Form Card -->
    <div class="relative z-10 w-full max-w-sm px-4">
        <div class="rounded-2xl border p-8 shadow-2xl" style="background:rgba(0,0,0,0.4);backdrop-filter:blur(24px);border-color:rgba(255,255,255,0.08)">

            <h2 class="text-2xl font-serif tracking-wider text-center mb-1"
                x-text="isLogin ? 'Kank\'a Dön' : 'Aramıza Katıl'"></h2>
            <p class="text-center text-sm mb-6" style="color:rgba(255,255,255,0.35)"
                x-text="isLogin ? 'Kaldığın yerden devam et.' : 'Davetiye kodunla maceraya başla.'"></p>

            <!-- Toggle -->
            <div class="flex w-full items-center justify-center p-1 rounded-full mb-6" style="background:rgba(255,255,255,0.05)">
                <button type="button" @click="isLogin=true"
                    :class="isLogin ? 'bg-white text-black font-medium' : 'text-zinc-400 hover:text-white'"
                    class="flex-1 text-sm py-2 rounded-full transition-all">Giriş</button>
                <button type="button" @click="isLogin=false"
                    :class="!isLogin ? 'bg-white text-black font-medium' : 'text-zinc-400 hover:text-white'"
                    class="flex-1 text-sm py-2 rounded-full transition-all">Kayıt</button>
            </div>

            <!-- Login Form -->
            <form x-show="isLogin" @submit.prevent="submitLogin()" class="space-y-4">
                @if ($errors->any())
                    <div class="text-xs text-center p-2 rounded text-rose-400" style="background:rgba(255,255,255,0.05)">
                        {{ $errors->first() }}
                    </div>
                @endif
                <div class="space-y-1">
                    <label class="text-xs text-zinc-400 block">Kullanıcı Adı</label>
                    <input x-model="username" name="username" type="text" placeholder="kank" autocomplete="username"
                        class="w-full rounded-lg px-3 py-2.5 text-sm text-white placeholder-zinc-600 border outline-none transition-colors"
                        style="background:rgba(255,255,255,0.05);border-color:rgba(255,255,255,0.08)"
                        onfocus="this.style.borderColor='rgba(255,255,255,0.2)'" onblur="this.style.borderColor='rgba(255,255,255,0.08)'">
                </div>
                <div class="space-y-1">
                    <label class="text-xs text-zinc-400 block">Şifre</label>
                    <input x-model="password" name="password" type="password" autocomplete="current-password"
                        class="w-full rounded-lg px-3 py-2.5 text-sm text-white placeholder-zinc-600 border outline-none transition-colors"
                        style="background:rgba(255,255,255,0.05);border-color:rgba(255,255,255,0.08)"
                        onfocus="this.style.borderColor='rgba(255,255,255,0.2)'" onblur="this.style.borderColor='rgba(255,255,255,0.08)'">
                </div>
                <div x-show="loginError" class="text-xs text-center p-2 rounded text-rose-400" style="background:rgba(255,255,255,0.05)" x-text="loginError"></div>
                <button type="submit" :disabled="loading"
                    class="w-full py-2.5 rounded-lg bg-white text-black font-medium text-sm hover:bg-zinc-200 transition-all disabled:opacity-60">
                    <span x-show="!loading">Giriş Yap</span>
                    <span x-show="loading">İşleniyor...</span>
                </button>
            </form>

            <!-- Register Form -->
            <form x-show="!isLogin" @submit.prevent="submitRegister()" class="space-y-4">
                <div class="space-y-1">
                    <label class="text-xs text-zinc-400 block">Kullanıcı Adı</label>
                    <input x-model="username" type="text" placeholder="kank" autocomplete="username"
                        class="w-full rounded-lg px-3 py-2.5 text-sm text-white placeholder-zinc-600 border outline-none transition-colors"
                        style="background:rgba(255,255,255,0.05);border-color:rgba(255,255,255,0.08)"
                        onfocus="this.style.borderColor='rgba(255,255,255,0.2)'" onblur="this.style.borderColor='rgba(255,255,255,0.08)'">
                </div>
                <div class="space-y-1">
                    <label class="text-xs text-zinc-400 block">Şifre</label>
                    <input x-model="password" type="password" autocomplete="new-password"
                        class="w-full rounded-lg px-3 py-2.5 text-sm text-white placeholder-zinc-600 border outline-none transition-colors"
                        style="background:rgba(255,255,255,0.05);border-color:rgba(255,255,255,0.08)"
                        onfocus="this.style.borderColor='rgba(255,255,255,0.2)'" onblur="this.style.borderColor='rgba(255,255,255,0.08)'">
                </div>
                <div class="space-y-1">
                    <label class="text-xs text-emerald-400 block">Davetiye Kodu</label>
                    <div class="relative">
                        <svg class="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-emerald-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z"/></svg>
                        <input x-model="inviteCode" type="text" placeholder="KNK-XXXXX" autocomplete="off"
                            class="w-full pl-9 rounded-lg px-3 py-2.5 text-sm text-emerald-100 placeholder-emerald-500 border outline-none transition-colors"
                            style="background:rgba(16,185,129,0.08);border-color:rgba(16,185,129,0.2)"
                            onfocus="this.style.borderColor='rgba(16,185,129,0.5)'" onblur="this.style.borderColor='rgba(16,185,129,0.2)'">
                    </div>
                </div>
                <div x-show="registerError" class="text-xs text-center p-2 rounded text-rose-400" style="background:rgba(255,255,255,0.05)" x-text="registerError"></div>
                <div x-show="registerSuccess" class="text-xs text-center p-2 rounded text-emerald-400" style="background:rgba(255,255,255,0.05)" x-text="registerSuccess"></div>
                <button type="submit" :disabled="loading"
                    class="w-full py-2.5 rounded-lg bg-white text-black font-medium text-sm hover:bg-zinc-200 transition-all disabled:opacity-60">
                    <span x-show="!loading">Davetiye ile Katıl</span>
                    <span x-show="loading">İşleniyor...</span>
                </button>
            </form>
        </div>
    </div>

    <script>
    function loginApp() {
        return {
            isLogin: {{ request('mode') === 'signup' ? 'false' : 'true' }},
            username: '',
            password: '',
            inviteCode: '',
            loading: false,
            loginError: '',
            registerError: '',
            registerSuccess: '',

            init() {},

            async submitLogin() {
                this.loading = true;
                this.loginError = '';
                try {
                    const form = document.createElement('form');
                    form.method = 'POST';
                    form.action = '{{ route("login.post") }}';
                    form.innerHTML = `
                        <input name="_token" value="{{ csrf_token() }}">
                        <input name="username" value="${this.username}">
                        <input name="password" value="${this.password}">
                    `;
                    document.body.appendChild(form);
                    form.submit();
                } catch(e) {
                    this.loginError = 'Bir hata oluştu.';
                    this.loading = false;
                }
            },

            async submitRegister() {
                this.loading = true;
                this.registerError = '';
                this.registerSuccess = '';
                try {
                    const form = document.createElement('form');
                    form.method = 'POST';
                    form.action = '{{ route("register.post") }}';
                    form.innerHTML = `
                        <input name="_token" value="{{ csrf_token() }}">
                        <input name="username" value="${this.username}">
                        <input name="password" value="${this.password}">
                        <input name="password_confirmation" value="${this.password}">
                        <input name="invite_code" value="${this.inviteCode}">
                    `;
                    document.body.appendChild(form);
                    form.submit();
                } catch(e) {
                    this.registerError = 'Bir hata oluştu.';
                    this.loading = false;
                }
            }
        }
    }
    </script>
</body>
</html>
