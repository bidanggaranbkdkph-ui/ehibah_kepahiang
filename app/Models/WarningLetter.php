<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;


class WarningLetter extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['letter_date' => 'date', 'lpj_due_date' => 'date'];
    }
}
