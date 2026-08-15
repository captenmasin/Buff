<?php

namespace App\Models;

class DailyGoal extends SyncedModel
{
    protected $fillable = [
        'calories',
        'protein_g',
        'carbs_g',
        'fat_g',
        'macro_calories',
        'height_cm',
        'target_weight_kg',
        'target_body_fat_percent',
    ];

    protected function casts(): array
    {
        return [
            'calories' => 'integer',
            'protein_g' => 'decimal:2',
            'carbs_g' => 'decimal:2',
            'fat_g' => 'decimal:2',
            'macro_calories' => 'integer',
            'height_cm' => 'decimal:2',
            'target_weight_kg' => 'decimal:2',
            'target_body_fat_percent' => 'decimal:2',
        ];
    }
}
