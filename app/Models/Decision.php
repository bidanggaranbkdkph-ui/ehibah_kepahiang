<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Decision extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['decided_at' => 'datetime', 'amount_approved' => 'integer'];
    }

    public function decree(): BelongsTo
    {
        return $this->belongsTo(Decree::class);
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(GrantApplication::class, 'application_id');
    }
}
