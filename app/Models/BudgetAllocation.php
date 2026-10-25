<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BudgetAllocation extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['amount' => 'integer', 'synced_at' => 'datetime', 'confirmed_at' => 'datetime', 'released_at' => 'datetime'];
    }

    public function ceiling(): BelongsTo
    {
        return $this->belongsTo(BudgetCeiling::class, 'ceiling_id');
    }
}
