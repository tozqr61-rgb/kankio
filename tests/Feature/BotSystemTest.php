<?php

namespace Tests\Feature;

use App\Events\MessageSent;
use App\Models\Bot;
use App\Models\Message;
use App\Models\Room;
use App\Models\User;
use App\Services\Bots\BotCommandParser;
use App\Services\Bots\BotManager;
use App\Services\Bots\BotMessageService;
use App\Services\Bots\Bots\DjBot;
use App\Services\Bots\Bots\GameBot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class BotSystemTest extends TestCase
{
    use RefreshDatabase;

    private Room $room;
    private User $user;
    private User $admin;
    private Bot  $gameBot;
    private Bot  $djBot;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->user  = User::factory()->create();
        $this->room  = Room::create([
            'name'       => 'Test Room',
            'type'       => 'global',
            'created_by' => $this->admin->id,
        ]);

        $gameBotUser = User::create([
            'username'      => 'oyun_botu',
            'email'         => 'bot+game@kankio.internal',
            'password'      => bcrypt('secret'),
            'is_bot'        => true,
            'presence_mode' => 'invisible',
        ]);
        $this->gameBot = Bot::create([
            'user_id'      => $gameBotUser->id,
            'bot_key'      => GameBot::BOT_KEY,
            'display_name' => '🎮 Oyun Botu',
            'is_active'    => true,
        ]);

        $djBotUser = User::create([
            'username'      => 'dj_bot',
            'email'         => 'bot+dj@kankio.internal',
            'password'      => bcrypt('secret'),
            'is_bot'        => true,
            'presence_mode' => 'invisible',
        ]);
        $this->djBot = Bot::create([
            'user_id'      => $djBotUser->id,
            'bot_key'      => DjBot::BOT_KEY,
            'display_name' => '🎵 DJ Bot',
            'is_active'    => true,
        ]);
    }

    public function test_bot_command_parser_detects_slash_commands(): void
    {
        $parser = app(BotCommandParser::class);

        $this->assertTrue($parser->isCommand('/oyun başlat'));
        $this->assertTrue($parser->isCommand('/çal mor ve ötesi'));
        $this->assertFalse($parser->isCommand('normal mesaj'));
        $this->assertFalse($parser->isCommand('mesaj /içindeki'));
    }

    public function test_bot_command_parser_extracts_command_and_args(): void
    {
        $parser = app(BotCommandParser::class);
        $ctx    = $parser->parse('/oyun başlat', $this->room, $this->user);

        $this->assertNotNull($ctx);
        $this->assertSame('oyun', $ctx->command);
        $this->assertSame(['başlat'], $ctx->args);
        $this->assertSame($this->room->id, $ctx->room->id);
        $this->assertSame($this->user->id, $ctx->sender->id);
    }

    public function test_bot_command_parser_returns_null_for_non_commands(): void
    {
        $parser = app(BotCommandParser::class);
        $this->assertNull($parser->parse('merhaba', $this->room, $this->user));
    }

    public function test_game_bot_handles_oyun_help_command(): void
    {
        Event::fake([MessageSent::class]);

        $manager = app(BotManager::class);
        $result  = $manager->dispatch('/oyun yardım', $this->room, $this->user);

        $this->assertNotNull($result);
        $this->assertTrue($result->handled);

        $this->assertDatabaseHas('messages', [
            'room_id'          => $this->room->id,
            'sender_id'        => $this->gameBot->user_id,
            'is_system_message'=> 1,
        ]);
    }

    public function test_game_bot_handles_oyun_status_when_no_game(): void
    {
        Event::fake([MessageSent::class]);

        $manager = app(BotManager::class);
        $result  = $manager->dispatch('/oyun durum', $this->room, $this->user);

        $this->assertNotNull($result);
        $this->assertTrue($result->handled);

        $msg = Message::where('room_id', $this->room->id)
            ->where('sender_id', $this->gameBot->user_id)
            ->first();
        $this->assertNotNull($msg);
        $this->assertStringContainsString('aktif bir oyun yok', $msg->content);
    }

    public function test_game_bot_starts_game_with_slash_command(): void
    {
        Event::fake([MessageSent::class]);

        $this->room->members()->attach($this->user->id, ['role' => 'member']);

        $manager = app(BotManager::class);
        $result  = $manager->dispatch('/oyun başlat', $this->room, $this->user);

        $this->assertNotNull($result);
        $this->assertTrue($result->handled);

        $msg = Message::where('room_id', $this->room->id)
            ->where('sender_id', $this->gameBot->user_id)
            ->first();
        $this->assertNotNull($msg);
        $this->assertStringContainsString($this->user->username, $msg->content);
    }

    public function test_dj_bot_handles_queue_command_with_empty_queue(): void
    {
        Event::fake([MessageSent::class]);

        $manager = app(BotManager::class);
        $result  = $manager->dispatch('/sıra', $this->room, $this->user);

        $this->assertNotNull($result);
        $this->assertTrue($result->handled);

        $msg = Message::where('room_id', $this->room->id)
            ->where('sender_id', $this->djBot->user_id)
            ->first();
        $this->assertNotNull($msg);
    }

    public function test_dj_bot_handles_play_command_without_query(): void
    {
        Event::fake([MessageSent::class]);

        $manager = app(BotManager::class);
        $result  = $manager->dispatch('/çal', $this->room, $this->user);

        $this->assertNotNull($result);
        $this->assertTrue($result->handled);

        $msg = Message::where('room_id', $this->room->id)
            ->where('sender_id', $this->djBot->user_id)
            ->first();
        $this->assertNotNull($msg);
        $this->assertStringContainsString('Kullanım', $msg->content);
    }

    public function test_unknown_command_returns_null(): void
    {
        $manager = app(BotManager::class);
        $result  = $manager->dispatch('/bilinmeyenkomut', $this->room, $this->user);

        $this->assertNull($result);
    }

    public function test_bot_message_service_deduplicates_identical_messages(): void
    {
        Event::fake([MessageSent::class]);

        $service = app(BotMessageService::class);
        $service->send($this->room, $this->gameBot, 'Test mesajı');
        $service->send($this->room, $this->gameBot, 'Test mesajı');

        $count = Message::where('room_id', $this->room->id)
            ->where('sender_id', $this->gameBot->user_id)
            ->where('content', 'Test mesajı')
            ->count();

        $this->assertSame(1, $count);
    }

    public function test_bot_user_is_invisible_and_marked_as_bot(): void
    {
        $botUser = User::find($this->gameBot->user_id);

        $this->assertTrue((bool) $botUser->is_bot);
        $this->assertSame('invisible', $botUser->presence_mode);
    }

    public function test_non_slash_message_does_not_trigger_bot(): void
    {
        $manager = app(BotManager::class);

        $result = $manager->dispatch('Normal bir mesaj yazıyorum', $this->room, $this->user);

        $this->assertNull($result);
    }
}
