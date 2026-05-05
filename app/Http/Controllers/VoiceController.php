<?php

namespace App\Http\Controllers;

use Agence104\LiveKit\AccessToken;
use Agence104\LiveKit\AccessTokenOptions;
use Agence104\LiveKit\VideoGrant;
use App\Events\VoiceParticipantUpdated;
use App\Events\VoiceStateChanged;
use App\Models\Room;
use App\Services\RoomAccessService;
use App\Support\AppMetrics;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class VoiceController extends Controller
{
    private const STALE_SECONDS = 20;

    public function __construct(private RoomAccessService $roomAccess)
    {
    }

    public function join(Request $request, $roomId)
    {
        $user = Auth::user();
        $room = Room::find($roomId);

        if (! $room || ! $this->roomAccess->canJoinVoice($room, $user)) {
            return response()->json(['error' => 'Erisim reddedildi'], 403);
        }

        $livekitUrl = trim((string) config('services.livekit.url'));
        $livekitKey = trim((string) config('services.livekit.key'));
        $livekitSecret = trim((string) config('services.livekit.secret'));

        if ($livekitUrl === '' || $livekitKey === '' || $livekitSecret === '') {
            Log::error('voice.join.config_missing', [
                'room_id' => $room->id,
                'user_id' => $user->id,
            ]);
            AppMetrics::increment('voice_join_fail_total', ['reason' => 'config_missing']);
            AppMetrics::increment('external_service_errors_total', ['service' => 'livekit', 'reason' => 'config_missing']);

            return response()->json([
                'error' => 'LiveKit sunucu bilgileri eksik. LIVEKIT_URL/API_KEY/API_SECRET ayarlarını kontrol edin.',
            ], 503);
        }

        $this->pruneStaleSessions($room->id);

        $existing = DB::table('voice_sessions')
            ->where('room_id', $room->id)
            ->where('user_id', $user->id)
            ->first();

        $canModerate = $this->roomAccess->canModerateVoice($room, $user);
        $canSpeak = $existing
            ? (bool) $existing->can_speak
            : (! $room->voice_requires_permission || $canModerate);
        $isMuted = $existing ? (bool) $existing->is_muted : ! $canSpeak;
        $reconnectCount = (int) ($existing->reconnect_count ?? 0);

        if ($request->boolean('reconnect')) {
            $reconnectCount++;
        }

        DB::table('voice_sessions')->updateOrInsert(
            ['room_id' => $room->id, 'user_id' => $user->id],
            [
                'is_active' => true,
                'is_muted' => $isMuted,
                'is_deafened' => (bool) ($existing->is_deafened ?? false),
                'is_speaking' => false,
                'can_speak' => $canSpeak,
                'connection_quality' => 'unknown',
                'reconnect_count' => $reconnectCount,
                'joined_at' => $existing && ! $request->boolean('reconnect') ? $existing->joined_at : now(),
                'last_ping' => now(),
                'last_client_event_at' => now(),
            ]
        );

        try {
            $token = $this->createLiveKitToken($room, $user, $canSpeak);
        } catch (\Throwable $e) {
            Log::error('voice.join.token_failed', [
                'room_id' => $room->id,
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
            AppMetrics::increment('voice_join_fail_total', ['reason' => 'token_failed']);
            AppMetrics::increment('external_service_errors_total', ['service' => 'livekit', 'reason' => 'token_failed']);

            return response()->json(['error' => 'Ses tokeni üretilemedi.'], 503);
        }

        $state = $this->getStatePayload($room, $user);
        $state['livekit_token'] = $token;
        $state['livekit_url'] = $livekitUrl;
        $state['livekit_room'] = $this->liveKitRoomName($room->id);

        Log::info('voice.join.success', [
            'room_id' => $room->id,
            'user_id' => $user->id,
            'reconnect' => $request->boolean('reconnect'),
            'reconnect_count' => $reconnectCount,
            'can_speak' => $canSpeak,
        ]);
        AppMetrics::increment('voice_join_success_total');
        if ($request->boolean('reconnect')) {
            AppMetrics::increment('voice_reconnect_total');
        }

        $this->broadcastVoiceState($room->id, $state['participants']);
        $this->recordConcurrentVoiceGauge((int) $room->id);

        return response()->json($state);
    }

    public function leave($roomId)
    {
        $user = Auth::user();
        $room = Room::find($roomId);

        if (! $room || ! $this->roomAccess->canAccessRoom($room, $user)) {
            return response()->json(['error' => 'Erisim reddedildi'], 403);
        }

        DB::table('voice_sessions')
            ->where('room_id', $roomId)
            ->where('user_id', $user->id)
            ->delete();

        DB::table('voice_signals')
            ->where('room_id', $roomId)
            ->where(fn ($q) => $q->where('from_user_id', $user->id)->orWhere('to_user_id', $user->id))
            ->delete();

        Log::info('voice.leave', ['room_id' => (int) $roomId, 'user_id' => $user->id]);

        $this->broadcastVoiceState((int) $roomId);
        $this->recordConcurrentVoiceGauge((int) $roomId);

        return response()->json(['ok' => true]);
    }

    public function toggleMute(Request $request, $roomId)
    {
        $user = Auth::user();
        $room = Room::find($roomId);

        if (! $room || ! $this->roomAccess->canJoinVoice($room, $user)) {
            return response()->json(['error' => 'Erisim reddedildi'], 403);
        }

        $session = $this->activeSession((int) $roomId, $user->id);

        if (! $session) {
            return response()->json(['error' => 'Ses kanalında değilsiniz'], 409);
        }

        $newMute = $request->has('is_muted')
            ? $request->boolean('is_muted')
            : ! (bool) $session->is_muted;

        if (! (bool) $session->can_speak && ! $newMute) {
            return response()->json(['error' => 'Bu odada konuşma izniniz yok'], 403);
        }

        DB::table('voice_sessions')
            ->where('room_id', $roomId)
            ->where('user_id', $user->id)
            ->update([
                'is_muted' => $newMute,
                'is_speaking' => false,
                'last_ping' => now(),
                'last_client_event_at' => now(),
            ]);

        $participant = $this->participantForUser((int) $roomId, $user->id);
        $this->broadcastParticipant((int) $roomId, $participant);

        return response()->json([
            'ok' => true,
            'is_muted' => $newMute,
            'participant' => $participant,
        ]);
    }

    public function toggleDeafen(Request $request, $roomId)
    {
        $user = Auth::user();
        $room = Room::find($roomId);

        if (! $room || ! $this->roomAccess->canJoinVoice($room, $user)) {
            return response()->json(['error' => 'Erisim reddedildi'], 403);
        }

        $session = $this->activeSession((int) $roomId, $user->id);

        if (! $session) {
            return response()->json(['error' => 'Ses kanalında değilsiniz'], 409);
        }

        $newDeafen = $request->has('is_deafened')
            ? $request->boolean('is_deafened')
            : ! (bool) $session->is_deafened;

        DB::table('voice_sessions')
            ->where('room_id', $roomId)
            ->where('user_id', $user->id)
            ->update([
                'is_deafened' => $newDeafen,
                'last_ping' => now(),
                'last_client_event_at' => now(),
            ]);

        $participant = $this->participantForUser((int) $roomId, $user->id);
        $this->broadcastParticipant((int) $roomId, $participant);

        return response()->json([
            'ok' => true,
            'is_deafened' => $newDeafen,
            'participant' => $participant,
        ]);
    }

    public function speaking(Request $request, $roomId)
    {
        $data = $request->validate(['is_speaking' => 'required|boolean']);
        $user = Auth::user();
        $room = Room::find($roomId);

        if (! $room || ! $this->roomAccess->canJoinVoice($room, $user)) {
            return response()->json(['error' => 'Erisim reddedildi'], 403);
        }

        $session = $this->activeSession((int) $roomId, $user->id);

        if (! $session) {
            return response()->json(['error' => 'Ses kanalında değilsiniz'], 409);
        }

        $isSpeaking = (bool) $data['is_speaking']
            && ! (bool) $session->is_muted
            && (bool) $session->can_speak;

        if ((bool) $session->is_speaking !== $isSpeaking) {
            DB::table('voice_sessions')
                ->where('room_id', $roomId)
                ->where('user_id', $user->id)
                ->update([
                    'is_speaking' => $isSpeaking,
                    'last_ping' => now(),
                    'last_client_event_at' => now(),
                ]);

            $this->broadcastParticipant((int) $roomId, $this->participantForUser((int) $roomId, $user->id));
        } else {
            DB::table('voice_sessions')
                ->where('room_id', $roomId)
                ->where('user_id', $user->id)
                ->update(['last_ping' => now()]);
        }

        return response()->json(['ok' => true, 'is_speaking' => $isSpeaking]);
    }

    public function quality(Request $request, $roomId)
    {
        $data = $request->validate([
            'connection_quality' => ['required', Rule::in(['unknown', 'poor', 'good', 'excellent'])],
            'reconnected' => 'sometimes|boolean',
        ]);

        $user = Auth::user();
        $room = Room::find($roomId);

        if (! $room || ! $this->roomAccess->canJoinVoice($room, $user)) {
            return response()->json(['error' => 'Erisim reddedildi'], 403);
        }

        $session = $this->activeSession((int) $roomId, $user->id);

        if (! $session) {
            return response()->json(['error' => 'Ses kanalında değilsiniz'], 409);
        }

        DB::table('voice_sessions')
            ->where('room_id', $roomId)
            ->where('user_id', $user->id)
            ->update([
                'connection_quality' => $data['connection_quality'],
                'reconnect_count' => (int) $session->reconnect_count + ($request->boolean('reconnected') ? 1 : 0),
                'last_ping' => now(),
                'last_client_event_at' => now(),
            ]);

        $participant = $this->participantForUser((int) $roomId, $user->id);
        $this->broadcastParticipant((int) $roomId, $participant);

        return response()->json(['ok' => true, 'participant' => $participant]);
    }

    public function state($roomId)
    {
        $user = Auth::user();
        $room = Room::find($roomId);

        if (! $room || ! $this->roomAccess->canJoinVoice($room, $user)) {
            return response()->json(['error' => 'Erisim reddedildi'], 403);
        }

        DB::table('voice_sessions')
            ->where('room_id', $room->id)
            ->where('user_id', $user->id)
            ->where('is_active', true)
            ->update(['last_ping' => now()]);

        return response()->json($this->getStatePayload($room, $user));
    }

    public function muteAll($roomId)
    {
        $user = Auth::user();
        $room = Room::findOrFail($roomId);

        if (! $this->roomAccess->canModerateVoice($room, $user)) {
            return response()->json(['error' => 'Erisim reddedildi'], 403);
        }

        DB::table('voice_sessions')
            ->where('room_id', $room->id)
            ->where('is_active', true)
            ->where('user_id', '!=', $user->id)
            ->update([
                'is_muted' => true,
                'is_speaking' => false,
                'last_client_event_at' => now(),
            ]);

        Log::warning('voice.moderation.mute_all', ['room_id' => $room->id, 'moderator_id' => $user->id]);
        AppMetrics::increment('voice_moderation_action_total', ['action' => 'mute_all']);
        $this->broadcastVoiceState($room->id);

        return response()->json(['ok' => true]);
    }

    public function kick($roomId, $userId)
    {
        $user = Auth::user();
        $room = Room::findOrFail($roomId);

        if (! $this->roomAccess->canModerateVoice($room, $user)) {
            return response()->json(['error' => 'Erisim reddedildi'], 403);
        }

        DB::table('voice_sessions')
            ->where('room_id', $room->id)
            ->where('user_id', $userId)
            ->delete();

        Log::warning('voice.moderation.kick', [
            'room_id' => $room->id,
            'moderator_id' => $user->id,
            'target_id' => (int) $userId,
        ]);
        AppMetrics::increment('voice_moderation_action_total', ['action' => 'kick']);

        $this->broadcastParticipant($room->id, ['id' => (int) $userId], 'kicked');
        $this->broadcastVoiceState($room->id);
        $this->recordConcurrentVoiceGauge($room->id);

        return response()->json(['ok' => true]);
    }

    public function setSpeakPermission(Request $request, $roomId, $userId)
    {
        $data = $request->validate(['can_speak' => 'required|boolean']);
        $user = Auth::user();
        $room = Room::findOrFail($roomId);

        if (! $this->roomAccess->canModerateVoice($room, $user)) {
            return response()->json(['error' => 'Erisim reddedildi'], 403);
        }

        DB::table('voice_sessions')
            ->where('room_id', $room->id)
            ->where('user_id', $userId)
            ->update([
                'can_speak' => (bool) $data['can_speak'],
                'is_muted' => ! (bool) $data['can_speak'],
                'is_speaking' => false,
                'last_client_event_at' => now(),
            ]);

        $participant = $this->participantForUser($room->id, (int) $userId);
        $this->broadcastParticipant($room->id, $participant);
        AppMetrics::increment('voice_moderation_action_total', ['action' => 'speak_permission']);

        return response()->json(['ok' => true, 'participant' => $participant]);
    }

    public function settings(Request $request, $roomId)
    {
        $data = $request->validate([
            'voice_members_only' => 'sometimes|boolean',
            'voice_requires_permission' => 'sometimes|boolean',
        ]);
        $user = Auth::user();
        $room = Room::findOrFail($roomId);

        if (! $this->roomAccess->canModerateVoice($room, $user)) {
            return response()->json(['error' => 'Erisim reddedildi'], 403);
        }

        $room->fill($data)->save();
        if (array_key_exists('voice_members_only', $data) || array_key_exists('voice_requires_permission', $data)) {
            $this->applyVoiceSettingsToActiveSessions($room, $user);
        }

        Log::info('voice.settings.updated', [
            'room_id' => $room->id,
            'moderator_id' => $user->id,
            'settings' => $data,
        ]);

        $this->broadcastVoiceState($room->id);

        return response()->json([
            'ok' => true,
            'settings' => [
                'voice_members_only' => (bool) $room->voice_members_only,
                'voice_requires_permission' => (bool) $room->voice_requires_permission,
            ],
        ]);
    }

    private function createLiveKitToken(Room $room, $user, bool $canSpeak): string
    {
        $tokenOptions = (new AccessTokenOptions)
            ->setIdentity((string) $user->id)
            ->setName($user->username)
            ->setTtl(60 * 60)
            ->setMetadata(json_encode([
                'user_id' => $user->id,
                'username' => $user->username,
                'room_id' => $room->id,
            ], JSON_THROW_ON_ERROR));

        $videoGrant = (new VideoGrant)
            ->setRoomJoin()
            ->setRoomName($this->liveKitRoomName($room->id))
            ->setCanSubscribe(true)
            ->setCanPublish($canSpeak)
            ->setCanPublishData(true)
            ->setCanPublishSources($canSpeak ? ['microphone'] : []);

        return (new AccessToken(
            config('services.livekit.key'),
            config('services.livekit.secret')
        ))
            ->init($tokenOptions)
            ->setGrant($videoGrant)
            ->toJwt();
    }

    private function getStatePayload(Room $room, $user): array
    {
        $this->pruneStaleSessions($room->id);

        $mySession = DB::table('voice_sessions')
            ->where('room_id', $room->id)
            ->where('user_id', $user->id)
            ->where('is_active', true)
            ->first();

        return [
            'in_voice' => (bool) $mySession,
            'is_muted' => $mySession ? (bool) $mySession->is_muted : false,
            'is_deafened' => $mySession ? (bool) $mySession->is_deafened : false,
            'can_speak' => $mySession ? (bool) $mySession->can_speak : true,
            'connection_quality' => $mySession->connection_quality ?? 'unknown',
            'reconnect_count' => (int) ($mySession->reconnect_count ?? 0),
            'can_moderate' => $this->roomAccess->canModerateVoice($room, $user),
            'settings' => [
                'voice_members_only' => (bool) $room->voice_members_only,
                'voice_requires_permission' => (bool) $room->voice_requires_permission,
            ],
            'participants' => $this->participants($room->id),
        ];
    }

    private function participants(int $roomId): array
    {
        return DB::table('voice_sessions')
            ->join('users', 'users.id', '=', 'voice_sessions.user_id')
            ->where('voice_sessions.room_id', $roomId)
            ->where('voice_sessions.is_active', true)
            ->orderBy('voice_sessions.joined_at')
            ->select(
                'users.id',
                'users.username',
                'users.avatar_url',
                'voice_sessions.is_muted',
                'voice_sessions.is_deafened',
                'voice_sessions.is_speaking',
                'voice_sessions.can_speak',
                'voice_sessions.connection_quality',
                'voice_sessions.reconnect_count',
                'voice_sessions.joined_at'
            )
            ->get()
            ->map(fn ($p) => [
                'id' => (int) $p->id,
                'username' => $p->username,
                'avatar_url' => $p->avatar_url,
                'is_muted' => (bool) $p->is_muted,
                'is_deafened' => (bool) $p->is_deafened,
                'is_speaking' => (bool) $p->is_speaking,
                'can_speak' => (bool) $p->can_speak,
                'connection_quality' => $p->connection_quality ?? 'unknown',
                'reconnect_count' => (int) $p->reconnect_count,
                'joined_at' => $p->joined_at,
            ])
            ->toArray();
    }

    private function participantForUser(int $roomId, int $userId): array
    {
        foreach ($this->participants($roomId) as $participant) {
            if ((int) $participant['id'] === $userId) {
                return $participant;
            }
        }

        return ['id' => $userId];
    }

    private function activeSession(int $roomId, int $userId): ?object
    {
        return DB::table('voice_sessions')
            ->where('room_id', $roomId)
            ->where('user_id', $userId)
            ->where('is_active', true)
            ->first();
    }

    private function applyVoiceSettingsToActiveSessions(Room $room, $moderator): void
    {
        if ($room->voice_members_only) {
            $allowedUserIds = $this->voiceMemberAllowedUserIds($room, (int) $moderator->id);

            DB::table('voice_sessions')
                ->where('room_id', $room->id)
                ->whereNotIn('user_id', $allowedUserIds)
                ->delete();
        }

        if ($room->voice_requires_permission) {
            $moderatorIds = $this->voiceModeratorUserIds($room, (int) $moderator->id);

            DB::table('voice_sessions')
                ->where('room_id', $room->id)
                ->whereNotIn('user_id', $moderatorIds)
                ->update([
                    'can_speak' => false,
                    'is_muted' => true,
                    'is_speaking' => false,
                    'last_client_event_at' => now(),
                ]);

            return;
        }

        DB::table('voice_sessions')
            ->where('room_id', $room->id)
            ->update([
                'can_speak' => true,
                'last_client_event_at' => now(),
            ]);
    }

    private function voiceModeratorUserIds(Room $room, int $fallbackUserId): array
    {
        $adminIds = DB::table('users')
            ->where('role', 'admin')
            ->pluck('id')
            ->all();

        $roomModeratorIds = DB::table('room_members')
            ->where('room_id', $room->id)
            ->whereIn('role', ['owner', 'admin'])
            ->pluck('user_id')
            ->all();

        return array_values(array_unique(array_map('intval', array_filter([
            ...$adminIds,
            ...$roomModeratorIds,
            $room->created_by,
            $fallbackUserId,
        ]))));
    }

    private function voiceMemberAllowedUserIds(Room $room, int $fallbackUserId): array
    {
        $adminIds = DB::table('users')
            ->where('role', 'admin')
            ->pluck('id')
            ->all();

        $memberIds = DB::table('room_members')
            ->where('room_id', $room->id)
            ->pluck('user_id')
            ->all();

        return array_values(array_unique(array_map('intval', array_filter([
            ...$adminIds,
            ...$memberIds,
            $room->created_by,
            $fallbackUserId,
        ]))));
    }

    private function pruneStaleSessions(int $roomId): void
    {
        DB::table('voice_sessions')
            ->where('room_id', $roomId)
            ->where('last_ping', '<', now()->subSeconds(self::STALE_SECONDS))
            ->delete();
    }

    private function broadcastVoiceState(int $roomId, ?array $participants = null): void
    {
        try {
            broadcast(new VoiceStateChanged(
                $roomId,
                $participants ?? $this->participants($roomId),
                $this->voiceSettingsForRoom($roomId)
            ));
        } catch (\Throwable $e) {
            Log::warning('voice.broadcast.state_failed', [
                'room_id' => $roomId,
                'error' => $e->getMessage(),
            ]);
            AppMetrics::increment('external_service_errors_total', ['service' => 'reverb', 'reason' => 'voice_state_broadcast']);
        }
    }

    private function broadcastParticipant(int $roomId, array $participant, string $action = 'updated'): void
    {
        try {
            broadcast(new VoiceParticipantUpdated($roomId, $participant, $action));
        } catch (\Throwable $e) {
            Log::warning('voice.broadcast.participant_failed', [
                'room_id' => $roomId,
                'action' => $action,
                'error' => $e->getMessage(),
            ]);
            AppMetrics::increment('external_service_errors_total', ['service' => 'reverb', 'reason' => 'voice_participant_broadcast']);
        }
    }

    private function liveKitRoomName(int $roomId): string
    {
        return 'room_'.$roomId;
    }

    private function voiceSettingsForRoom(int $roomId): ?array
    {
        $room = Room::query()
            ->select(['id', 'voice_members_only', 'voice_requires_permission'])
            ->find($roomId);

        if (! $room) {
            return null;
        }

        return [
            'voice_members_only' => (bool) $room->voice_members_only,
            'voice_requires_permission' => (bool) $room->voice_requires_permission,
        ];
    }

    private function recordConcurrentVoiceGauge(int $roomId): void
    {
        AppMetrics::gauge(
            'voice_concurrent_users',
            DB::table('voice_sessions')->where('room_id', $roomId)->where('is_active', true)->count(),
            ['room_id' => $roomId]
        );
    }
}
