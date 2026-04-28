<?php

use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\DB;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

/* ── Private: each user's WebRTC signal inbox ── */
Broadcast::channel('voice.signal.{userId}', function ($user, $userId) {
    return (int) $user->id === (int) $userId;
});

/* ── Presence: real-time room participant tracking ── */
Broadcast::channel('presence-room.{roomId}', function ($user, $roomId) {
    $room = DB::table('rooms')->where('id', $roomId)->first();
    if (! $room) return false;
    return [
        'id'         => $user->id,
        'username'   => $user->username,
        'avatar_url' => $user->avatar_url,
    ];
});

/* ── Music control: only room owner or admin can change track ── */
Broadcast::channel('room.{roomId}.music.control', function ($user, $roomId) {
    $room = DB::table('rooms')->where('id', $roomId)->first();
    return $room && ((int) $room->created_by === (int) $user->id || $user->isAdmin());
});
