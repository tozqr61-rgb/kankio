<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BaglantiKalContent extends Model
{
    protected $fillable = [
        'content',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'content' => 'array',
        ];
    }

    public function editor()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
