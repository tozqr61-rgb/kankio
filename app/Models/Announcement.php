<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Announcement extends Model
{
    protected $fillable = ['message', 'type', 'is_active', 'expires_at'];

    protected $casts = [
        'is_active'  => 'boolean',
        'expires_at' => 'datetime',
    ];

    /** Şu an görünür olan aktif duyuruyu döndür (5 dakika cache) */
    public static function active(): ?self
    {
        return Cache::remember('active_announcement', 300, function () {
            return self::where('is_active', true)
                ->where(function ($q) {
                    $q->whereNull('expires_at')
                      ->orWhere('expires_at', '>', now());
                })
                ->latest()
                ->first();
        });
    }

    /** Cache'i temizle (duyuru değiştiğinde çağrılır) */
    public static function clearCache(): void
    {
        Cache::forget('active_announcement');
    }
}
