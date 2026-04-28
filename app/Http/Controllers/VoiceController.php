<?php

namespace App\Http\Controllers;

use App\Events\VoiceMuteChanged;
use App\Events\VoiceSignalEvent;
use App\Events\VoiceStateChanged;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

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

    /** Post a WebRTC signal (offer / answer / ice) */
    public function signal(Request $request, $roomId)
    {
        $user = Auth::user();
        if (! $this->canAccessRoom($roomId, $user)) {
            return response()->json(['error' => 'Erisim reddedildi'], 403);
        }

        $request->validate([
            'to_user_id' => 'required|integer|exists:users,id',
            'type'       => 'required|in:offer,answer,ice',
            'payload'    => 'required|string',
        ]);

        $fromId = $user->id;

        DB::table('voice_signals')->insert([
            'room_id'      => $roomId,
            'from_user_id' => $fromId,
            'to_user_id'   => $request->to_user_id,
            'type'         => $request->type,
            'payload'      => $request->payload,
            'processed'    => false,
            'created_at'   => now(),
            'updated_at'   => now(),
        ]);

        /* Instant delivery via Reverb — DB record is the fallback */
        try {
            broadcast(new VoiceSignalEvent(
                toUserId:   $request->to_user_id,
                fromUserId: $fromId,
                type:       $request->type,
                payload:    $request->payload,
            ));
        } catch (\Throwable) {}

        return response()->json(['ok' => true]);
    }

    /** Get and consume pending signals for current user in this room */
    public function getSignals($roomId)
    {
        $user = Auth::user();
        if (! $this->canAccessRoom($roomId, $user)) {
            return response()->json(['error' => 'Erisim reddedildi'], 403);
        }
        $userId = $user->id;

        $signals = DB::table('voice_signals')
            ->where('room_id', $roomId)
            ->where('to_user_id', $userId)
            ->where('processed', false)
            ->orderBy('created_at')
            ->get();

        if ($signals->count()) {
            DB::table('voice_signals')
                ->whereIn('id', $signals->pluck('id'))
                ->update(['processed' => true]);
        }

        // Clean up old processed signals (> 30s)
        DB::table('voice_signals')
            ->where('room_id', $roomId)
            ->where('processed', true)
            ->where('created_at', '<', now()->subSeconds(30))
            ->delete();

        return response()->json($signals->map(fn($s) => [
            'id'          => $s->id,
            'from_user_id'=> $s->from_user_id,
            'type'        => $s->type,
            'payload'     => $s->payload,
        ]));
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
