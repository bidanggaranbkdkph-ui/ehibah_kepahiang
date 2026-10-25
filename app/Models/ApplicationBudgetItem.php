<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;


class ApplicationBudgetItem extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['quantity' => 'float', 'unit_price' => 'integer', 'subtotal' => 'integer'];
    }
}
