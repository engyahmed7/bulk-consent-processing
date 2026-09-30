<?php

namespace App\Infrastructure\Settings;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $fillable = [
        'setting_group',
        'setting_key',
        'setting_value',
    ];

    protected $hidden = [
        'setting_value',
    ];

    protected function casts(): array
    {
        return [
            'setting_value' => 'encrypted',
        ];
    }
}
