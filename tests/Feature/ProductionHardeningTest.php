<?php

namespace Tests\Feature;

use App\Events\MessageSent;
use App\Events\MessagesRead;
use App\Events\UserTyping;
use App\Models\Message;
use App\Models\Room;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class ProductionHardeningTest extends TestCase
{
    use RefreshDatabase;

    public function test_should_return403_when_private_room_access_denied(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $outsider = User::factory()->create();

        $room = Room::create([
            'name' => 'Private',
            'type' => 'private',
            'created_by' => $owner->id,
        ]);
        $room->members()->attach($member->id, ['role' => 'member']);

        $this->actingAs($outsider)
            ->get(route('chat.room', $room->id))
            ->assertForbidden();

        $this->actingAs($member)
            ->get(route('chat.room', $room->id))
            ->assertOk();
    }

    public function test_should_reject_voice_join_when_user_is_not_private_room_member(): void
    {
        config([
            'services.livekit.url' => 'wss://livekit.example.test',
            'services.livekit.key' => 'test-livekit-key',
            'services.livekit.secret' => 'test-livekit-secret-with-32-characters',
        ]);

        $owner = User::factory()->create();
        $member = User::factory()->create();
        $outsider = User::factory()->create();

        $room = Room::create([
            'name' => 'Private Voice',
            'type' => 'private',
            'created_by' => $owner->id,
        ]);
        $room->members()->attach($member->id, ['role' => 'member']);

        $this->actingAs($outsider)
            ->postJson(route('api.voice.join', $room->id))
            ->assertForbidden();

        $this->actingAs($member)
            ->postJson(route('api.voice.join', $room->id))
            ->assertOk()
            ->assertJsonStructure([
                'in_voice',
                'livekit_token',
                'livekit_url',
                'participants',
                'is_muted',
                'is_deafened',
                'can_speak',
                'connection_quality',
            ]);

        $this->assertDatabaseHas('voice_sessions', [
            'room_id' => $room->id,
            'user_id' => $member->id,
            'is_active' => true,
        ]);
    }

    public function test_should_return_json_error_when_live_kit_config_is_missing(): void
    {
        config([
            'services.livekit.url' => '',
            'services.livekit.key' => '',
            'services.livekit.secret' => '',
        ]);

        $user = User::factory()->create();
        $room = Room::create(['name' => 'Voice', 'type' => 'global', 'created_by' => $user->id]);

        $this->actingAs($user)
            ->postJson(route('api.voice.join', $room->id))
            ->assertStatus(503)
            ->assertHeader('content-type', 'application/json')
            ->assertJsonFragment(['error' => 'LiveKit sunucu bilgileri eksik. LIVEKIT_URL/API_KEY/API_SECRET ayarlarını kontrol edin.']);
    }

    public function test_should_return422_when_reply_references_message_from_another_room(): void
    {
        $user = User::factory()->create();
        $sourceRoom = Room::create(['name' => 'Source', 'type' => 'global', 'created_by' => $user->id]);
        $targetRoom = Room::create(['name' => 'Target', 'type' => 'global', 'created_by' => $user->id]);

        $foreignMessage = Message::create([
            'room_id' => $sourceRoom->id,
            'sender_id' => $user->id,
            'content' => 'Do not leak this reply target',
        ]);

        $this->actingAs($user)
            ->postJson(route('api.message.store', $targetRoom->id), [
                'content' => 'Reply attempt',
                'reply_to' => $foreignMessage->id,
            ])
            ->assertStatus(422)
            ->assertJson(['error' => 'Yanıtlanan mesaj bu odada değil']);
    }

    public function test_should_broadcast_realtime_chat_events_when_message_typing_and_read_change(): void
    {
        $sender = User::factory()->create();
        $reader = User::factory()->create();
        $room = Room::create(['name' => 'Global', 'type' => 'global', 'created_by' => $sender->id]);

        $message = Message::create([
            'room_id' => $room->id,
            'sender_id' => $sender->id,
            'content' => 'Read me',
        ]);

        Event::fake([MessageSent::class, UserTyping::class, MessagesRead::class]);

        $this->actingAs($sender)
            ->postJson(route('api.message.store', $room->id), ['content' => 'Realtime'])
            ->assertCreated();
        Event::assertDispatched(MessageSent::class);

        $this->actingAs($reader)
            ->postJson(route('api.message.typing', $room->id), ['is_typing' => true])
            ->assertOk();
        Event::assertDispatched(UserTyping::class);

        $this->actingAs($reader)
            ->postJson(route('api.message.seen', $room->id), ['message_ids' => [$message->id]])
            ->assertOk();
        Event::assertDispatched(MessagesRead::class);
    }

    public function test_should_allow_only_voice_moderators_to_mute_kick_and_change_speak_permission(): void
    {
        $owner = User::factory()->create();
        $target = User::factory()->create();
        $nonModerator = User::factory()->create();

        $room = Room::create(['name' => 'Voice', 'type' => 'global', 'created_by' => $owner->id]);
        $room->members()->attach($target->id, ['role' => 'member']);

        DB::table('voice_sessions')->insert([
            [
                'room_id' => $room->id,
                'user_id' => $target->id,
                'is_active' => true,
                'is_muted' => false,
                'joined_at' => now(),
                'last_ping' => now(),
            ],
            [
                'room_id' => $room->id,
                'user_id' => $nonModerator->id,
                'is_active' => true,
                'is_muted' => false,
                'joined_at' => now(),
                'last_ping' => now(),
            ],
        ]);

        $this->actingAs($nonModerator)
            ->postJson(route('api.voice.mute_all', $room->id))
            ->assertForbidden();

        $this->actingAs($owner)
            ->postJson(route('api.voice.speak_permission', [$room->id, $target->id]), ['can_speak' => false])
            ->assertOk();

        $this->assertDatabaseHas('voice_sessions', [
            'room_id' => $room->id,
            'user_id' => $target->id,
            'can_speak' => false,
            'is_muted' => true,
        ]);

        $this->actingAs($owner)
            ->postJson(route('api.voice.settings', $room->id), ['voice_requires_permission' => true])
            ->assertOk();

        $this->assertDatabaseHas('voice_sessions', [
            'room_id' => $room->id,
            'user_id' => $nonModerator->id,
            'can_speak' => false,
            'is_muted' => true,
        ]);

        $this->actingAs($owner)
            ->postJson(route('api.voice.settings', $room->id), ['voice_members_only' => true])
            ->assertOk();

        $this->assertDatabaseMissing('voice_sessions', [
            'room_id' => $room->id,
            'user_id' => $nonModerator->id,
        ]);

        $this->actingAs($owner)
            ->deleteJson(route('api.voice.kick', [$room->id, $target->id]))
            ->assertOk();

        $this->assertDatabaseMissing('voice_sessions', [
            'room_id' => $room->id,
            'user_id' => $target->id,
        ]);
    }

    public function test_should_block_public_baglanti_kal_audio_upload_when_unauthenticated(): void
    {
        $route = app('router')->getRoutes()->getByName('stay.audio');

        $this->assertContains('auth', $route->gatherMiddleware());
        $this->assertContains('throttle:10,1', $route->gatherMiddleware());

        $response = $this->postJson(route('stay.audio'), [
            'audio' => UploadedFile::fake()->create('voice.webm', 10, 'audio/webm'),
        ]);

        $this->assertNotContains($response->getStatusCode(), [200, 201]);
    }
}
