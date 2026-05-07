<?php

namespace Tests\Feature;

use App\Events\MessageSent;
use App\Events\MessagesRead;
use App\Events\UserTyping;
use App\Models\AdminAction;
use App\Models\AppRelease;
use App\Models\BaglantiKalContent;
use App\Models\Bot;
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

    public function test_mark_seen_validates_message_ids_and_is_throttled(): void
    {
        $user = User::factory()->create();
        $room = Room::create(['name' => 'Global', 'type' => 'global', 'created_by' => $user->id]);

        $this->actingAs($user)
            ->postJson(route('api.message.seen', $room->id), [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('message_ids');

        $this->actingAs($user)
            ->postJson(route('api.message.seen', $room->id), ['message_ids' => ['bad']])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('message_ids.0');

        $this->actingAs($user)
            ->postJson(route('api.message.seen', $room->id), ['message_ids' => range(1, 101)])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('message_ids');

        $route = app('router')->getRoutes()->getByName('api.message.seen');
        $this->assertContains('throttle:60,1', $route->gatherMiddleware());
    }

    public function test_invisible_presence_hides_online_typing_and_read_receipts(): void
    {
        $sender = User::factory()->create();
        $reader = User::factory()->create(['role' => 'admin']);
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

    public function test_invisible_presence_mode_is_admin_only(): void
    {
        $user = User::factory()->create();
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($user)
            ->postJson(route('api.profile.presence_mode'), ['presence_mode' => 'invisible'])
            ->assertForbidden();

        $this->actingAs($admin)
            ->postJson(route('api.profile.presence_mode'), ['presence_mode' => 'invisible'])
            ->assertOk()
            ->assertJsonPath('presence_mode', 'invisible');
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

    public function test_maintenance_mode_uses_central_whitelist_and_json_response(): void
    {
        Cache::forever('maintenance_mode', true);
        $user = User::factory()->create();
        $admin = User::factory()->create(['role' => 'admin']);

        $this->get('/')
            ->assertRedirect('/maintenance');

        $this->get('/maintenance')
            ->assertOk();

        $this->get('/login')
            ->assertOk();

        $this->get('/up')
            ->assertOk();

        $this->actingAs($user)
            ->getJson(route('api.users'))
            ->assertStatus(503)
            ->assertJsonPath('message', 'Bakım modu aktif. Lütfen daha sonra tekrar deneyin.');

        $this->actingAs($admin)
            ->get(route('admin.dashboard'))
            ->assertOk();

        Cache::forget('maintenance_mode');
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

    public function test_music_commands_require_room_moderator_permission_even_when_user_is_in_voice(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $moderator = User::factory()->create();
        $room = Room::create(['name' => 'Music Control', 'type' => 'global', 'created_by' => $owner->id]);
        $room->members()->attach($member->id, ['role' => 'member']);
        $room->members()->attach($moderator->id, ['role' => 'admin']);

        DB::table('voice_sessions')->insert([
            ['room_id' => $room->id, 'user_id' => $member->id, 'is_active' => true, 'joined_at' => now(), 'last_ping' => now()],
            ['room_id' => $room->id, 'user_id' => $moderator->id, 'is_active' => true, 'joined_at' => now(), 'last_ping' => now()],
        ]);
        RoomMusicState::create([
            'room_id' => $room->id,
            'video_id' => 'dQw4w9WgXcQ',
            'video_title' => 'Controlled song',
            'is_playing' => true,
            'position' => 0,
            'video_duration' => 120,
            'started_at_unix' => time(),
            'queue' => [],
        ]);

        $this->actingAs($member)
            ->postJson(route('api.music.command', $room->id), ['command' => '/stop'])
            ->assertForbidden()
            ->assertJsonPath('error', 'Müzik kontrol yetkiniz yok');

        $this->assertDatabaseHas('room_music_states', [
            'room_id' => $room->id,
            'is_playing' => true,
            'updated_by' => null,
        ]);

        $this->actingAs($moderator)
            ->postJson(route('api.music.command', $room->id), ['command' => '/stop'])
            ->assertOk()
            ->assertJsonPath('ok', true);

        $this->assertDatabaseHas('room_music_states', [
            'room_id' => $room->id,
            'is_playing' => false,
            'updated_by' => $moderator->id,
        ]);
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

    public function test_user_directory_is_filtered_searchable_paginated_and_throttled(): void
    {
        $viewer = User::factory()->create(['username' => 'viewer']);
        $visibleA = User::factory()->create(['username' => 'ada_alpha']);
        $visibleB = User::factory()->create(['username' => 'ada_zulu']);
        User::factory()->create(['username' => 'ada_banned', 'is_banned' => true]);
        User::factory()->create(['username' => 'ada_bot', 'is_bot' => true]);
        User::factory()->create(['username' => 'ada_deleted', 'deactivated_at' => now()]);

        $this->actingAs($viewer)
            ->getJson(route('api.users', ['q' => 'ada', 'per_page' => 1]))
            ->assertOk()
            ->assertJsonPath('users.0.id', $visibleA->id)
            ->assertJsonPath('pagination.has_more', true)
            ->assertJsonPath('pagination.per_page', 1)
            ->assertJsonMissing(['username' => 'ada_banned'])
            ->assertJsonMissing(['username' => 'ada_bot'])
            ->assertJsonMissing(['username' => 'ada_deleted'])
            ->assertJsonMissing(['id' => $viewer->id]);

        $this->actingAs($viewer)
            ->getJson(route('api.users', ['q' => 'ada', 'per_page' => 1, 'page' => 2]))
            ->assertOk()
            ->assertJsonPath('users.0.id', $visibleB->id);

        $route = app('router')->getRoutes()->getByName('api.users');
        $this->assertContains('throttle:30,1', $route->gatherMiddleware());
    }

    public function test_room_creation_rejects_ineligible_members(): void
    {
        $user = User::factory()->create();
        $eligible = User::factory()->create();
        $banned = User::factory()->create(['is_banned' => true]);

        $this->actingAs($user)
            ->postJson(route('api.room.store'), [
                'name' => 'Private nope',
                'type' => 'private',
                'members' => [$eligible->id, $banned->id],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('members.1');
    }

    public function test_private_room_creation_persists_room_and_members_together(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();

        $response = $this->actingAs($owner)
            ->postJson(route('api.room.store'), [
                'name' => 'Private atomic',
                'type' => 'private',
                'members' => [$member->id],
            ])
            ->assertCreated();

        $roomId = $response->json('id');
        $this->assertDatabaseHas('rooms', [
            'id' => $roomId,
            'name' => 'Private atomic',
            'type' => 'private',
            'created_by' => $owner->id,
        ]);
        $this->assertDatabaseHas('room_members', [
            'room_id' => $roomId,
            'user_id' => $owner->id,
            'role' => 'owner',
        ]);
        $this->assertDatabaseHas('room_members', [
            'room_id' => $roomId,
            'user_id' => $member->id,
            'role' => 'member',
        ]);
    }

    public function test_should_record_admin_audit_for_destructive_actions(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $target = User::factory()->create();
        $room = Room::create(['name' => 'Archive me', 'type' => 'global', 'created_by' => $admin->id]);

        $this->actingAs($admin)
            ->deleteJson(route('admin.user.delete', $target->id))
            ->assertOk();

        $this->assertDatabaseHas('admin_actions', [
            'actor_id' => $admin->id,
            'action' => 'user.deactivate_anonymize',
            'target_type' => User::class,
            'target_id' => (string) $target->id,
        ]);
        $this->assertDatabaseHas('users', [
            'id' => $target->id,
            'username' => 'deleted_user_'.$target->id,
            'is_banned' => true,
        ]);

        $this->actingAs($admin)
            ->deleteJson(route('admin.room.delete', $room->id))
            ->assertOk();

        $this->assertDatabaseHas('rooms', [
            'id' => $room->id,
            'is_archived' => true,
            'archived_by' => $admin->id,
        ]);
    }

    public function test_admin_cannot_ban_self_or_remove_last_active_admin(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->postJson(route('admin.ban', $admin->id))
            ->assertForbidden()
            ->assertJsonPath('error', 'Kendi hesabını banlayamazsın');

        $this->assertDatabaseHas('users', [
            'id' => $admin->id,
            'is_banned' => false,
            'role' => 'admin',
        ]);
        $admin->update(['is_banned' => true]);

        $operator = User::factory()->create(['role' => 'admin']);
        $lastAdmin = User::factory()->create(['role' => 'admin']);
        $operator->update(['is_banned' => true]);
        $controller = app(\App\Http\Controllers\AdminController::class);
        $request = \Illuminate\Http\Request::create('/admin/users/'.$lastAdmin->id.'/ban', 'POST');

        $this->actingAs($operator);

        $banResponse = $controller->banUser($request, $lastAdmin->id);
        $this->assertSame(403, $banResponse->getStatusCode());
        $this->assertSame('Son admin banlanamaz', $banResponse->getData(true)['error']);

        $roleRequest = \Illuminate\Http\Request::create('/admin/users/'.$lastAdmin->id.'/role', 'POST', ['role' => 'user']);
        $roleResponse = $controller->toggleAdmin($roleRequest, $lastAdmin->id);
        $this->assertSame(403, $roleResponse->getStatusCode());
        $this->assertSame('Son admin rolü düşürülemez', $roleResponse->getData(true)['error']);

        $this->assertDatabaseHas('users', [
            'id' => $lastAdmin->id,
            'is_banned' => false,
            'role' => 'admin',
        ]);
    }

    public function test_admin_can_ban_or_demote_admin_when_another_active_admin_remains(): void
    {
        $operator = User::factory()->create(['role' => 'admin']);
        $spareAdmin = User::factory()->create(['role' => 'admin']);
        $target = User::factory()->create(['role' => 'admin']);

        $this->actingAs($operator)
            ->postJson(route('admin.ban', $target->id))
            ->assertOk()
            ->assertJsonPath('is_banned', true);

        $target->update(['is_banned' => false]);

        $this->actingAs($operator)
            ->postJson(route('admin.toggle.role', $target->id), ['role' => 'user'])
            ->assertOk()
            ->assertJsonPath('role', 'user');

        $this->assertDatabaseHas('users', [
            'id' => $spareAdmin->id,
            'role' => 'admin',
            'is_banned' => false,
        ]);
    }

    public function test_message_delete_soft_deletes_and_records_actor(): void
    {
        $owner = User::factory()->create();
        $room = Room::create(['name' => 'Soft Delete', 'type' => 'global', 'created_by' => $owner->id]);
        $message = Message::create([
            'room_id' => $room->id,
            'sender_id' => $owner->id,
            'content' => 'Delete me',
        ]);

        $this->actingAs($owner)
            ->deleteJson(route('api.message.destroy', [$room->id, $message->id]))
            ->assertOk();

        $this->assertSoftDeleted('messages', ['id' => $message->id]);
        $this->assertDatabaseHas('messages', [
            'id' => $message->id,
            'deleted_by' => $owner->id,
        ]);
    }

    public function test_deactivated_and_bot_users_cannot_login(): void
    {
        $deactivated = User::factory()->create([
            'username' => 'pasif',
            'email' => 'pasif@kank.com',
            'password' => bcrypt('secret123'),
            'deactivated_at' => now(),
        ]);
        $bot = User::factory()->create([
            'username' => 'botuser',
            'email' => 'botuser@kank.com',
            'password' => bcrypt('secret123'),
            'is_bot' => true,
        ]);

        $this->post(route('login.post'), ['username' => $deactivated->username, 'password' => 'secret123'])
            ->assertSessionHasErrors('username');

        $this->post(route('login.post'), ['username' => $bot->username, 'password' => 'secret123'])
            ->assertSessionHasErrors('username');
    }

    public function test_oversight_access_requires_reason_logs_and_unlocks_broadcast_access(): void
    {
        $owner = User::factory()->create();
        $oversight = User::factory()->create(['role' => 'oversight_admin']);
        $room = Room::create(['name' => 'Private Audit', 'type' => 'private', 'created_by' => $owner->id]);

        $this->actingAs($oversight)
            ->postJson(route('admin.oversight.access'), ['room_id' => $room->id, 'reason' => 'short'])
            ->assertStatus(422);

        $this->assertFalse(canAccessBroadcastRoom($oversight, $room->id));

        $this->actingAs($oversight)
            ->postJson(route('admin.oversight.access'), [
                'room_id' => $room->id,
                'reason' => 'Moderasyon denetim kaydı için erişim.',
            ])
            ->assertOk()
            ->assertJsonPath('ok', true);

        $this->assertDatabaseHas('admin_actions', [
            'actor_id' => $oversight->id,
            'action' => 'oversight.room_access',
            'target_id' => (string) $room->id,
        ]);
        $this->assertDatabaseHas('admin_actions', [
            'actor_id' => $oversight->id,
            'action' => 'oversight.room_access',
            'target_id' => (string) $room->id,
            'payload->reason' => 'Moderasyon denetim kaydı için erişim.',
        ]);
        $this->assertDatabaseMissing('messages', [
            'room_id' => $room->id,
            'is_system_message' => true,
            'content' => 'Denetim erişimi başlatıldı. Gerekçe: Moderasyon denetim kaydı için erişim.',
        ]);
        $this->assertTrue(canAccessBroadcastRoom($oversight->fresh(), $room->id));
    }

    public function test_baglanti_kal_content_is_saved_to_database(): void
    {
        config([
            'services.baglantikal.access_pin' => '1071',
            'services.baglantikal.letter_pin' => '2022',
        ]);
        $admin = User::factory()->create(['role' => 'admin']);
        $payload = [
            'muzik_id' => 'abc123',
            'achievements' => [['icon' => '*', 'title' => 'Baslik', 'desc' => 'Aciklama']],
            'memories' => [['icon' => '*', 'caption' => 'Ani', 'detail' => 'Detay', 'img' => null]],
            'boxes' => [['fi' => '*', 'bi' => '*', 'bt' => 'Kutu', 'bc' => 'Icerik', 'audio' => null]],
            'mektup' => ['p1' => 'A', 'p2' => 'B', 'p3' => 'C', 'p4' => 'D'],
        ];

        $this->actingAs($admin)
            ->postJson(route('stay.save'), $payload)
            ->assertOk();

        $this->assertDatabaseHas('baglanti_kal_contents', ['id' => 1, 'updated_by' => $admin->id]);
        $this->assertSame('abc123', BaglantiKalContent::find(1)->content['muzik_id']);

        $this->postJson(route('stay.unlock'), ['pin' => '1071'])
            ->assertOk()
            ->assertJsonPath('content.muzik_id', 'abc123')
            ->assertJsonMissingPath('content.mektup');
    }

    public function test_bot_event_dispatch_is_idempotent(): void
    {
        $room = Room::create(['name' => 'Bot Event', 'type' => 'global']);
        $botUser = User::factory()->create(['is_bot' => true, 'presence_mode' => 'invisible']);
        Bot::create([
            'user_id' => $botUser->id,
            'bot_key' => \App\Services\Bots\Bots\GameBot::BOT_KEY,
            'display_name' => 'Game Bot',
            'is_active' => true,
        ]);

        Event::fake([MessageSent::class]);
        $manager = app(\App\Services\Bots\BotManager::class);
        $manager->broadcastEvent('game.round_started', ['event_id' => 'round-1', 'room_id' => $room->id, 'round_no' => 1], $room);
        $manager->broadcastEvent('game.round_started', ['event_id' => 'round-1', 'room_id' => $room->id, 'round_no' => 1], $room);

        $this->assertLessThanOrEqual(1, Message::where('room_id', $room->id)->where('sender_id', $botUser->id)->count());
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
