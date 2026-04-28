<?php

namespace App\Http\Controllers;

use App\Models\Room;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class RoomController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'name'     => 'required|string|max:100',
            'type'     => 'required|in:global,private',
            'members'  => 'nullable|array',
            'members.*'=> 'integer|exists:users,id',
        ]);

        $user = Auth::user();

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

        if ($room->created_by !== $user->id && ! $user->isAdmin()) {
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
