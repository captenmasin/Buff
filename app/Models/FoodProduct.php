<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FoodProduct extends Model
{
    use HasUuids;

    protected $fillable = [
        'barcode',
        'name',
        'brand',
        'image_url',
        'serving_label',
        'serving_quantity',
        'serving_unit',
        'package_label',
        'package_quantity',
        'package_unit',
        'nutrition_unit',
        'calories_per_100',
        'protein_per_100',
        'carbs_per_100',
        'fat_per_100',
    ];

    protected function casts(): array
    {
        return [
            'serving_quantity' => 'decimal:2',
            'package_quantity' => 'decimal:2',
            'calories_per_100' => 'decimal:2',
            'protein_per_100' => 'decimal:2',
            'carbs_per_100' => 'decimal:2',
            'fat_per_100' => 'decimal:2',
        ];
    }

    public function mealEntries(): HasMany
    {
        return $this->hasMany(MealEntry::class);
    }
}
