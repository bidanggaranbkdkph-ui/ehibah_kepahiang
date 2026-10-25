<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GoodsDelivery extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'procurement_date' => 'date', 'bast_date' => 'date',
            'delivered_at' => 'datetime', 'asset_recorded_at' => 'datetime',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(GoodsDeliveryItem::class);
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(GrantApplication::class, 'application_id');
    }
}
