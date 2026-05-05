<?php

namespace App\Services\Bots\Bots;

use App\Models\Bot;
use App\Models\Room;
use App\Services\Bots\BotCommandContext;
use App\Services\Bots\BotMessageService;
use App\Services\Bots\BotResponse;
use App\Services\Bots\Contracts\BotInterface;
use App\Services\Games\IsimSehirGameService;

class GameBot implements BotInterface
{
    public const BOT_KEY = 'game_bot';

    private const COMMANDS = ['oyun', 'game', 'isim', 'skor'];

    public function __construct(
        private IsimSehirGameService $gameService,
        private BotMessageService $messenger,
    ) {}

    public function botKey(): string
    {
        return self::BOT_KEY;
    }

    public function supportsRoom(Room $room): bool
    {
        return true;
    }

    public function supportsCommand(string $command): bool
    {
        return in_array(mb_strtolower($command), self::COMMANDS, true);
    }

    public function handleCommand(BotCommandContext $ctx): BotResponse
    {
        $bot = Bot::findByKey(self::BOT_KEY);

        if (! $bot) {
            return BotResponse::ignored();
        }

        $sub = mb_strtolower($ctx->arg(0, ''));

        return match ($sub) {
            'başlat', 'baslat', 'start' => $this->startGame($ctx, $bot),
            'durum', 'status'           => $this->gameStatus($ctx, $bot),
            'bitir', 'finish'           => $this->finishGame($ctx, $bot),
            'yardım', 'yardim', 'help'  => $this->help($ctx, $bot),
            default                     => $this->help($ctx, $bot),
        };
    }

    public function handleEvent(string $event, array $payload): void
    {
        $bot = Bot::findByKey(self::BOT_KEY);
        if (! $bot) {
            return;
        }

        match ($event) {
            'game.round_started'  => $this->onRoundStarted($bot, $payload),
            'game.round_finished' => $this->onRoundFinished($bot, $payload),
            'game.finished'       => $this->onGameFinished($bot, $payload),
            default               => null,
        };
    }

    private function startGame(BotCommandContext $ctx, Bot $bot): BotResponse
    {
        $existing = $this->gameService->current($ctx->room);

        if ($existing) {
            $url  = route('rooms.games.show', [$ctx->room->id, $existing->id]);
            $embedUrl = $url . '?embedded=1';
            $this->messenger->send(
                $ctx->room,
                $bot,
                "🎮 Aktif oyun devam ediyor! Katıl: {$url}",
                ['action' => 'game:open', 'game_url' => $embedUrl]
            );

            return BotResponse::ok();
        }

        try {
            $session  = $this->gameService->start($ctx->room, $ctx->sender);
            $url      = route('rooms.games.show', [$ctx->room->id, $session->id]);
            $embedUrl = $url . '?embedded=1';
            $this->messenger->send(
                $ctx->room,
                $bot,
                "🎮 **{$ctx->sender->username}** İsim-Şehir başlattı! Katılmak için tıkla: {$url}",
                ['action' => 'game:open', 'game_url' => $embedUrl]
            );

            return BotResponse::ok('Oyun başlatıldı.', ['session_id' => $session->id]);
        } catch (\Throwable $e) {
            $this->messenger->send($ctx->room, $bot, '❌ Oyun başlatılamadı: ' . $e->getMessage());

            return BotResponse::error($e->getMessage());
        }
    }

    private function gameStatus(BotCommandContext $ctx, Bot $bot): BotResponse
    {
        $session = $this->gameService->current($ctx->room);

        if (! $session) {
            $this->messenger->send($ctx->room, $bot, '🎮 Şu an aktif bir oyun yok. Başlatmak için: `/oyun başlat`');

            return BotResponse::ok();
        }

        $state       = $this->gameService->state($session, $ctx->sender);
        $participants = count($state['participants'] ?? []);
        $statusLabel = match ($session->status) {
            'waiting'     => 'Oyuncu bekleniyor',
            'in_progress' => 'Oyun devam ediyor',
            'finished'    => 'Bitti',
            default       => $session->status,
        };

        $url      = route('rooms.games.show', [$ctx->room->id, $session->id]);
        $embedUrl = $url . '?embedded=1';
        $this->messenger->send(
            $ctx->room,
            $bot,
            "🎮 Oyun: **{$statusLabel}** · {$participants} oyuncu",
            ['action' => 'game:open', 'game_url' => $embedUrl]
        );

        return BotResponse::ok();
    }

    private function finishGame(BotCommandContext $ctx, Bot $bot): BotResponse
    {
        $session = $this->gameService->current($ctx->room);

        if (! $session) {
            $this->messenger->send($ctx->room, $bot, '🎮 Bitirmek için aktif bir oyun yok.');

            return BotResponse::ok();
        }

        if (! $ctx->sender->isAdmin() && $session->created_by !== $ctx->sender->id) {
            $this->messenger->send($ctx->room, $bot, '❌ Yalnızca oyunu başlatan veya yönetici oyunu bitirebilir.');

            return BotResponse::error('Yetersiz yetki.');
        }

        try {
            $this->gameService->finish($session, $ctx->sender);
            $this->messenger->send($ctx->room, $bot, '🏁 Oyun bitirildi! Sonuçlar kaydedildi.');

            return BotResponse::ok('Oyun bitirildi.');
        } catch (\Throwable $e) {
            $this->messenger->send($ctx->room, $bot, '❌ Oyun bitirilemedi: ' . $e->getMessage());

            return BotResponse::error($e->getMessage());
        }
    }

    private function help(BotCommandContext $ctx, Bot $bot): BotResponse
    {
        $this->messenger->send($ctx->room, $bot, implode("\n", [
            '🎮 **Oyun Botu Komutları:**',
            '`/oyun başlat` — İsim-Şehir oyunu başlat',
            '`/oyun durum` — Aktif oyun bilgisi',
            '`/oyun bitir` — Oyunu bitir (sadece başlatan/admin)',
            '`/skor` — Aktif oyun durum ve skor özeti',
        ]));

        return BotResponse::ok();
    }

    private function onRoundStarted(Bot $bot, array $payload): void
    {
        $room = \App\Models\Room::find($payload['room_id'] ?? null);
        if (! $room) {
            return;
        }

        $letter    = $payload['letter'] ?? '?';
        $roundNo   = $payload['round_no'] ?? 1;
        $seconds   = $payload['round_time_seconds'] ?? 420;
        $minutes   = (int) ($seconds / 60);

        $this->messenger->send(
            $room,
            $bot,
            "🎮 **Tur {$roundNo} başladı!** Harf: **{$letter}** · Süre: {$minutes} dakika"
        );
    }

    private function onRoundFinished(Bot $bot, array $payload): void
    {
        $room = \App\Models\Room::find($payload['room_id'] ?? null);
        if (! $room) {
            return;
        }

        $roundNo = $payload['round_no'] ?? 1;
        $this->messenger->send($room, $bot, "✅ **Tur {$roundNo} bitti!** Puanlar hesaplanıyor...");
    }

    private function onGameFinished(Bot $bot, array $payload): void
    {
        $room = \App\Models\Room::find($payload['room_id'] ?? null);
        if (! $room) {
            return;
        }

        $winner = $payload['winner_username'] ?? null;
        $msg    = $winner
            ? "🏆 **Oyun bitti!** Kazanan: **{$winner}** 🎉"
            : '🏆 **Oyun bitti!** Sonuçlar kayıt edildi.';

        $this->messenger->send($room, $bot, $msg);
    }
}
