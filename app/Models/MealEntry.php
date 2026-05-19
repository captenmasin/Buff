<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MealEntry extends Model
{
    use HasUuids;

    public const MEAL_TYPES = ['breakfast', 'lunch', 'dinner', 'snacks'];

    public const SOURCE_CUSTOM = 'custom';

    public const SOURCE_BARCODE = 'barcode';

    protected $fillable = [
        'date',
        'meal_type',
        'source_type',
        'food_product_id',
        'name',
        'portion_quantity',
        'portion_unit',
        'calories',
        'protein_g',
        'carbs_g',
        'fat_g',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'portion_quantity' => 'decimal:2',
            'calories' => 'integer',
            'protein_g' => 'decimal:2',
            'carbs_g' => 'decimal:2',
            'fat_g' => 'decimal:2',
        ];
    }

    public function foodProduct(): BelongsTo
    {
        return $this->belongsTo(FoodProduct::class);
    }
}
