@extends('layouts.chat')

@section('chat-content')
<div class="flex-1 min-w-0 h-full bg-black text-white overflow-hidden">
    @yield('room-content')
</div>
@endsection
