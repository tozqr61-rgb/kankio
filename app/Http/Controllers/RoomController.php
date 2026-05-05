<?php

namespace App\Http\Controllers;

use App\Models\Room;
use App\Models\User;
use App\Services\RoomAccessService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

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
            'members.*'=> 'integer|exists:users,id',
        ]);

        $user = Auth::user();

        if (! $this->roomAccess->canCreateRoom($user, $request->type)) {
            return response()->json(['error' => 'Global oda oluşturma yalnızca yöneticilere açıktır'], 403);
        }

        if ($request->type === 'private') {
            $privateRoomCount = Room::where('created_by', $user->id)->where('type', 'private')->count();
            if ($privateRoomCount >= self::PRIVATE_ROOM_QUOTA) {
                return response()->json(['error' => 'Özel oda limitine ulaştınız'], 422);
            }
        }

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

        // For global rooms, add all users
        if ($request->type === 'global') {
            $allUsers = User::where('id', '!=', $user->id)->get();
            foreach ($allUsers as $u) {
                $members[$u->id] = ['role' => 'member'];
            }
        }

        $room->members()->attach($members);

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

        $room->delete();

        return response()->json(['ok' => true]);
    }

    public function getUsers()
    {
        $currentUser = Auth::user();
        $users       = User::where('id', '!=', $currentUser->id)->select('id', 'username', 'avatar_url')->get();

        return response()->json($users);
    }
}
