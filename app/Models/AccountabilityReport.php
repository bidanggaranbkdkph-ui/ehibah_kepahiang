<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AccountabilityReport extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'period_start' => 'date', 'period_end' => 'date', 'due_date' => 'date', 'bpk_audit_date' => 'date',
            'submitted_at' => 'datetime', 'reviewed_at' => 'datetime', 'is_late' => 'boolean',
            'total_realization' => 'integer', 'remaining_funds' => 'integer',
        ];
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(ReportExpense::class, 'report_id');
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(GrantApplication::class, 'application_id');
    }
}
