<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class BodyMetric extends Model
{
    use HasUuids;

    protected $fillable = [
        'date',
        'weight_kg',
        'body_fat_percent',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'weight_kg' => 'decimal:2',
            'body_fat_percent' => 'decimal:2',
        ];
    }
}
