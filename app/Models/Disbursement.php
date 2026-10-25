<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Disbursement extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'amount' => 'integer', 'percentage' => 'float', 'paid_at' => 'datetime',
            'spp_date' => 'date', 'sp2d_date' => 'date',
        ];
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(GrantApplication::class, 'application_id');
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }
}
