<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;

class Setting extends Model
{
    protected $fillable = [
        'key',
        'value',
    ];

    public static function get(string $key, mixed $default = null): mixed
    {
        try {
            $setting = static::where('key', $key)->first();
        } catch (QueryException) {
            if ($key === 'bank_accounts') {
                return $default ?? [];
            }

            return $default;
        }

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

        if ($key === 'social_follow_rules') {
            return json_decode($value, true) ?? [];
        }

        return $value;
    }
}
