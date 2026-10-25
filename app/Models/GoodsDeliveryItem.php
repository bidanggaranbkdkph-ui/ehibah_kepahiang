<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;


class GoodsDeliveryItem extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['quantity' => 'float', 'unit_value' => 'integer'];
    }
}
