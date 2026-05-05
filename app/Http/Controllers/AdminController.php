<?php

namespace App\Http\Controllers;

use App\Models\Announcement;
use App\Models\AppRelease;
use App\Models\InviteCode;
use App\Models\Message;
use App\Models\Room;
use App\Models\User;
use App\Services\AdminAuditService;
use App\Support\AppMetrics;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class AdminController extends Controller
{
    public function __construct(private AdminAuditService $audit)
    {
    }

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
        $this->audit->record($request, 'user.ban_toggle', User::class, $user->id, [
            'is_banned' => $user->is_banned,
        ]);

        return response()->json(['is_banned' => $user->is_banned]);
    }

    public function toggleAdmin(Request $request, $userId)
    {
        $user = User::findOrFail($userId);
        if ($user->id === auth()->id()) {
            return response()->json(['error' => 'Kendi rolünü değiştiremezsin'], 403);
        }
        $newRole = $user->role === 'admin' ? 'user' : 'admin';
        $user->update(['role' => $newRole]);
        $this->audit->record($request, 'user.role_toggle', User::class, $user->id, [
            'role' => $newRole,
        ]);

        return response()->json(['role' => $newRole]);
    }

    public function deleteUser(Request $request, $userId)
    {
        $user = User::findOrFail($userId);
        $this->audit->record($request, 'user.delete', User::class, $user->id, [
            'username' => $user->username,
            'role' => $user->role,
        ]);
        $user->delete();

        return response()->json(['ok' => true]);
    }

    public function deleteRoom(Request $request, $roomId)
    {
        $room = Room::findOrFail($roomId);
        $this->audit->record($request, 'room.delete', Room::class, $room->id, [
            'name' => $room->name,
            'type' => $room->type,
        ]);
        $room->delete();

        return response()->json(['ok' => true]);
    }

    public function createInvite(Request $request)
    {
        do {
            $code = 'KNK-'.Str::upper(Str::random(10));
        } while (InviteCode::where('code', $code)->exists());

        $invite = InviteCode::create([
            'code' => $code,
            'expires_at' => now()->addDays(30),
        ]);
        $this->audit->record($request, 'invite.create', InviteCode::class, $invite->id, [
            'expires_at' => $invite->expires_at?->toISOString(),
        ]);

        return response()->json(['code' => $invite->code, 'id' => $invite->id]);
    }

    public function deleteInvite(Request $request, $id)
    {
        $invite = InviteCode::findOrFail($id);
        $this->audit->record($request, 'invite.delete', InviteCode::class, $invite->id, [
            'code' => $invite->code,
            'is_used' => $invite->is_used,
        ]);
        $invite->delete();

        return response()->json(['ok' => true]);
    }

    public function cleanOldMessages(Request $request)
    {
        $announcementRoomIds = Room::where('type', 'announcements')->pluck('id');
        $count = Message::where('created_at', '<', now()->subHours(24))
            ->whereNotIn('room_id', $announcementRoomIds)
            ->delete();
        $this->audit->record($request, 'messages.clean_old', Message::class, null, [
            'deleted' => $count,
            'older_than_hours' => 24,
        ]);

        return response()->json(['deleted' => $count]);
    }

    public function cleanAllMessages(Request $request)
    {
        $announcementRoomIds = Room::where('type', 'announcements')->pluck('id');
        $count = Message::whereNotIn('room_id', $announcementRoomIds)->count();
        Message::whereNotIn('room_id', $announcementRoomIds)->delete();
        $this->audit->record($request, 'messages.clean_all', Message::class, null, [
            'deleted' => $count,
        ]);

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
        $this->audit->record($request, 'announcement.post', Announcement::class, $ann->id, [
            'type' => $ann->type,
            'expires_at' => $ann->expires_at?->toISOString(),
        ]);

        return response()->json(['ok' => true, 'id' => $ann->id]);
    }

    public function clearAnnouncement(Request $request)
    {
        Announcement::query()->update(['is_active' => false]);
        Announcement::clearCache();
        $this->audit->record($request, 'announcement.clear', Announcement::class);

        return response()->json(['ok' => true]);
    }

    public function postAppRelease(Request $request)
    {
        $request->validate([
            'version' => 'required|string|max:50',
            'drive_link' => 'required|url',
            'checksum' => ['nullable', 'string', 'regex:/^[a-fA-F0-9]{64}$/'],
            'notes' => 'nullable|string|max:1000',
        ]);

        if (! $this->isAllowedReleaseUrl($request->drive_link)) {
            return response()->json(['message' => 'Release linki izinli bir domain üzerinden verilmelidir.'], 422);
        }

        $release = AppRelease::create([
            'version' => $request->version,
            'drive_link' => $request->drive_link,
            'checksum' => $request->checksum,
            'notes' => $request->notes,
        ]);
        $this->audit->record($request, 'app_release.post', AppRelease::class, $release->id, [
            'version' => $release->version,
            'drive_link_host' => parse_url($release->drive_link, PHP_URL_HOST),
            'has_checksum' => (bool) $release->checksum,
        ]);

        return response()->json(['ok' => true, 'id' => $release->id]);
    }

    public function toggleMaintenance(Request $request)
    {
        $current = Cache::get('maintenance_mode', false);
        $new = ! $current;
        Cache::forever('maintenance_mode', $new);
        $this->audit->record($request, 'maintenance.toggle', null, null, [
            'maintenance' => $new,
        ]);

        return response()->json(['maintenance' => $new]);
    }

    private function isAllowedReleaseUrl(string $url): bool
    {
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        $allowedHosts = array_filter(array_map('trim', explode(',', config('services.app_release.allowed_hosts', 'drive.google.com,github.com,githubusercontent.com'))));

        foreach ($allowedHosts as $allowedHost) {
            if ($host === strtolower($allowedHost) || str_ends_with($host, '.'.strtolower($allowedHost))) {
                return true;
            }
        }

        return false;
    }
}
