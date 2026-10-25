<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BudgetCeiling extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['ceiling' => 'integer'];
    }

    public function opd(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'opd_id');
    }

    public function fiscalYear(): BelongsTo
    {
        return $this->belongsTo(FiscalYear::class);
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(BudgetAllocation::class, 'ceiling_id');
    }

    /** Total terpakai = alokasi RESERVED + CONFIRMED (RELEASED dikembalikan ke pagu). */
    public function used(): int
    {
        return (int) $this->allocations()->whereIn('status', ['RESERVED', 'CONFIRMED'])->sum('amount');
    }

    public function remaining(): int
    {
        return $this->ceiling - $this->used();
    }
}
