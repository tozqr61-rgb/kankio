<?php

namespace App\Http\Controllers;

use App\Events\MessagesRead;
use App\Events\StayConnectedSurpriseTriggered;
use App\Events\UserTyping;
use App\Models\Message;
use App\Models\Room;
use App\Support\AppMetrics;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class ChatController extends Controller
{
    public function index()
    {
        $user = Auth::user();

        // Redirect to first accessible room automatically
        $firstRoom = Room::where('type', 'global')
            ->orderBy('id', 'asc')
            ->first();

        if ($firstRoom) {
            return redirect()->route('chat.room', $firstRoom->id);
        }

        $rooms = $this->getAccessibleRooms($user);
        $dms = $user->rooms()->where('type', 'dm')->get();
        $onlineUsers = $this->getOnlineUsers();

        return view('chat.index', compact('user', 'rooms', 'dms', 'onlineUsers'));
    }

    public function room(Request $request, $roomId)
    {
        $user = Auth::user();
        $room = Room::findOrFail($roomId);

        // Access check for private rooms
        if ($room->type === 'private') {
            $isMember = $room->members()->where('user_id', $user->id)->exists();
            if (! $isMember && ! $user->isAdmin()) {
                abort(403, 'Bu odaya erişim izniniz yok.');
            }
        }

        $messages = Message::where('room_id', $roomId)
            ->where('is_archived', false)
            ->with(['sender', 'replyToMessage.sender'])
            ->orderBy('created_at', 'asc')
            ->limit(100)
            ->get();

        $initMsgs = $messages->map(function ($m) {
            return $this->formatMessage($m);
        })->values()->toArray();

        $archivedCount = Message::where('room_id', $roomId)->where('is_archived', true)->count();

        $rooms = $this->getAccessibleRooms($user);
        $dms = $user->rooms()->where('type', 'dm')->get();
        $onlineUsers = $this->getOnlineUsers();

        // Mark as read
        \DB::table('room_reads')->updateOrInsert(
            ['user_id' => $user->id, 'room_id' => $roomId],
            ['last_read_at' => now()]
        );

        return view('chat.room', compact('user', 'room', 'initMsgs', 'archivedCount', 'rooms', 'dms', 'onlineUsers'));
    }

    public function roomFrame(Request $request, $roomId)
    {
        $user = Auth::user();
        $room = Room::findOrFail($roomId);

        if (! $this->canAccessRoom($room, $user)) {
            return response()->json(['error' => 'Erişim reddedildi'], 403);
        }

        $messages = Message::where('room_id', $roomId)
            ->where('is_archived', false)
            ->with(['sender', 'replyToMessage.sender'])
            ->orderBy('created_at', 'asc')
            ->limit(100)
            ->get();

        $msgs = $messages->map(function ($m) {
            return $this->formatMessage($m);
        })->values()->toArray();

        $musicState = \Cache::get("music_state_{$roomId}", [
            'video_id' => null, 'video_title' => null,
            'is_playing' => false, 'position' => 0, 'queue' => [],
        ]);

        \DB::table('room_reads')->updateOrInsert(
            ['user_id' => $user->id, 'room_id' => $roomId],
            ['last_read_at' => now()]
        );

        return response()->json([
            'id' => $room->id,
            'name' => $room->name,
            'type' => $room->type,
            'messages' => $msgs,
            'music_state' => $musicState,
            'archived_count' => Message::where('room_id', $roomId)->where('is_archived', true)->count(),
        ]);
    }

    public function bootstrap(Request $request, $roomId)
    {
        $user = Auth::user();
        $room = Room::findOrFail($roomId);

        if (! $this->canAccessRoom($room, $user)) {
            return response()->json(['error' => 'Erişim reddedildi'], 403);
        }

        $messages = Message::where('room_id', $roomId)
            ->where('is_archived', false)
            ->with(['sender', 'replyToMessage.sender'])
            ->orderBy('created_at', 'desc')
            ->limit(100)
            ->get()
            ->reverse()
            ->values();

        \DB::table('room_reads')->updateOrInsert(
            ['user_id' => $user->id, 'room_id' => $roomId],
            ['last_read_at' => now()]
        );

        return response()->json([
            'room' => [
                'id' => $room->id,
                'name' => $room->name,
                'type' => $room->type,
            ],
            'messages' => $messages->map(function ($m) {
                return $this->formatMessage($m);
            })->values()->toArray(),
            'archived_count' => Message::where('room_id', $roomId)->where('is_archived', true)->count(),
        ]);
    }

    public function poll(Request $request, $roomId)
    {
        $user = Auth::user();
        $room = Room::findOrFail($roomId);

        if (! $this->canAccessRoom($room, $user)) {
            return response()->json(['error' => 'Erişim reddedildi'], 403);
        }

        /* Mark room as read while actively polling */
        if (Auth::check()) {
            \DB::table('room_reads')->updateOrInsert(
                ['user_id' => Auth::id(), 'room_id' => $roomId],
                ['last_read_at' => now()]
            );
        }

        $since = $request->query('since');
        $query = Message::where('room_id', $roomId)->where('is_archived', false)->with(['sender', 'replyToMessage']);

        if ($since) {
            $query->where('created_at', '>=', $since)->limit(200);
        } else {
            $query->orderBy('created_at', 'asc')->limit(100);
        }

        $messages = $query->orderBy('created_at', 'asc')->get();

        return response()->json($messages->map(function ($m) {
            return $this->formatMessage($m);
        }));
    }

    private function getAccessibleRooms($user)
    {
        return Room::where('type', '!=', 'dm')
            ->where(function ($q) use ($user) {
                $q->where('type', 'global')
                    ->orWhere('type', 'announcements')
                    ->orWhere(function ($q2) use ($user) {
                        $q2->where('type', 'private')
                            ->whereHas('members', fn ($q3) => $q3->where('user_id', $user->id));
                    });
            })
            ->orderBy('created_at', 'asc')
            ->get();
    }

    private function canAccessRoom($room, $user): bool
    {
        if ($room->type !== 'private') {
            return true;
        }

        return $user->isAdmin() || $room->members()->where('user_id', $user->id)->exists();
    }

    private function getOnlineUsers(): array
    {
        return \Cache::get('online_users', []);
    }

    private function formatMessage($m): array
    {
        return [
            'id' => $m->id,
            'title' => $m->title,
            'content' => $m->content,
            'audio_url' => $m->audio_url,
            'audio_duration' => $m->audio_duration,
            'is_system_message' => (bool) $m->is_system_message,
            'reply_to' => $m->reply_to,
            'reply_message' => $m->replyToMessage ? [
                'content' => $m->replyToMessage->content,
                'username' => $m->replyToMessage->sender?->username ?? 'Silinmiş',
            ] : null,
            'read_count' => $m->reads_count ?? 0,
            'created_at' => $m->created_at->toISOString(),
            'sender' => $m->sender ? [
                'id' => $m->sender->id,
                'username' => $m->sender->username,
                'avatar_url' => $m->sender->avatar_url,
                'role' => $m->sender->role,
            ] : null,
        ];
    }

    public function updatePresence(Request $request)
    {
        $user = Auth::user();
        $status = $request->input('status', 'online');
        $onlineUsers = \Cache::get('online_users', []);

        if ($status === 'offline') {
            /* Immediately remove user on explicit offline signal */
            unset($onlineUsers[$user->id]);
            \Cache::put('online_users', array_values($onlineUsers), 300);

            return response()->json(['ok' => true, 'users' => array_values($onlineUsers)]);
        }

        $onlineUsers[$user->id] = [
            'id' => $user->id,
            'username' => $user->username,
            'avatar_url' => $user->avatar_url,
            'role' => $user->role,
            'status' => 'online',
            'last_seen' => now()->timestamp,
        ];

        /* Remove users with no heartbeat for > 45 s (heartbeat period is 15 s) */
        $now = now()->timestamp;
        foreach ($onlineUsers as $uid => $u) {
            if (($now - ($u['last_seen'] ?? 0)) >= 45) {
                unset($onlineUsers[$uid]);
            }
        }

        /* Store with ID keys so we can upsert without duplicates */
        \Cache::put('online_users', $onlineUsers, 300);

        $user->update(['last_seen_at' => now()]);

        return response()->json(['ok' => true, 'users' => array_values($onlineUsers)]);
    }

    public function unreadCounts()
    {
        $userId = Auth::id();
        $user = Auth::user();
        $rooms = $this->getAccessibleRooms($user);
        $counts = [];

        foreach ($rooms as $room) {
            $lastRead = \DB::table('room_reads')
                ->where('user_id', $userId)
                ->where('room_id', $room->id)
                ->value('last_read_at');

            $q = Message::where('room_id', $room->id)
                ->where('is_system_message', false)
                ->where('sender_id', '!=', $userId);

            if ($lastRead) {
                $q->where('created_at', '>', $lastRead);
            } else {
                /* Never opened this room — only count last 24h */
                $q->where('created_at', '>', now()->subDay());
            }

            $cnt = $q->count();
            if ($cnt > 0) {
                $counts[(string) $room->id] = $cnt;
            }
        }

        return response()->json($counts);
    }

    public function archivedMessages(Request $request, $roomId)
    {
        $user = Auth::user();
        $room = Room::findOrFail($roomId);

        if (! $this->canAccessRoom($room, $user)) {
            return response()->json(['error' => 'Erişim reddedildi'], 403);
        }

        $page = (int) $request->query('page', 1);
        $messages = Message::where('room_id', $roomId)
            ->where('is_archived', true)
            ->with(['sender', 'replyToMessage.sender'])
            ->orderBy('created_at', 'desc')
            ->paginate(50, ['*'], 'page', $page);

        return response()->json([
            'messages' => collect($messages->items())->map(fn ($m) => $this->formatMessage($m)),
            'has_more' => $messages->hasMorePages(),
            'total' => $messages->total(),
        ]);
    }

    public function markSeen(Request $request, $roomId)
    {
        $user = Auth::user();
        $room = Room::findOrFail($roomId);

        if (! $this->canAccessRoom($room, $user)) {
            return response()->json(['error' => 'Erişim reddedildi'], 403);
        }

        $messageIds = $request->input('message_ids', []);
        if (empty($messageIds)) {
            return response()->json(['ok' => true]);
        }

        $messages = Message::where('room_id', $roomId)
            ->whereIn('id', $messageIds)
            ->where('sender_id', '!=', $user->id)
            ->pluck('id');

        $inserts = $messages->map(fn ($id) => [
            'message_id' => $id,
            'user_id' => $user->id,
            'read_at' => now(),
        ])->toArray();

        if (! empty($inserts)) {
            \DB::table('message_reads')->upsert(
                $inserts,
                ['message_id', 'user_id'],
                ['read_at']
            );

            try {
                broadcast(new MessagesRead((int) $roomId, (int) $user->id, array_values($messages->all())));
            } catch (\Throwable $e) {
                Log::warning('chat.broadcast.read_receipt_failed', [
                    'room_id' => (int) $roomId,
                    'user_id' => $user->id,
                    'error' => $e->getMessage(),
                ]);
                AppMetrics::increment('external_service_errors_total', ['service' => 'reverb', 'reason' => 'read_receipt_broadcast']);
            }
        }

        return response()->json(['ok' => true, 'marked' => count($inserts)]);
    }

    public function typing(Request $request, $roomId)
    {
        $user = Auth::user();
        $room = Room::findOrFail($roomId);

        if (! $this->canAccessRoom($room, $user)) {
            return response()->json(['error' => 'Erişim reddedildi'], 403);
        }

        $data = $request->validate(['is_typing' => 'required|boolean']);

        try {
            broadcast(new UserTyping((int) $roomId, [
                'id' => $user->id,
                'username' => $user->username,
                'avatar_url' => $user->avatar_url,
                'role' => $user->role,
            ], (bool) $data['is_typing']))->toOthers();
        } catch (\Throwable $e) {
            Log::warning('chat.broadcast.typing_failed', [
                'room_id' => (int) $roomId,
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
            AppMetrics::increment('external_service_errors_total', ['service' => 'reverb', 'reason' => 'typing_broadcast']);
        }

        return response()->json(['ok' => true]);
    }

    public function triggerStayConnected(Request $request, $roomId)
    {
        $user = Auth::user();
        $room = Room::findOrFail($roomId);

        if (! $this->canAccessRoom($room, $user)) {
            return response()->json(['error' => 'Erişim reddedildi'], 403);
        }

        if (! $user->isAdmin()) {
            return response()->json(['error' => 'Yetkin yok'], 403);
        }

        try {
            broadcast(new StayConnectedSurpriseTriggered((int) $roomId, [
                'id' => $user->id,
                'username' => $user->username,
            ]))->toOthers();
        } catch (\Throwable $e) {
            Log::warning('chat.broadcast.stay_connected_failed', [
                'room_id' => (int) $roomId,
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
            AppMetrics::increment('external_service_errors_total', ['service' => 'reverb', 'reason' => 'stay_connected_broadcast']);

            return response()->json(['error' => 'Sürpriz yayını gönderilemedi'], 503);
        }

        return response()->json(['ok' => true]);
    }

    public function getPresence()
    {
        $onlineUsers = \Cache::get('online_users', []);

        /* Remove users inactive > 45 s — same threshold as updatePresence() */
        $now = now()->timestamp;
        $onlineUsers = array_filter($onlineUsers, function ($u) use ($now) {
            return ($now - ($u['last_seen'] ?? 0)) < 45;
        });

        return response()->json(array_values($onlineUsers));
    }
}
