<?php

namespace App\Http\Controllers;

use App\Models\GameRound;
use App\Models\GameSession;
use App\Models\Room;
use App\Services\Games\IsimSehirGameService;
use App\Services\RoomAccessService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;

class GameController extends Controller
{
    public function __construct(
        private RoomAccessService $roomAccess,
        private IsimSehirGameService $games,
    ) {}

    public function current(Room $room)
    {
        $this->authorizeRoom($room);
        $session = $this->games->current($room);

        if (! $session) {
            if (request()->expectsJson()) {
                return response()->json(['ok' => false, 'active' => false], 404);
            }

            return redirect()->route('chat.room', $room->id)->withErrors([
                'game' => 'Bu odada aktif İsim-Şehir oyunu yok.',
            ]);
        }

        if (request()->expectsJson()) {
            return response()->json([
                'ok' => true,
                'active' => true,
                'game_session_id' => $session->id,
                'redirect' => route('rooms.games.show', [$room, $session]),
                'state' => $this->games->state($session, Auth::user()),
            ]);
        }

        return redirect()->route('rooms.games.show', [$room, $session]);
    }

    public function start(Request $request, Room $room)
    {
        $user = Auth::user();
        $this->authorizeRoom($room);

        $data = $request->validate([
            'round_time_seconds' => 'nullable|integer|min:30|max:900',
            'categories' => 'nullable|array|min:2|max:10',
            'categories.*' => 'string|max:30',
        ]);

        $hadCurrentSession = (bool) $this->games->current($room);
        $session = $this->games->start($room, $user, $data);
        $this->games->broadcast($session, $user, 'session.started');

        return response()->json([
            'ok' => true,
            'redirect' => route('rooms.games.show', [$room, $session]),
            'game_session_id' => $session->id,
        ], $hadCurrentSession ? 200 : 201);
    }

    public function show(Room $room, GameSession $gameSession)
    {
        $this->authorizeGame($room, $gameSession);
        $user = Auth::user();

        return view('games.isim-sehir', [
            'room' => $room,
            'gameSession' => $gameSession,
            'initialState' => $this->games->state($gameSession, $user),
            'canManageGame' => $this->canManageGame($room, $gameSession),
            'embedded' => request()->boolean('embedded'),
            'rooms' => $this->getAccessibleRooms($user),
            'dms' => $user->rooms()->where('type', 'dm')->get(),
            'onlineUsers' => $this->getOnlineUsers(),
        ]);
    }

    public function state(Room $room, GameSession $gameSession)
    {
        $this->authorizeGame($room, $gameSession);

        return response()->json($this->games->state($gameSession, Auth::user()));
    }

    public function join(Room $room, GameSession $gameSession)
    {
        $this->authorizeGame($room, $gameSession);

        $this->games->join($gameSession, Auth::user());

        return response()->json(['ok' => true, 'state' => $this->games->state($gameSession, Auth::user())]);
    }

    public function leave(Room $room, GameSession $gameSession)
    {
        $this->authorizeGame($room, $gameSession);

        $this->games->leave($gameSession, Auth::user());

        return response()->json(['ok' => true, 'state' => $this->games->state($gameSession, Auth::user())]);
    }

    public function ready(Request $request, Room $room, GameSession $gameSession)
    {
        $this->authorizeGame($room, $gameSession);
        $data = $request->validate(['is_ready' => 'required|boolean']);

        $this->games->ready($gameSession, Auth::user(), (bool) $data['is_ready']);

        return response()->json(['ok' => true, 'state' => $this->games->state($gameSession, Auth::user())]);
    }

    public function settings(Request $request, Room $room, GameSession $gameSession)
    {
        $this->authorizeGame($room, $gameSession);
        $this->authorizeManageGame($room, $gameSession);

        $data = $request->validate([
            'round_time_seconds' => 'required|integer|min:30|max:900',
            'categories' => 'required|array|min:2|max:10',
            'categories.*' => 'required|string|max:30',
        ]);

        $this->games->updateSettings($gameSession, Auth::user(), $data);

        return response()->json(['ok' => true, 'state' => $this->games->state($gameSession, Auth::user())]);
    }

    public function beginRound(Room $room, GameSession $gameSession)
    {
        $this->authorizeGame($room, $gameSession);
        $this->authorizeManageGame($room, $gameSession);

        $round = $this->games->beginRound($gameSession, Auth::user());

        return response()->json(['ok' => true, 'round_id' => $round->id, 'state' => $this->games->state($gameSession, Auth::user())]);
    }

    public function saveDraft(Request $request, Room $room, GameSession $gameSession, GameRound $round)
    {
        $this->authorizeGame($room, $gameSession);
        $data = $request->validate(['answers' => 'nullable|array']);

        $submission = $this->games->saveDraft($gameSession, $round, Auth::user(), $data['answers'] ?? []);

        return response()->json(['ok' => true, 'submission_id' => $submission->id]);
    }

    public function submit(Request $request, Room $room, GameSession $gameSession, GameRound $round)
    {
        $this->authorizeGame($room, $gameSession);
        $data = $request->validate(['answers' => 'required|array']);

        $submission = $this->games->submit($gameSession, $round, Auth::user(), $data['answers']);

        return response()->json(['ok' => true, 'submission_id' => $submission->id, 'state' => $this->games->state($gameSession, Auth::user())]);
    }

    public function finalizeRound(Room $room, GameSession $gameSession, GameRound $round)
    {
        $this->authorizeGame($room, $gameSession);
        $this->authorizeManageGame($room, $gameSession);

        $this->games->finalizeRound($gameSession, $round, Auth::user());

        return response()->json(['ok' => true, 'state' => $this->games->state($gameSession, Auth::user())]);
    }

    public function finish(Room $room, GameSession $gameSession)
    {
        $this->authorizeGame($room, $gameSession);
        $this->authorizeManageGame($room, $gameSession);

        $meta = $this->games->finish($gameSession, Auth::user());

        return response()->json([
            'ok' => true,
            'state' => $this->games->state($gameSession, Auth::user()),
            'meta' => $meta,
        ]);
    }

    private function authorizeRoom(Room $room): void
    {
        if (! $this->roomAccess->canAccessRoom($room, Auth::user())) {
            abort(403, 'Bu odadaki oyuna erişim izniniz yok.');
        }
    }

    private function authorizeGame(Room $room, GameSession $gameSession): void
    {
        $this->authorizeRoom($room);
        if ((int) $gameSession->room_id !== (int) $room->id) {
            abort(404);
        }
    }

    private function authorizeManageGame(Room $room, GameSession $gameSession): void
    {
        if (! $this->canManageGame($room, $gameSession)) {
            throw ValidationException::withMessages(['game' => 'Bu oyun aksiyonu için yetkiniz yok.']);
        }
    }

    private function canManageGame(Room $room, GameSession $gameSession): bool
    {
        $user = Auth::user();

        return $user->isAdmin()
            || (int) $gameSession->created_by === (int) $user->id
            || $this->roomAccess->canModerateRoom($room, $user);
    }

    private function getAccessibleRooms($user)
    {
        if ($user->isAdmin()) {
            return Room::where('type', '!=', 'dm')
                ->orderBy('created_at', 'asc')
                ->get();
        }

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

    private function getOnlineUsers(): array
    {
        return Cache::get('online_users', []);
    }
}
