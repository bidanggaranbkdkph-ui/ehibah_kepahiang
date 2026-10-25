<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Recommendation extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['letter_date' => 'date', 'issued_at' => 'datetime', 'amount_recommended' => 'integer'];
    }

    public function issuer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by');
    }
}
