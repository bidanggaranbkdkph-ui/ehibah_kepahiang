<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;


class DocumentRequirement extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['is_mandatory' => 'boolean', 'requires_validity' => 'boolean', 'is_active' => 'boolean'];
    }
}
