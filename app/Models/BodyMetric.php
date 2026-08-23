<?php

namespace App\Models;

class BodyMetric extends SyncedModel
{
    protected $fillable = [
        'date',
        'weight_kg',
        'body_fat_percent',
        'chest_cm',
        'waist_cm',
        'hips_cm',
        'upper_arm_cm',
        'thigh_cm',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'weight_kg' => 'decimal:2',
            'body_fat_percent' => 'decimal:2',
            'chest_cm' => 'decimal:2',
            'waist_cm' => 'decimal:2',
            'hips_cm' => 'decimal:2',
            'upper_arm_cm' => 'decimal:2',
            'thigh_cm' => 'decimal:2',
        ];
    }
}
