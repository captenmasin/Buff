<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AppPreference extends Model
{
    public const WEIGHT_UNITS = ['kg', 'lb'];

    public const HEIGHT_UNITS = ['cm', 'in'];

    protected $fillable = [
        'weight_unit',
        'height_unit',
    ];

    protected $attributes = [
        'weight_unit' => 'kg',
        'height_unit' => 'cm',
    ];

    public static function current(): self
    {
        return self::query()->firstOrCreate([]);
    }
}
