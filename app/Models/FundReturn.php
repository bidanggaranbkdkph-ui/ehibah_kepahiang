<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FundReturn extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['returned_on' => 'date', 'notified_at' => 'datetime', 'amount' => 'integer'];
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(GrantApplication::class, 'application_id');
    }
}
