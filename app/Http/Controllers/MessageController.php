<?php

namespace App\Http\Controllers;

use App\Models\Message;
use App\Models\Room;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class MessageController extends Controller
{
    public function store(Request $request, $roomId)
    {
        $user = Auth::user();
        $room = Room::findOrFail($roomId);

        // Announcements room: only admins can post
        if ($room->type === 'announcements' && ! $user->isAdmin()) {
            return response()->json(['error' => 'Bu odaya yalnızca yöneticiler yazabilir'], 403);
        }

        // Private room access check
        if ($room->type === 'private') {
            $isMember = $room->members()->where('user_id', $user->id)->exists();
            if (! $isMember && ! $user->isAdmin()) {
                return response()->json(['error' => 'Erişim reddedildi'], 403);
            }
        }

        $isVoiceMessage = $request->hasFile('audio');

        if ($isVoiceMessage) {
            $request->validate([
                'audio'          => 'required|file|mimes:webm,ogg,mp3,wav,mp4|max:10240',
                'audio_duration' => 'nullable|integer|min:1|max:300',
                'reply_to'       => 'nullable|integer|exists:messages,id',
            ]);

            $file     = $request->file('audio');
            $filename = $user->id . '_' . time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
            $file->storeAs('voice_messages', $filename, 'public');
            $audioUrl = '/storage/voice_messages/' . $filename;

            $message = Message::create([
                'room_id'        => $roomId,
                'sender_id'      => $user->id,
                'content'        => '🎤 Sesli mesaj',
                'audio_url'      => $audioUrl,
                'audio_duration' => $request->audio_duration ?? null,
                'reply_to'       => $request->reply_to,
            ]);
        } else {
            $rules = ['content' => 'required|string|max:4000', 'reply_to' => 'nullable|integer|exists:messages,id'];
            if ($room->type === 'announcements') {
                $rules['title'] = 'required|string|max:200';
            }
            $request->validate($rules);

            $message = Message::create([
                'room_id'   => $roomId,
                'sender_id' => $user->id,
                'content'   => $request->content,
                'title'     => $request->title ?? null,
                'reply_to'  => $request->reply_to,
            ]);
        }

        $message->load(['sender', 'replyToMessage.sender']);

        return response()->json([
            'id'                => $message->id,
            'title'             => $message->title,
            'content'           => $message->content,
            'audio_url'         => $message->audio_url,
            'audio_duration'    => $message->audio_duration,
            'is_system_message' => $message->is_system_message,
            'reply_to'          => $message->reply_to,
            'reply_message'     => $message->replyToMessage ? [
                'content'  => $message->replyToMessage->content,
                'username' => $message->replyToMessage->sender?->username ?? 'Silinmiş',
            ] : null,
            'created_at' => $message->created_at->toISOString(),
            'sender'     => [
                'id'         => $user->id,
                'username'   => $user->username,
                'avatar_url' => $user->avatar_url,
                'role'       => $user->role,
            ],
        ], 201);
    }

    public function destroy($roomId, $messageId)
    {
        $user    = Auth::user();
        $message = Message::where('room_id', $roomId)->findOrFail($messageId);

        if (! $user->isAdmin() && $message->sender_id !== $user->id) {
            return response()->json(['error' => 'Erişim reddedildi'], 403);
        }

        $message->delete();

        return response()->json(['ok' => true]);
    }
}
