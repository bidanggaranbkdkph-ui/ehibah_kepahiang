<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NphdDocument extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['nphd_date' => 'date', 'signed_at' => 'datetime', 'amount' => 'integer'];
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(GrantApplication::class, 'application_id');
    }

    public function signer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'signer_user_id');
    }
}
