<?php

namespace App\Http\Controllers;

use App\Models\Room;
use App\Models\User;
use App\Services\RoomAccessService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class RoomController extends Controller
{
    private const PRIVATE_ROOM_QUOTA = 20;

    public function __construct(private RoomAccessService $roomAccess)
    {
    }

    public function store(Request $request)
    {
        $request->validate([
            'name'     => 'required|string|max:100',
            'type'     => 'required|in:global,private',
            'members'  => 'nullable|array',
            'members.*'=> [
                'integer',
                Rule::exists('users', 'id')->where(fn ($query) => $query
                    ->where('is_bot', false)
                    ->where('is_banned', false)
                    ->whereNull('deactivated_at')),
            ],
        ]);

        $user = Auth::user();

        if (! $this->roomAccess->canCreateRoom($user, $request->type)) {
            return response()->json(['error' => 'Global oda oluşturma yalnızca yöneticilere açıktır'], 403);
        }

        if ($request->type === 'private') {
            $privateRoomCount = Room::where('created_by', $user->id)
                ->where('type', 'private')
                ->where('is_archived', false)
                ->count();
            if ($privateRoomCount >= self::PRIVATE_ROOM_QUOTA) {
                return response()->json(['error' => 'Özel oda limitine ulaştınız'], 422);
            }
        }

        $room = DB::transaction(function () use ($request, $user) {
            $room = Room::create([
                'name'       => $request->name,
                'type'       => $request->type,
                'created_by' => $user->id,
            ]);

            // Add creator as owner
            $members = [$user->id => ['role' => 'owner']];

            // Add selected members
            if ($request->type === 'private' && $request->members) {
                foreach ($request->members as $memberId) {
                    $members[$memberId] = ['role' => 'member'];
                }
            }

            // For global rooms, add all eligible users
            if ($request->type === 'global') {
                $allUsers = User::where('id', '!=', $user->id)
                    ->where('is_bot', false)
                    ->where('is_banned', false)
                    ->whereNull('deactivated_at')
                    ->get();
                foreach ($allUsers as $u) {
                    $members[$u->id] = ['role' => 'member'];
                }
            }

            $room->members()->attach($members);

            return $room;
        });

        return response()->json([
            'id'   => $room->id,
            'name' => $room->name,
            'type' => $room->type,
        ], 201);
    }

    public function destroy($roomId)
    {
        $user = Auth::user();
        $room = Room::findOrFail($roomId);

        if (! $this->roomAccess->canModerateRoom($room, $user)) {
            return response()->json(['error' => 'Erişim reddedildi'], 403);
        }

        $room->update([
            'is_archived' => true,
            'archived_at' => now(),
            'archived_by' => $user->id,
        ]);

        return response()->json(['ok' => true]);
    }

    public function getUsers(Request $request)
    {
        $currentUser = Auth::user();
        $data = $request->validate([
            'q' => 'nullable|string|max:50',
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:50',
        ]);
        $perPage = (int) ($data['per_page'] ?? 20);
        $search = trim((string) ($data['q'] ?? ''));

        $users = User::query()
            ->whereKeyNot($currentUser->id)
            ->where('is_bot', false)
            ->where('is_banned', false)
            ->whereNull('deactivated_at')
            ->when($search !== '', fn ($query) => $query->where('username', 'like', '%'.$search.'%'))
            ->orderBy('username')
            ->paginate($perPage, ['id', 'username', 'avatar_url'], 'page', (int) ($data['page'] ?? 1));

        return response()->json([
            'users' => collect($users->items())->map(fn (User $user) => [
                'id' => $user->id,
                'username' => $user->username,
                'avatar_url' => $user->avatar_url,
            ])->values(),
            'pagination' => [
                'current_page' => $users->currentPage(),
                'has_more' => $users->hasMorePages(),
                'per_page' => $users->perPage(),
                'total' => $users->total(),
            ],
        ]);
    }
}
