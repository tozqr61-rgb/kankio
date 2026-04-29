<?php

namespace App\Http\Controllers;

use App\Events\VoiceMuteChanged;
use App\Events\VoiceSignalEvent;
use App\Events\VoiceStateChanged;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Agence104\LiveKit\AccessToken;
use Agence104\LiveKit\AccessTokenOptions;
use Agence104\LiveKit\VideoGrant;

class VoiceController extends Controller
{
    private function canAccessRoom($roomId, $user): bool
    {
        $room = \App\Models\Room::find($roomId);
        if (! $room || $room->type !== 'private') return true;
        return $user->isAdmin() || $room->members()->where('user_id', $user->id)->exists();
    }

    /** Join voice channel for this room */
    public function join($roomId)
    {
        $user = Auth::user();
        if (! $this->canAccessRoom($roomId, $user)) {
            return response()->json(['error' => 'Erisim reddedildi'], 403);
        }
        $userId = $user->id;

        DB::table('voice_sessions')->updateOrInsert(
            ['room_id' => $roomId, 'user_id' => $userId],
            ['is_active' => true, 'is_muted' => false, 'joined_at' => now(), 'last_ping' => now()]
        );

        $state = $this->getState($roomId, $userId);
        
        // Generate LiveKit Token
        $tokenOptions = (new AccessTokenOptions())
            ->setIdentity((string) $userId)
            ->setName($user->username);
            
        $videoGrant = (new VideoGrant())
            ->setRoomJoin()
            ->setRoomName('room_' . $roomId);

        $token = (new AccessToken(
            config('services.livekit.key'),
            config('services.livekit.secret')
        ))
        ->init($tokenOptions)
        ->setGrant($videoGrant)
        ->toJwt();

        $state['livekit_token'] = $token;
        $state['livekit_url'] = config('services.livekit.url');

        $this->broadcastVoiceState($roomId, $state['participants']);
        return response()->json($state);
    }

    /** Leave voice channel */
    public function leave($roomId)
    {
        $userId = Auth::id();

        DB::table('voice_sessions')
            ->where('room_id', $roomId)
            ->where('user_id', $userId)
            ->delete();

        DB::table('voice_signals')
            ->where('room_id', $roomId)
            ->where(fn($q) => $q->where('from_user_id', $userId)->orWhere('to_user_id', $userId))
            ->delete();

        $this->broadcastVoiceState($roomId);
        return response()->json(['ok' => true]);
    }

    /** Toggle mute */
    public function toggleMute($roomId)
    {
        $userId = Auth::id();

        $session = DB::table('voice_sessions')
            ->where('room_id', $roomId)
            ->where('user_id', $userId)
            ->first();

        if ($session) {
            $newMute = ! $session->is_muted;
            DB::table('voice_sessions')
                ->where('room_id', $roomId)
                ->where('user_id', $userId)
                ->update(['is_muted' => $newMute, 'last_ping' => now()]);

            /* Lightweight event: only userId + isMuted (~95% smaller payload than full list) */
            try {
                broadcast(new VoiceMuteChanged($roomId, $userId, $newMute));
            } catch (\Throwable) {}
        }

        return response()->json(['ok' => true]);
    }

    /** Get voice state + pending signals */
    public function state($roomId)
    {
        $user = Auth::user();
        if (! $this->canAccessRoom($roomId, $user)) {
            return response()->json(['error' => 'Erisim reddedildi'], 403);
        }
        $userId = $user->id;

        // Heartbeat — keep session alive
        DB::table('voice_sessions')
            ->where('room_id', $roomId)
            ->where('user_id', $userId)
            ->update(['last_ping' => now()]);

        return response()->json($this->getState($roomId, $userId));
    }



    private function broadcastVoiceState(int $roomId, $participants = null): void
    {
        try {
            if ($participants === null) {
                $participants = DB::table('voice_sessions')
                    ->join('users', 'users.id', '=', 'voice_sessions.user_id')
                    ->where('voice_sessions.room_id', $roomId)
                    ->where('voice_sessions.is_active', true)
                    ->select('users.id', 'users.username', 'users.avatar_url', 'voice_sessions.is_muted')
                    ->get()->toArray();
            }
            broadcast(new VoiceStateChanged($roomId, (array) $participants));
        } catch (\Throwable) {}
    }

    private function getState($roomId, $userId): array
    {
        // Prune stale sessions (no ping for > 15s)
        DB::table('voice_sessions')
            ->where('room_id', $roomId)
            ->where('last_ping', '<', now()->subSeconds(15))
            ->delete();

        $participants = DB::table('voice_sessions')
            ->join('users', 'users.id', '=', 'voice_sessions.user_id')
            ->where('voice_sessions.room_id', $roomId)
            ->where('voice_sessions.is_active', true)
            ->select('users.id', 'users.username', 'users.avatar_url', 'voice_sessions.is_muted', 'voice_sessions.joined_at')
            ->get();

        $inVoice = DB::table('voice_sessions')
            ->where('room_id', $roomId)
            ->where('user_id', $userId)
            ->where('is_active', true)
            ->exists();

        $mySession = DB::table('voice_sessions')
            ->where('room_id', $roomId)
            ->where('user_id', $userId)
            ->first();

        return [
            'in_voice'     => $inVoice,
            'is_muted'     => $mySession ? (bool) $mySession->is_muted : false,
            'participants' => $participants,
        ];
    }
}
