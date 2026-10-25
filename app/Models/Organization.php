<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Organization extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'legal_doc_date' => 'date'];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(RecipientCategory::class, 'recipient_category_id');
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function isOpd(): bool
    {
        return $this->type === 'OPD';
    }
}
