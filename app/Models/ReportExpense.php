<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;


class ReportExpense extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['spent_on' => 'date', 'amount' => 'integer'];
    }
}
