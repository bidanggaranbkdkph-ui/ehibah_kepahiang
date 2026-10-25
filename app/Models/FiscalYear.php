<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;


class FiscalYear extends Model
{
    protected $guarded = ['id'];

    public static function current(): ?self
    {
        return static::where('status', 'BERJALAN')->orderByDesc('year')->first()
            ?? static::orderByDesc('year')->first();
    }
}
