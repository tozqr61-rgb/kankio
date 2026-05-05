<?php

namespace Tests\Feature;

use App\Events\MessageSent;
use App\Events\MessagesRead;
use App\Events\UserTyping;
use App\Models\AdminAction;
use App\Models\AppRelease;
use App\Models\InviteCode;
use App\Models\Message;
use App\Models\RoomMusicState;
use App\Models\Room;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
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

    public function test_should_bootstrap_room_messages_when_room_is_accessible(): void
    {
        $user = User::factory()->create();
        $room = Room::create(['name' => 'Bootstrap', 'type' => 'global', 'created_by' => $user->id]);

        Message::create([
            'room_id' => $room->id,
            'sender_id' => $user->id,
            'content' => 'Visible message',
        ]);

        $archivedMessage = Message::create([
            'room_id' => $room->id,
            'sender_id' => $user->id,
            'content' => 'Archived message',
        ]);
        $archivedMessage->forceFill(['is_archived' => true])->save();

        $this->actingAs($user)
            ->getJson(route('api.chat.bootstrap', $room->id))
            ->assertOk()
            ->assertJsonPath('room.id', $room->id)
            ->assertJsonPath('room.name', 'Bootstrap')
            ->assertJsonPath('archived_count', 1)
            ->assertJsonCount(1, 'messages')
            ->assertJsonPath('messages.0.content', 'Visible message');

        $this->assertDatabaseHas('room_reads', [
            'user_id' => $user->id,
            'room_id' => $room->id,
        ]);
    }

    public function test_should_return403_when_bootstrap_private_room_access_denied(): void
    {
        $owner = User::factory()->create();
        $outsider = User::factory()->create();

        $room = Room::create([
            'name' => 'Private Bootstrap',
            'type' => 'private',
            'created_by' => $owner->id,
        ]);

        $this->actingAs($outsider)
            ->getJson(route('api.chat.bootstrap', $room->id))
            ->assertForbidden();
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

    public function test_invisible_presence_hides_online_typing_and_read_receipts(): void
    {
        $sender = User::factory()->create();
        $reader = User::factory()->create();
        $room = Room::create(['name' => 'Global', 'type' => 'global', 'created_by' => $sender->id]);
        $message = Message::create([
            'room_id' => $room->id,
            'sender_id' => $sender->id,
            'content' => 'Read me',
        ]);

        Cache::put('online_users', [
            $reader->id => [
                'id' => $reader->id,
                'username' => $reader->username,
                'last_seen' => now()->timestamp,
            ],
        ], 300);

        $this->actingAs($reader)
            ->postJson(route('api.profile.presence_mode'), ['presence_mode' => 'invisible'])
            ->assertOk()
            ->assertJsonPath('presence_mode', 'invisible');

        $this->actingAs($reader)
            ->postJson(route('api.presence.update'), ['status' => 'online'])
            ->assertOk()
            ->assertJsonCount(0, 'users');

        $reader = $reader->fresh();
        Event::fake([UserTyping::class, MessagesRead::class]);

        $this->actingAs($reader)
            ->postJson(route('api.message.typing', $room->id), ['is_typing' => true])
            ->assertOk()
            ->assertJsonPath('presence_mode', 'invisible');
        Event::assertNotDispatched(UserTyping::class);

        $this->actingAs($reader)
            ->postJson(route('api.message.seen', $room->id), ['message_ids' => [$message->id]])
            ->assertOk()
            ->assertJsonPath('marked', 0);
        Event::assertNotDispatched(MessagesRead::class);

        $this->assertDatabaseMissing('message_reads', [
            'message_id' => $message->id,
            'user_id' => $reader->id,
        ]);
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
        $this->assertContains('admin', $route->gatherMiddleware());
        $this->assertContains('throttle:10,1', $route->gatherMiddleware());

        $response = $this->postJson(route('stay.audio'), [
            'audio' => UploadedFile::fake()->create('voice.webm', 10, 'audio/webm'),
        ]);

        $this->assertNotContains($response->getStatusCode(), [200, 201]);
    }

    public function test_should_unlock_baglanti_kal_content_without_leaking_letter(): void
    {
        config([
            'services.baglantikal.access_pin' => '1071',
            'services.baglantikal.letter_pin' => '2022',
        ]);

        $this->get(route('stay.connected'))
            ->assertOk()
            ->assertDontSee('Sabahlara Kadar Uyumayanlar')
            ->assertDontSee('Bu mektubu açabilmek');

        $this->postJson(route('stay.unlock'), ['pin' => '0000'])
            ->assertStatus(422);

        $this->postJson(route('stay.unlock'), ['pin' => '1071'])
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonMissingPath('content.mektup')
            ->assertJsonStructure(['content' => ['muzik_id', 'achievements', 'memories', 'boxes']]);

        $this->postJson(route('stay.letter.unlock'), ['pin' => '2022'])
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonStructure(['mektup' => ['p1', 'p2', 'p3', 'p4']]);
    }

    public function test_should_allow_only_admins_to_manage_baglanti_kal_content_and_audio(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();
        $admin = User::factory()->create(['role' => 'admin']);
        $payload = [
            'muzik_id' => 'dQw4w9WgXcQ',
            'achievements' => [],
            'memories' => [],
            'boxes' => [],
            'mektup' => [
                'p1' => 'Merhaba',
                'p2' => 'Paragraf iki',
                'p3' => 'Paragraf üç',
                'p4' => 'Kapanış',
            ],
        ];

        $this->actingAs($user)
            ->postJson(route('stay.save'), $payload)
            ->assertForbidden();

        $this->actingAs($user)
            ->postJson(route('stay.audio'), [
                'audio' => UploadedFile::fake()->create('voice.webm', 10, 'audio/webm'),
            ])
            ->assertForbidden();

        $this->actingAs($admin)
            ->postJson(route('stay.save'), $payload)
            ->assertOk()
            ->assertJsonPath('ok', true);

        $this->actingAs($admin)
            ->postJson(route('stay.audio'), [
                'audio' => UploadedFile::fake()->create('voice.webm', 10, 'audio/webm'),
            ])
            ->assertOk()
            ->assertJsonStructure(['audio_url']);
    }

    public function test_should_apply_auth_rate_limit_middleware(): void
    {
        $loginRoute = app('router')->getRoutes()->getByName('login.post');
        $registerRoute = app('router')->getRoutes()->getByName('register.post');

        $this->assertContains('throttle:5,1', $loginRoute->gatherMiddleware());
        $this->assertContains('throttle:5,5', $registerRoute->gatherMiddleware());
    }

    public function test_should_block_private_room_music_state_for_outsider(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $outsider = User::factory()->create();
        $room = Room::create(['name' => 'Private Music', 'type' => 'private', 'created_by' => $owner->id]);
        $room->members()->attach($member->id, ['role' => 'member']);

        RoomMusicState::create([
            'room_id' => $room->id,
            'video_id' => 'dQw4w9WgXcQ',
            'video_title' => 'Private song',
            'queue' => [],
        ]);

        $this->actingAs($outsider)
            ->getJson(route('api.music.state', $room->id))
            ->assertForbidden();

        $this->actingAs($member)
            ->getJson(route('api.music.state', $room->id))
            ->assertOk()
            ->assertJsonPath('video_title', 'Private song');
    }

    public function test_should_restrict_global_room_creation_to_admins_but_allow_private_rooms(): void
    {
        $user = User::factory()->create();
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($user)
            ->postJson(route('api.room.store'), ['name' => 'Nope', 'type' => 'global'])
            ->assertForbidden();

        $this->actingAs($user)
            ->postJson(route('api.room.store'), ['name' => 'Private ok', 'type' => 'private'])
            ->assertCreated();

        $this->actingAs($admin)
            ->postJson(route('api.room.store'), ['name' => 'Global ok', 'type' => 'global'])
            ->assertCreated();
    }

    public function test_should_record_admin_audit_for_destructive_actions(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $target = User::factory()->create();

        $this->actingAs($admin)
            ->deleteJson(route('admin.user.delete', $target->id))
            ->assertOk();

        $this->assertDatabaseHas('admin_actions', [
            'actor_id' => $admin->id,
            'action' => 'user.delete',
            'target_type' => User::class,
            'target_id' => (string) $target->id,
        ]);
    }

    public function test_should_validate_app_release_host_and_checksum(): void
    {
        config(['services.app_release.allowed_hosts' => 'drive.google.com']);
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->postJson(route('admin.app_release.post'), [
                'version' => '1.2.3',
                'drive_link' => 'https://example.com/kankio.apk',
                'checksum' => str_repeat('a', 64),
            ])
            ->assertStatus(422);

        $this->actingAs($admin)
            ->postJson(route('admin.app_release.post'), [
                'version' => '1.2.3',
                'drive_link' => 'https://drive.google.com/file/d/abc/view',
                'checksum' => str_repeat('b', 64),
            ])
            ->assertOk();

        $this->assertDatabaseHas('app_releases', [
            'version' => '1.2.3',
            'checksum' => str_repeat('b', 64),
        ]);
    }

    public function test_should_consume_invite_atomically_on_register(): void
    {
        $invite = InviteCode::create(['code' => 'KNK-'.strtoupper(bin2hex(random_bytes(5)))]);
        $room = Room::create(['name' => 'Global', 'type' => 'global']);

        $response = $this->post(route('register.post'), [
            'username' => 'newuser',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
            'invite_code' => $invite->code,
        ]);

        $response->assertRedirect(route('chat.index'));
        $this->assertDatabaseHas('invite_codes', [
            'id' => $invite->id,
            'is_used' => true,
        ]);
        $this->assertDatabaseHas('room_members', [
            'room_id' => $room->id,
            'role' => 'member',
        ]);
    }
}
