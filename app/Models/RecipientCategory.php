<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;


class RecipientCategory extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['is_parpol' => 'boolean', 'requires_legal_entity' => 'boolean', 'is_active' => 'boolean'];
    }
}
