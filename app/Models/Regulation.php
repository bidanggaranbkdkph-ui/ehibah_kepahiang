<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Regulation extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'is_grant_basis' => 'boolean', 'enacted_at' => 'date',
            'effective_at' => 'date', 'revoked_at' => 'date',
        ];
    }

    public function revokedBy(): BelongsTo
    {
        return $this->belongsTo(self::class, 'revoked_by_id');
    }

    public function isActive(): bool
    {
        return $this->status === 'AKTIF';
    }
}
