<?php

namespace App\Http\Controllers;

use App\Events\MessageDeleted;
use App\Events\MessageSent;
use App\Models\Message;
use App\Models\Room;
use App\Services\RoomAccessService;
use App\Support\AppMetrics;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class MessageController extends Controller
{
    public function __construct(private RoomAccessService $roomAccess)
    {
    }

    public function store(Request $request, $roomId)
    {
        $user = Auth::user();
        $room = Room::findOrFail($roomId);

        // Announcements room: only admins can post
        if ($room->type === 'announcements' && ! $user->isAdmin()) {
            return response()->json(['error' => 'Bu odaya yalnızca yöneticiler yazabilir'], 403);
        }

        if (! $this->roomAccess->canAccessRoom($room, $user)) {
            return response()->json(['error' => 'Erişim reddedildi'], 403);
        }

        $isVoiceMessage = $request->hasFile('audio');

        if ($isVoiceMessage) {
            $request->validate([
                'audio' => 'required|file|mimes:webm,ogg,mp3,wav,mp4|max:10240',
                'audio_duration' => 'nullable|integer|min:1|max:300',
                'reply_to' => 'nullable|integer|exists:messages,id',
            ]);
            if (! $this->replyBelongsToRoom($request->reply_to, (int) $roomId)) {
                return response()->json(['error' => 'Yanıtlanan mesaj bu odada değil'], 422);
            }

            $file = $request->file('audio');
            $filename = $user->id.'_'.time().'_'.uniqid().'.'.$file->getClientOriginalExtension();
            $file->storeAs('voice_messages', $filename, 'public');
            $audioUrl = '/storage/voice_messages/'.$filename;

            $message = Message::create([
                'room_id' => $roomId,
                'sender_id' => $user->id,
                'content' => '🎤 Sesli mesaj',
                'audio_url' => $audioUrl,
                'audio_duration' => $request->audio_duration ?? null,
                'reply_to' => $request->reply_to,
            ]);
        } else {
            $rules = ['content' => 'required|string|max:4000', 'reply_to' => 'nullable|integer|exists:messages,id'];
            if ($room->type === 'announcements') {
                $rules['title'] = 'required|string|max:200';
            }
            $request->validate($rules);
            if (! $this->replyBelongsToRoom($request->reply_to, (int) $roomId)) {
                return response()->json(['error' => 'Yanıtlanan mesaj bu odada değil'], 422);
            }

            $message = Message::create([
                'room_id' => $roomId,
                'sender_id' => $user->id,
                'content' => $request->content,
                'title' => $request->title ?? null,
                'reply_to' => $request->reply_to,
            ]);
        }

        $message->load(['sender', 'replyToMessage.sender']);
        $payload = $this->formatMessage($message);

        try {
            broadcast(new MessageSent((int) $roomId, $payload))->toOthers();
        } catch (\Throwable $e) {
            Log::warning('chat.broadcast.message_sent_failed', [
                'room_id' => (int) $roomId,
                'message_id' => $message->id,
                'error' => $e->getMessage(),
            ]);
            AppMetrics::increment('external_service_errors_total', ['service' => 'reverb', 'reason' => 'message_sent_broadcast']);
        }

        return response()->json($payload, 201);
    }

    public function destroy($roomId, $messageId)
    {
        $user = Auth::user();
        $message = Message::where('room_id', $roomId)->findOrFail($messageId);
        $room = Room::findOrFail($roomId);

        if (! $this->roomAccess->canAccessRoom($room, $user)) {
            return response()->json(['error' => 'Erişim reddedildi'], 403);
        }

        if (! $user->isAdmin() && $message->sender_id !== $user->id) {
            return response()->json(['error' => 'Erişim reddedildi'], 403);
        }

        $message->forceFill(['deleted_by' => $user->id])->save();
        $message->delete();

        try {
            broadcast(new MessageDeleted((int) $roomId, (int) $messageId));
        } catch (\Throwable $e) {
            Log::warning('chat.broadcast.message_deleted_failed', [
                'room_id' => (int) $roomId,
                'message_id' => (int) $messageId,
                'error' => $e->getMessage(),
            ]);
            AppMetrics::increment('external_service_errors_total', ['service' => 'reverb', 'reason' => 'message_deleted_broadcast']);
        }

        return response()->json(['ok' => true]);
    }

    private function replyBelongsToRoom($replyTo, int $roomId): bool
    {
        if (! $replyTo) {
            return true;
        }

        return Message::where('id', $replyTo)
            ->where('room_id', $roomId)
            ->exists();
    }

    private function formatMessage(Message $message): array
    {
        return [
            'id' => $message->id,
            'title' => $message->title,
            'content' => $message->content,
            'audio_url' => $message->audio_url,
            'audio_duration' => $message->audio_duration,
            'is_system_message' => (bool) $message->is_system_message,
            'reply_to' => $message->reply_to,
            'reply_message' => $message->replyToMessage ? [
                'content' => $message->replyToMessage->content,
                'username' => $message->replyToMessage->sender?->username ?? 'Silinmiş',
            ] : null,
            'created_at' => $message->created_at->toISOString(),
            'sender' => [
                'id' => $message->sender->id,
                'username' => $message->sender->username,
                'avatar_url' => $message->sender->avatar_url,
                'role' => $message->sender->role,
            ],
        ];
    }
}
