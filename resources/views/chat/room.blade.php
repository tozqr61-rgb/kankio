@extends('layouts.chat')

@section('chat-content')
@include('chat.partials.overlays')

<div class="flex bg-transparent relative select-none"
     style="height:100dvh;overflow:hidden;"
     x-data="chatRoom()" x-init="init()">
    @include('chat.partials.messages')
    @include('chat.partials.chat-input')
    @include('chat.partials.voice-panel')
	    @include('chat.partials.music-panel')
	    @include('chat.partials.mobile-controls')
	    @include('chat.partials.youtube-player')

	    <div x-show="gameOpen" x-cloak class="fixed inset-0 z-[9700] bg-black" x-transition.opacity>
	        <iframe x-show="gameUrl"
	                :src="gameUrl"
	                title="İsim-Şehir"
	                class="h-full w-full border-0"
	                style="background:#050505"></iframe>
	    </div>
	</div>{{-- end root flex div --}}
@endsection

@push('scripts')
<script>
window.KANKIO_CHAT_BOOTSTRAP = {
    roomId: '{{ $room->id }}',
    currentUser: {!! json_encode(auth()->user()) !!},
    isAdmin: {{ auth()->user()->isAdmin() ? 'true' : 'false' }},
    notificationsEnabled: {{ auth()->user()->notifications_enabled ? 'true' : 'false' }},
    initMsgs: {!! json_encode($initMsgs) !!},
    archivedCount: {{ $archivedCount ?? 0 }},
    roomType: '{{ $room->type }}',
    roomName: {!! json_encode($room->name) !!},
    broadcastDriver: '{{ config('broadcasting.default', 'null') }}',
    broadcastConfig: {!! json_encode([
        'pusher' => [
            'key'     => config('broadcasting.connections.pusher.key'),
            'cluster' => config('broadcasting.connections.pusher.options.cluster', 'eu'),
            'host'    => config('broadcasting.connections.pusher.options.host'),
            'port'    => config('broadcasting.connections.pusher.options.port', 443),
            'scheme'  => config('broadcasting.connections.pusher.options.scheme', 'https'),
        ],
        'reverb' => [
            'key'    => config('broadcasting.connections.reverb.key'),
            'host'   => config('broadcasting.connections.reverb.options.host', 'localhost'),
            'port'   => config('broadcasting.connections.reverb.options.port', 8080),
            'scheme' => config('broadcasting.connections.reverb.options.scheme', 'http'),
        ],
    ]) !!},
};
</script>
@vite('resources/js/chat-room.js')
@endpush
