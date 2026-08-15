<?php

namespace App\Models;

class BodyMetric extends SyncedModel
{
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
