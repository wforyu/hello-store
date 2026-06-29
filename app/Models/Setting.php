<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $fillable = [
        'key',
        'value',
    ];

    public static function get(string $key, mixed $default = null): mixed
    {
        $setting = static::where('key', $key)->first();

        if (! $setting) {
            if ($key === 'bank_accounts') {
                return $default ?? [];
            }

            return $default;
        }

        $value = $setting->value;

        if ($key === 'bank_accounts') {
            return json_decode($value, true) ?? [];
        }

        return $value;
    }
}
