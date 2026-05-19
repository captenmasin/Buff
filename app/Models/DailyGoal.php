<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class DailyGoal extends Model
{
    use HasUuids;

    protected $fillable = [
        'starts_on',
        'calories',
        'protein_g',
        'carbs_g',
        'fat_g',
        'macro_calories',
    ];

    protected function casts(): array
    {
        return [
            'starts_on' => 'date',
            'calories' => 'integer',
            'protein_g' => 'decimal:2',
            'carbs_g' => 'decimal:2',
            'fat_g' => 'decimal:2',
            'macro_calories' => 'integer',
        ];
    }
}
