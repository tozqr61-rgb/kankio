<?php

use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\DB;

if (! function_exists('canAccessBroadcastRoom')) {
    function canAccessBroadcastRoom($user, int $roomId): bool
    {
        $room = DB::table('rooms')->where('id', $roomId)->first();
        if (! $room) {
            return false;
        }
        if ($room->type !== 'private') {
            return true;
        }
        if (method_exists($user, 'isAdmin') && $user->isAdmin()) {
            return true;
        }

        return DB::table('room_members')
            ->where('room_id', $roomId)
            ->where('user_id', $user->id)
            ->exists();
    }
}

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

/* ── Private: each user's WebRTC signal inbox ── */
Broadcast::channel('voice.signal.{userId}', function ($user, $userId) {
    return (int) $user->id === (int) $userId;
});

/* ── Presence: real-time room participant tracking ── */
Broadcast::channel('presence-room.{roomId}', function ($user, $roomId) {
    if (($user->presence_mode ?? 'online') === 'invisible') {
        return false;
    }

    $room = DB::table('rooms')->where('id', $roomId)->first();
    if (! $room) {
        return false;
    }

    return [
        'id' => $user->id,
        'username' => $user->username,
        'avatar_url' => $user->avatar_url,
    ];
});

Broadcast::channel('room.{roomId}.chat', function ($user, $roomId) {
    return canAccessBroadcastRoom($user, (int) $roomId);
});

Broadcast::channel('room.{roomId}.voice', function ($user, $roomId) {
    return canAccessBroadcastRoom($user, (int) $roomId);
});

Broadcast::channel('room.{roomId}.music', function ($user, $roomId) {
    return canAccessBroadcastRoom($user, (int) $roomId);
});

Broadcast::channel('room.{roomId}.game', function ($user, $roomId) {
    return canAccessBroadcastRoom($user, (int) $roomId);
});

/* ── Music control: only room owner or admin can change track ── */
Broadcast::channel('room.{roomId}.music.control', function ($user, $roomId) {
    $room = DB::table('rooms')->where('id', $roomId)->first();

    return $room && ((int) $room->created_by === (int) $user->id || $user->isAdmin());
});
