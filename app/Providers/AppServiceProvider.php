<?php

namespace App\Providers;

use App\Events\MessageSent;
use App\Listeners\Bots\DispatchBotMessage;
use App\Listeners\Notifications\SendWebPushForMessage;
use App\Services\Bots\BotCommandParser;
use App\Services\Bots\BotManager;
use App\Services\Bots\BotMessageService;
use App\Services\Bots\Bots\DjBot;
use App\Services\Bots\Bots\GameBot;
use App\Services\Games\IsimSehirGameService;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(BotManager::class, function ($app) {
            $manager = new BotManager($app->make(BotCommandParser::class));
            $manager->register($app->make(GameBot::class));
            $manager->register($app->make(DjBot::class));

            return $manager;
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Event::listen(MessageSent::class, DispatchBotMessage::class);
        Event::listen(MessageSent::class, SendWebPushForMessage::class);

        if (! app()->environment('production')) {
            return;
        }

        $required = [
            'APP_KEY' => config('app.key'),
            'REVERB_APP_KEY' => config('broadcasting.connections.reverb.key'),
            'REVERB_APP_SECRET' => config('reverb.apps.apps.0.secret'),
            'BAGLANTIKAL_ACCESS_PIN' => config('services.baglantikal.access_pin'),
            'BAGLANTIKAL_LETTER_PIN' => config('services.baglantikal.letter_pin'),
        ];

        $missing = array_keys(array_filter($required, fn ($value) => blank($value) || str_contains((string) $value, 'change-me')));

        if ($missing !== []) {
            Log::critical('production.required_secrets_missing', ['keys' => $missing]);
            throw new \RuntimeException('Production secrets missing: '.implode(', ', $missing));
        }
    }
}
