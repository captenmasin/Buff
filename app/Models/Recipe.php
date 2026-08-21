<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;

class Recipe extends SyncedModel
{
    protected $fillable = [
        'name',
        'servings',
        'items',
    ];

    protected function casts(): array
    {
        return [
            'servings' => 'decimal:2',
            'items' => 'array',
        ];
    }

    /**
     * @return array{calories: int, protein_g: float, carbs_g: float, fat_g: float}
     */
    public function totals(): array
    {
        $items = collect($this->items ?? []);

        return [
            'calories' => (int) $items->sum('calories'),
            'protein_g' => round((float) $items->sum('protein_g'), 2),
            'carbs_g' => round((float) $items->sum('carbs_g'), 2),
            'fat_g' => round((float) $items->sum('fat_g'), 2),
        ];
    }

    /**
     * @return array{id: string, name: string, servings: float, calories: int, protein_g: float, carbs_g: float, fat_g: float, items: array<int, mixed>}
     */
    public function toPayload(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'servings' => (float) $this->servings,
            ...$this->totals(),
            'items' => $this->items ?? [],
        ];
    }

    public function mealEntries(): HasMany
    {
        return $this->hasMany(MealEntry::class);
    }
}
