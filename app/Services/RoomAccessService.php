<?php

namespace App\Services;

use App\Models\Room;
use App\Models\User;

class RoomAccessService
{
    public function canAccessRoom(Room $room, User $user): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        if ($room->type !== 'private') {
            return true;
        }

        return $room->members()->where('user_id', $user->id)->exists();
    }

    public function canCreateRoom(User $user, string $type): bool
    {
        if ($type === 'global') {
            return $user->isAdmin();
        }

        return $type === 'private';
    }

    public function canModerateRoom(Room $room, User $user): bool
    {
        if ($user->isAdmin() || (int) $room->created_by === (int) $user->id) {
            return true;
        }

        return $room->members()
            ->where('user_id', $user->id)
            ->wherePivotIn('role', ['owner', 'admin'])
            ->exists();
    }

    public function canJoinVoice(Room $room, User $user): bool
    {
        if (! $this->canAccessRoom($room, $user)) {
            return false;
        }

        if ($user->isAdmin()) {
            return true;
        }

        if ($room->voice_members_only) {
            return $room->members()->where('user_id', $user->id)->exists();
        }

        return true;
    }

    public function canModerateVoice(Room $room, User $user): bool
    {
        return $this->canModerateRoom($room, $user);
    }
}
