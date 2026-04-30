<?php

namespace App\Http\Controllers;

use App\Models\Announcement;
use App\Models\AppRelease;
use App\Models\InviteCode;
use App\Models\Message;
use App\Models\Room;
use App\Models\User;
use App\Support\AppMetrics;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class AdminController extends Controller
{
    public function dashboard()
    {
        $userCount = User::count();
        $msgCount = Message::count();
        $roomCount = Room::count();
        $metrics = AppMetrics::snapshot();

        return view('admin.dashboard', compact('userCount', 'msgCount', 'roomCount', 'metrics'));
    }

    public function metrics()
    {
        return response()->json(AppMetrics::snapshot());
    }

    public function users()
    {
        $users = User::orderBy('created_at', 'desc')->get();

        return view('admin.users', compact('users'));
    }

    public function rooms()
    {
        $rooms = Room::with('creator')->orderBy('created_at', 'desc')->get();

        return view('admin.rooms', compact('rooms'));
    }

    public function invites()
    {
        $codes = InviteCode::orderBy('created_at', 'desc')->get();

        return view('admin.invites', compact('codes'));
    }

    // API Actions
    public function banUser(Request $request, $userId)
    {
        $user = User::findOrFail($userId);
        $user->update(['is_banned' => ! $user->is_banned]);

        return response()->json(['is_banned' => $user->is_banned]);
    }

    public function toggleAdmin($userId)
    {
        $user = User::findOrFail($userId);
        if ($user->id === auth()->id()) {
            return response()->json(['error' => 'Kendi rolünü değiştiremezsin'], 403);
        }
        $newRole = $user->role === 'admin' ? 'user' : 'admin';
        $user->update(['role' => $newRole]);

        return response()->json(['role' => $newRole]);
    }

    public function deleteUser($userId)
    {
        $user = User::findOrFail($userId);
        $user->delete();

        return response()->json(['ok' => true]);
    }

    public function deleteRoom($roomId)
    {
        Room::findOrFail($roomId)->delete();

        return response()->json(['ok' => true]);
    }

    public function createInvite()
    {
        $code = 'KNK-'.strtoupper(substr(str_shuffle('ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'), 0, 5));

        $invite = InviteCode::create([
            'code' => $code,
            'expires_at' => now()->addDays(30),
        ]);

        return response()->json(['code' => $invite->code, 'id' => $invite->id]);
    }

    public function deleteInvite($id)
    {
        InviteCode::findOrFail($id)->delete();

        return response()->json(['ok' => true]);
    }

    public function cleanOldMessages()
    {
        $announcementRoomIds = Room::where('type', 'announcements')->pluck('id');
        $count = Message::where('created_at', '<', now()->subHours(24))
            ->whereNotIn('room_id', $announcementRoomIds)
            ->delete();

        return response()->json(['deleted' => $count]);
    }

    public function cleanAllMessages()
    {
        $announcementRoomIds = Room::where('type', 'announcements')->pluck('id');
        $count = Message::whereNotIn('room_id', $announcementRoomIds)->count();
        Message::whereNotIn('room_id', $announcementRoomIds)->delete();

        return response()->json(['deleted' => $count]);
    }

    public function postAnnouncement(Request $request)
    {
        $request->validate([
            'message' => 'required|string|max:500',
            'type' => 'required|in:info,warning,danger',
            'expires_at' => 'nullable|date|after:now',
        ]);

        Announcement::query()->update(['is_active' => false]);

        $ann = Announcement::create([
            'message' => $request->message,
            'type' => $request->type,
            'is_active' => true,
            'expires_at' => $request->expires_at ?: null,
        ]);

        Announcement::clearCache();

        return response()->json(['ok' => true, 'id' => $ann->id]);
    }

    public function clearAnnouncement()
    {
        Announcement::query()->update(['is_active' => false]);
        Announcement::clearCache();

        return response()->json(['ok' => true]);
    }

    public function postAppRelease(Request $request)
    {
        $request->validate([
            'version' => 'required|string|max:50',
            'drive_link' => 'required|url',
            'notes' => 'nullable|string|max:1000',
        ]);

        $release = AppRelease::create([
            'version' => $request->version,
            'drive_link' => $request->drive_link,
            'notes' => $request->notes,
        ]);

        return response()->json(['ok' => true, 'id' => $release->id]);
    }

    public function toggleMaintenance()
    {
        $current = Cache::get('maintenance_mode', false);
        $new = ! $current;
        Cache::forever('maintenance_mode', $new);

        return response()->json(['maintenance' => $new]);
    }
}
