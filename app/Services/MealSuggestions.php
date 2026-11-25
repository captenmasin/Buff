<?php

namespace App\Services;

use App\Models\MealEntry;
use App\Models\Recipe;
use Carbon\CarbonInterface;

class MealSuggestions
{
    public function forMissingMeals(CarbonInterface $date, array $missingMeals): array
    {
        $suggestions = [];

        foreach ($missingMeals as $mealType) {
            $entry = MealEntry::query()
                ->where('meal_type', $mealType)
                ->where('date', '<', $date->toDateString())
                ->latest('date')
                ->latest()
                ->first();

            if ($entry !== null) {
                $suggestions[$mealType] = $this->entry($entry);
            }
        }

        if (count($suggestions) < count($missingMeals) && ($recipe = Recipe::query()->latest()->first()) !== null) {
            $proteinPerServing = round($recipe->totals()['protein_g'] / max((float) $recipe->servings, 0.1), 2);

            foreach (array_diff($missingMeals, array_keys($suggestions)) as $mealType) {
                $suggestions[$mealType] = [
                    'id' => $recipe->id,
                    'name' => $recipe->name,
                    'kind' => 'recipe',
                    'meal_type' => $mealType,
                    'portion_quantity' => 1,
                    'portion_unit' => null,
                    'protein_g' => $proteinPerServing,
                ];
            }
        }

        return $suggestions;
    }

    public function weeklyObservation(CarbonInterface $date): ?array
    {
        $entries = MealEntry::query()
            ->where('date', '>=', $date->copy()->subDays(6)->toDateString())
            ->where('date', '<', $date->copy()->addDay()->toDateString())
            ->orderByDesc('protein_g')
            ->latest('date')
            ->get(['id', 'date', 'name', 'meal_type', 'portion_quantity', 'portion_unit', 'protein_g']);

        $comparableMeals = $entries->groupBy('meal_type')
            ->filter(fn ($group): bool => $group->pluck('name')->unique()->count() >= 2
                && $group->unique(fn (MealEntry $entry): string => $entry->date->toDateString())->count() >= 2);

        $entry = $entries->first(fn (MealEntry $entry): bool => $comparableMeals->has($entry->meal_type));

        return $entry !== null && (float) $entry->protein_g > 0 ? $this->entry($entry) : null;
    }

    private function entry(MealEntry $entry): array
    {
        return [
            'id' => $entry->id,
            'name' => $entry->name,
            'kind' => 'entry',
            'meal_type' => $entry->meal_type,
            'portion_quantity' => $entry->portion_quantity !== null ? (float) $entry->portion_quantity : null,
            'portion_unit' => $entry->portion_unit,
            'protein_g' => (float) $entry->protein_g,
        ];
    }
}
