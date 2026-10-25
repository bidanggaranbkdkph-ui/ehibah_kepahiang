<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Decree extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['decree_date' => 'date'];
    }

    public function fiscalYear(): BelongsTo
    {
        return $this->belongsTo(FiscalYear::class);
    }

    public function decisions(): HasMany
    {
        return $this->hasMany(Decision::class);
    }
}
