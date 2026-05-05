<?php

namespace Tests\Feature;

use App\Models\GameParticipant;
use App\Models\GameRound;
use App\Models\GameSession;
use App\Models\GameSubmission;
use App\Models\Room;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IsimSehirGameTest extends TestCase
{
    use RefreshDatabase;

    public function test_room_member_can_start_join_and_view_game_state(): void
    {
        $user = User::factory()->create();
        $room = Room::create(['name' => 'Oyun', 'type' => 'global', 'created_by' => $user->id]);

        $response = $this->actingAs($user)
            ->postJson(route('rooms.games.start', $room))
            ->assertCreated()
            ->assertJsonPath('ok', true);

        $sessionId = $response->json('game_session_id');

        $this->assertDatabaseHas('game_sessions', [
            'id' => $sessionId,
            'room_id' => $room->id,
            'status' => 'waiting',
        ]);
        $this->assertDatabaseHas('game_participants', [
            'game_session_id' => $sessionId,
            'user_id' => $user->id,
            'is_active' => true,
        ]);

        $this->actingAs($user)
            ->getJson(route('rooms.games.state', [$room, $sessionId]))
            ->assertOk()
            ->assertJsonPath('session.id', $sessionId)
            ->assertJsonPath('session.round_time_seconds', 420)
            ->assertJsonPath('categories.0', 'isim')
            ->assertJsonPath('categories.4', 'bitki');

        $this->actingAs($user)
            ->getJson(route('rooms.games.current', $room))
            ->assertOk()
            ->assertJsonPath('active', true)
            ->assertJsonPath('game_session_id', $sessionId);

        $this->actingAs($user)
            ->get(route('rooms.games.show', [$room, $sessionId]))
            ->assertOk()
            ->assertSee('İsim-Şehir', false);

        $this->actingAs($user)
            ->get(route('rooms.games.show', [$room, $sessionId, 'embedded' => 1]))
            ->assertOk()
            ->assertSee('game:close', false)
            ->assertSee('Sadece', false)
            ->assertSee('Cevapları Kilitle', false);
    }

    public function test_private_room_outsider_cannot_access_game(): void
    {
        $owner = User::factory()->create();
        $outsider = User::factory()->create();
        $room = Room::create(['name' => 'Private', 'type' => 'private', 'created_by' => $owner->id]);
        $session = GameSession::create([
            'room_id' => $room->id,
            'created_by' => $owner->id,
            'game_type' => 'isim_sehir',
            'status' => 'waiting',
            'settings' => ['categories' => ['isim', 'şehir']],
        ]);

        $this->actingAs($outsider)
            ->getJson(route('rooms.games.state', [$room, $session]))
            ->assertForbidden();
    }

    public function test_creator_can_begin_round_and_participants_can_submit_answers(): void
    {
        $creator = User::factory()->create();
        $player = User::factory()->create();
        $room = Room::create(['name' => 'Global', 'type' => 'global', 'created_by' => $creator->id]);

        $session = GameSession::create([
            'room_id' => $room->id,
            'created_by' => $creator->id,
            'game_type' => 'isim_sehir',
            'status' => 'waiting',
            'round_time_seconds' => 60,
            'settings' => ['categories' => ['isim', 'şehir', 'hayvan', 'eşya'], 'round_time_seconds' => 60],
        ]);
        GameParticipant::create(['game_session_id' => $session->id, 'user_id' => $creator->id, 'joined_at' => now(), 'is_active' => true]);
        GameParticipant::create(['game_session_id' => $session->id, 'user_id' => $player->id, 'joined_at' => now(), 'is_active' => true]);

        $this->actingAs($creator)
            ->postJson(route('rooms.games.begin_round', [$room, $session]))
            ->assertOk()
            ->assertJsonPath('state.round.status', 'collecting');

        $round = GameRound::where('game_session_id', $session->id)->firstOrFail();
        $letter = $round->letter;

        $this->actingAs($player)
            ->postJson(route('rooms.games.rounds.submit', [$room, $session, $round]), [
                'answers' => [
                    'isim' => $letter.'ert',
                    'şehir' => $letter.'ersin',
                    'hayvan' => $letter.'artı',
                    'eşya' => $letter.'asa',
                ],
            ])
            ->assertOk();

        $this->assertDatabaseHas('game_submissions', [
            'game_round_id' => $round->id,
            'user_id' => $player->id,
            'is_locked' => true,
        ]);
    }

    public function test_round_finalizes_when_every_active_participant_submits_and_scores_duplicates(): void
    {
        $creator = User::factory()->create();
        $player = User::factory()->create();
        $room = Room::create(['name' => 'Global', 'type' => 'global', 'created_by' => $creator->id]);
        $session = GameSession::create([
            'room_id' => $room->id,
            'created_by' => $creator->id,
            'game_type' => 'isim_sehir',
            'status' => 'in_progress',
            'current_round_no' => 1,
            'round_time_seconds' => 60,
            'settings' => ['categories' => ['isim']],
        ]);
        GameParticipant::create(['game_session_id' => $session->id, 'user_id' => $creator->id, 'joined_at' => now(), 'is_active' => true]);
        GameParticipant::create(['game_session_id' => $session->id, 'user_id' => $player->id, 'joined_at' => now(), 'is_active' => true]);
        $round = GameRound::create([
            'game_session_id' => $session->id,
            'round_no' => 1,
            'letter' => 'M',
            'status' => 'collecting',
            'started_at' => now(),
            'submission_deadline' => now()->addMinute(),
        ]);

        $this->actingAs($creator)
            ->postJson(route('rooms.games.rounds.submit', [$room, $session, $round]), ['answers' => ['isim' => 'Mert']])
            ->assertOk();

        $this->actingAs($player)
            ->postJson(route('rooms.games.rounds.submit', [$room, $session, $round]), ['answers' => ['isim' => 'Mert']])
            ->assertOk();

        $this->assertDatabaseHas('game_rounds', [
            'id' => $round->id,
            'status' => 'closed',
        ]);
        $this->assertEquals(2, GameSubmission::where('game_round_id', $round->id)->count());
        $this->assertEquals(5, GameParticipant::where('game_session_id', $session->id)->where('user_id', $creator->id)->value('total_score'));
        $this->assertEquals(5, GameParticipant::where('game_session_id', $session->id)->where('user_id', $player->id)->value('total_score'));
    }

    public function test_state_does_not_reset_waiting_session_settings_and_ready_can_toggle(): void
    {
        $user = User::factory()->create();
        $room = Room::create(['name' => 'Global', 'type' => 'global', 'created_by' => $user->id]);
        $session = GameSession::create([
            'room_id' => $room->id,
            'created_by' => $user->id,
            'game_type' => 'isim_sehir',
            'status' => 'waiting',
            'round_time_seconds' => 300,
            'settings' => ['categories' => ['isim', 'şehir'], 'round_time_seconds' => 300],
        ]);
        GameParticipant::create(['game_session_id' => $session->id, 'user_id' => $user->id, 'joined_at' => now(), 'is_active' => true]);

        $this->actingAs($user)
            ->getJson(route('rooms.games.state', [$room, $session]))
            ->assertOk()
            ->assertJsonPath('session.round_time_seconds', 300)
            ->assertJsonPath('categories.1', 'şehir');

        $this->actingAs($user)
            ->postJson(route('rooms.games.ready', [$room, $session]), ['is_ready' => true])
            ->assertOk()
            ->assertJsonPath('state.participants.0.is_ready', true);

        $this->actingAs($user)
            ->postJson(route('rooms.games.ready', [$room, $session]), ['is_ready' => false])
            ->assertOk()
            ->assertJsonPath('state.participants.0.is_ready', false);
    }

    public function test_manager_can_update_game_settings_between_rounds(): void
    {
        $creator = User::factory()->create();
        $room = Room::create(['name' => 'Global', 'type' => 'global', 'created_by' => $creator->id]);
        $session = GameSession::create([
            'room_id' => $room->id,
            'created_by' => $creator->id,
            'game_type' => 'isim_sehir',
            'status' => 'waiting',
            'round_time_seconds' => 420,
            'settings' => ['categories' => ['isim', 'şehir', 'hayvan', 'eşya', 'bitki'], 'round_time_seconds' => 420],
        ]);
        GameParticipant::create(['game_session_id' => $session->id, 'user_id' => $creator->id, 'joined_at' => now(), 'is_active' => true]);

        $this->actingAs($creator)
            ->postJson(route('rooms.games.settings', [$room, $session]), [
                'round_time_seconds' => 300,
                'categories' => ['isim', 'şehir', 'bitki'],
            ])
            ->assertOk()
            ->assertJsonPath('state.session.round_time_seconds', 300)
            ->assertJsonPath('state.categories.2', 'bitki');

        $this->assertDatabaseHas('game_sessions', [
            'id' => $session->id,
            'round_time_seconds' => 300,
        ]);
    }

    public function test_game_settings_cannot_change_while_round_is_collecting(): void
    {
        $creator = User::factory()->create();
        $room = Room::create(['name' => 'Global', 'type' => 'global', 'created_by' => $creator->id]);
        $session = GameSession::create([
            'room_id' => $room->id,
            'created_by' => $creator->id,
            'game_type' => 'isim_sehir',
            'status' => 'in_progress',
            'current_round_no' => 1,
            'round_time_seconds' => 420,
            'settings' => ['categories' => ['isim', 'şehir'], 'round_time_seconds' => 420],
        ]);
        GameParticipant::create(['game_session_id' => $session->id, 'user_id' => $creator->id, 'joined_at' => now(), 'is_active' => true]);
        GameRound::create([
            'game_session_id' => $session->id,
            'round_no' => 1,
            'letter' => 'M',
            'status' => 'collecting',
            'started_at' => now(),
            'submission_deadline' => now()->addMinutes(7),
        ]);

        $this->actingAs($creator)
            ->postJson(route('rooms.games.settings', [$room, $session]), [
                'round_time_seconds' => 300,
                'categories' => ['isim', 'şehir', 'bitki'],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('game');
    }

    public function test_finish_closes_active_round_and_marks_session_finished(): void
    {
        $creator = User::factory()->create();
        $room = Room::create(['name' => 'Global', 'type' => 'global', 'created_by' => $creator->id]);
        $session = GameSession::create([
            'room_id' => $room->id,
            'created_by' => $creator->id,
            'game_type' => 'isim_sehir',
            'status' => 'in_progress',
            'current_round_no' => 1,
            'round_time_seconds' => 420,
            'settings' => ['categories' => ['isim'], 'round_time_seconds' => 420],
        ]);
        GameParticipant::create(['game_session_id' => $session->id, 'user_id' => $creator->id, 'joined_at' => now(), 'is_active' => true]);
        $round = GameRound::create([
            'game_session_id' => $session->id,
            'round_no' => 1,
            'letter' => 'Z',
            'status' => 'collecting',
            'started_at' => now(),
            'submission_deadline' => now()->addMinutes(7),
        ]);

        $this->actingAs($creator)
            ->postJson(route('rooms.games.rounds.draft', [$room, $session, $round]), ['answers' => ['isim' => 'Zeynep']])
            ->assertOk();

        $this->actingAs($creator)
            ->postJson(route('rooms.games.finish', [$room, $session]))
            ->assertOk()
            ->assertJsonPath('state.session.status', 'finished')
            ->assertJsonPath('state.round.status', 'closed')
            ->assertJsonPath('meta.finalized_collecting_rounds', 1)
            ->assertJsonPath('meta.scored_only_locked', true);

        $this->assertDatabaseHas('game_sessions', ['id' => $session->id, 'status' => 'finished']);
        $this->assertDatabaseHas('game_rounds', ['id' => $round->id, 'status' => 'closed']);
        $this->assertEquals(0, GameParticipant::where('game_session_id', $session->id)->where('user_id', $creator->id)->value('total_score'));

        $this->actingAs($creator)
            ->postJson(route('rooms.games.rounds.draft', [$room, $session, $round]), ['answers' => ['isim' => 'Zerrin']])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('round');
    }

    public function test_finish_scores_only_locked_answers(): void
    {
        $creator = User::factory()->create();
        $player = User::factory()->create();
        $room = Room::create(['name' => 'Global', 'type' => 'global', 'created_by' => $creator->id]);
        $session = GameSession::create([
            'room_id' => $room->id,
            'created_by' => $creator->id,
            'game_type' => 'isim_sehir',
            'status' => 'in_progress',
            'current_round_no' => 1,
            'round_time_seconds' => 420,
            'settings' => ['categories' => ['isim'], 'round_time_seconds' => 420],
        ]);
        GameParticipant::create(['game_session_id' => $session->id, 'user_id' => $creator->id, 'joined_at' => now(), 'is_active' => true]);
        GameParticipant::create(['game_session_id' => $session->id, 'user_id' => $player->id, 'joined_at' => now(), 'is_active' => true]);
        $round = GameRound::create([
            'game_session_id' => $session->id,
            'round_no' => 1,
            'letter' => 'Z',
            'status' => 'collecting',
            'started_at' => now(),
            'submission_deadline' => now()->addMinutes(7),
        ]);

        $this->actingAs($creator)
            ->postJson(route('rooms.games.rounds.submit', [$room, $session, $round]), ['answers' => ['isim' => 'Zeynep']])
            ->assertOk();

        $this->actingAs($player)
            ->postJson(route('rooms.games.rounds.draft', [$room, $session, $round]), ['answers' => ['isim' => 'Zerrin']])
            ->assertOk();

        $this->actingAs($creator)
            ->postJson(route('rooms.games.finish', [$room, $session]))
            ->assertOk();

        $this->assertEquals(10, GameParticipant::where('game_session_id', $session->id)->where('user_id', $creator->id)->value('total_score'));
        $this->assertEquals(0, GameParticipant::where('game_session_id', $session->id)->where('user_id', $player->id)->value('total_score'));
    }

    public function test_last_active_player_leave_cancels_session(): void
    {
        $creator = User::factory()->create();
        $room = Room::create(['name' => 'Global', 'type' => 'global', 'created_by' => $creator->id]);
        $session = GameSession::create([
            'room_id' => $room->id,
            'created_by' => $creator->id,
            'game_type' => 'isim_sehir',
            'status' => 'in_progress',
            'current_round_no' => 1,
            'round_time_seconds' => 420,
            'settings' => ['categories' => ['isim'], 'round_time_seconds' => 420],
        ]);
        GameParticipant::create(['game_session_id' => $session->id, 'user_id' => $creator->id, 'joined_at' => now(), 'is_active' => true]);
        $round = GameRound::create([
            'game_session_id' => $session->id,
            'round_no' => 1,
            'letter' => 'Z',
            'status' => 'collecting',
            'started_at' => now(),
            'submission_deadline' => now()->addMinutes(7),
        ]);

        $this->actingAs($creator)
            ->postJson(route('rooms.games.leave', [$room, $session]))
            ->assertOk()
            ->assertJsonPath('state.session.status', 'cancelled');

        $this->assertDatabaseHas('game_sessions', ['id' => $session->id, 'status' => 'cancelled']);
        $this->assertDatabaseHas('game_rounds', ['id' => $round->id, 'status' => 'closed']);
    }

    public function test_game_history_endpoint_returns_paginated_round_details(): void
    {
        $creator = User::factory()->create();
        $room = Room::create(['name' => 'Global', 'type' => 'global', 'created_by' => $creator->id]);
        $session = GameSession::create([
            'room_id' => $room->id,
            'created_by' => $creator->id,
            'game_type' => 'isim_sehir',
            'status' => 'waiting',
            'current_round_no' => 2,
            'round_time_seconds' => 420,
            'settings' => ['categories' => ['isim'], 'round_time_seconds' => 420],
        ]);
        GameParticipant::create(['game_session_id' => $session->id, 'user_id' => $creator->id, 'joined_at' => now(), 'is_active' => true]);

        $round = GameRound::create([
            'game_session_id' => $session->id,
            'round_no' => 1,
            'letter' => 'M',
            'status' => 'closed',
            'started_at' => now()->subMinutes(10),
            'submission_deadline' => now()->subMinutes(3),
            'ended_at' => now()->subMinutes(2),
            'results_published_at' => now()->subMinutes(2),
        ]);
        GameSubmission::create([
            'game_round_id' => $round->id,
            'user_id' => $creator->id,
            'answers' => ['isim' => 'Mert'],
            'submitted_at' => now()->subMinutes(4),
            'is_locked' => true,
            'score_total' => 10,
            'score_breakdown' => ['isim' => ['score' => 10]],
        ]);

        $this->actingAs($creator)
            ->getJson(route('rooms.games.history', [$room, $session]))
            ->assertOk()
            ->assertJsonPath('rounds.0.id', $round->id)
            ->assertJsonPath('rounds.0.submissions.0.answers.isim', 'Mert')
            ->assertJsonPath('pagination.has_more', false);
    }

    public function test_city_category_uses_city_allowlist(): void
    {
        $creator = User::factory()->create();
        $player = User::factory()->create();
        $room = Room::create(['name' => 'Global', 'type' => 'global', 'created_by' => $creator->id]);
        $session = GameSession::create([
            'room_id' => $room->id,
            'created_by' => $creator->id,
            'game_type' => 'isim_sehir',
            'status' => 'in_progress',
            'current_round_no' => 1,
            'round_time_seconds' => 420,
            'settings' => ['categories' => ['şehir'], 'round_time_seconds' => 420],
        ]);
        GameParticipant::create(['game_session_id' => $session->id, 'user_id' => $creator->id, 'joined_at' => now(), 'is_active' => true]);
        GameParticipant::create(['game_session_id' => $session->id, 'user_id' => $player->id, 'joined_at' => now(), 'is_active' => true]);
        $round = GameRound::create([
            'game_session_id' => $session->id,
            'round_no' => 1,
            'letter' => 'M',
            'status' => 'collecting',
            'started_at' => now(),
            'submission_deadline' => now()->addMinutes(7),
        ]);

        $this->actingAs($creator)
            ->postJson(route('rooms.games.rounds.submit', [$room, $session, $round]), ['answers' => ['şehir' => 'Mersin']])
            ->assertOk();
        $this->actingAs($player)
            ->postJson(route('rooms.games.rounds.submit', [$room, $session, $round]), ['answers' => ['şehir' => 'Masa']])
            ->assertOk();

        $this->assertEquals(10, GameParticipant::where('game_session_id', $session->id)->where('user_id', $creator->id)->value('total_score'));
        $this->assertEquals(0, GameParticipant::where('game_session_id', $session->id)->where('user_id', $player->id)->value('total_score'));
    }
}
