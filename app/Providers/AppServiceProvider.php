<?php

namespace App\Providers;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
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
