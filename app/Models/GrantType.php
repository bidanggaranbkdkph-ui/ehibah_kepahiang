<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;


class GrantType extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function isMoney(): bool
    {
        return $this->form === 'UANG';
    }
}
