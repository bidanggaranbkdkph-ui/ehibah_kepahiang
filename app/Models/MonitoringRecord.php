<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MonitoringRecord extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'visited_at' => 'date', 'follow_up_due' => 'date',
            'sent_to_bupati_at' => 'datetime', 'sent_to_inspektorat_at' => 'datetime',
        ];
    }

    public function officer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'officer_id');
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(GrantApplication::class, 'application_id');
    }
}
