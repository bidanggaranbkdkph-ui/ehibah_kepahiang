<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApplicationDocument extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'is_current' => 'boolean', 'valid_until' => 'date', 'reviewed_at' => 'datetime',
            'replaced_at' => 'datetime', 'size_bytes' => 'integer',
        ];
    }

    public function requirement(): BelongsTo
    {
        return $this->belongsTo(DocumentRequirement::class, 'requirement_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function isExpired(): bool
    {
        return $this->valid_until !== null && $this->valid_until->isPast();
    }
}
