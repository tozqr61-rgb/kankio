<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InviteCode extends Model
{
    protected $fillable = ['code', 'used_by', 'is_used', 'expires_at'];

    protected function casts(): array
    {
        return [
            'is_used'    => 'boolean',
            'expires_at' => 'datetime',
        ];
    }

    public function usedByUser()
    {
        return $this->belongsTo(User::class, 'used_by');
    }

    public function isValid(): bool
    {
        if ($this->is_used) return false;
        if ($this->expires_at && $this->expires_at->isPast()) return false;
        return true;
    }
}
