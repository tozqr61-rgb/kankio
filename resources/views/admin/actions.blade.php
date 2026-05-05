@extends('layouts.admin')

@section('admin-content')
<div class="space-y-5">
    <div>
        <h1 class="text-3xl font-bold tracking-tight text-white">Audit Log</h1>
        <p class="mt-2 text-sm text-zinc-400">Admin, denetim ve bakım işlemleri burada kalıcı olarak izlenir.</p>
    </div>

    <form method="GET" class="flex flex-wrap gap-2">
        <input name="actor" value="{{ request('actor') }}" placeholder="Actor"
               class="px-3 py-2 rounded-xl border text-sm text-white"
               style="background:rgba(255,255,255,0.04);border-color:rgba(255,255,255,0.08)">
        <input name="action" value="{{ request('action') }}" placeholder="Action"
               class="px-3 py-2 rounded-xl border text-sm text-white"
               style="background:rgba(255,255,255,0.04);border-color:rgba(255,255,255,0.08)">
        <button class="px-4 py-2 rounded-xl text-sm font-semibold"
                style="background:rgba(16,185,129,0.18);color:rgba(110,231,183,1)">Filtrele</button>
    </form>

    <div class="space-y-2">
        @foreach($actions as $action)
        <div class="rounded-xl border p-4" style="background:rgba(255,255,255,0.03);border-color:rgba(255,255,255,0.07)">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <p class="text-sm font-semibold text-white">{{ $action->action }}</p>
                    <p class="text-xs text-zinc-500">
                        {{ $action->actor?->username ?? 'Sistem' }} • {{ $action->created_at->format('d.m.Y H:i') }} • {{ $action->ip_address }}
                    </p>
                </div>
                <p class="text-xs text-zinc-500">{{ class_basename($action->target_type ?? '') }} #{{ $action->target_id ?? '-' }}</p>
            </div>
            @if($action->payload)
            <pre class="mt-3 overflow-x-auto rounded-lg p-3 text-xs text-zinc-300" style="background:rgba(0,0,0,0.28)">{{ json_encode($action->payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) }}</pre>
            @endif
        </div>
        @endforeach
    </div>

    {{ $actions->links() }}
</div>
@endsection
