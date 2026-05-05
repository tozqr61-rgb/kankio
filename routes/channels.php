<?php

use App\Models\Room;
use App\Services\RoomAccessService;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\DB;

if (! function_exists('canAccessBroadcastRoom')) {
    function canAccessBroadcastRoom($user, int $roomId): bool
    {
        $room = Room::find($roomId);
        if (! $room) {
            return false;
        }

        return app(RoomAccessService::class)->canAccessRoom($room, $user);
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

    if (! canAccessBroadcastRoom($user, (int) $roomId)) {
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
