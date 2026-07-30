<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Module extends Model
{
    protected $fillable = [
        'name',
        'label',
        'enabled',
        'config',
    ];

    protected $casts = [
        'enabled' => 'boolean',
        'config'  => 'array',
    ];

    public static function isEnabled(string $name): bool
    {
        $module = static::where('name', $name)->first();
        return $module ? $module->enabled : false;
    }

    public static function getConfig(string $name): array
    {
        $module = static::where('name', $name)->first();
        return $module?->config ?? [];
    }
}
