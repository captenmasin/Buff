<?php

namespace App\Services;

use App\Models\DailyGoal;
use App\Models\FoodProduct;

class NutritionCalculator
{
    public function macroCalories(float|int|string $protein, float|int|string $carbs, float|int|string $fat): int
    {
        return (int) round(((float) $protein * 4) + ((float) $carbs * 4) + ((float) $fat * 9));
    }

    public function goalMatchesCalories(float|int|string $calories, float|int|string $protein, float|int|string $carbs, float|int|string $fat): bool
    {
        return $this->macroCalories($protein, $carbs, $fat) === (int) round((float) $calories);
    }

    /**
     * @return array{calories: int, protein_g: float, carbs_g: float, fat_g: float, macro_calories: int}
     */
    public function effectiveDailyGoal(DailyGoal $goal, int $burnedCalories, string $eatBack = 'all'): array
    {
        $effectiveCalories = $goal->calories + $this->eatenBackCalories($burnedCalories, $eatBack);
        $macroCalories = max($goal->macro_calories, 1);
        $scale = $effectiveCalories / $macroCalories;

        return [
            'calories' => $effectiveCalories,
            'protein_g' => round((float) $goal->protein_g * $scale, 2),
            'carbs_g' => round((float) $goal->carbs_g * $scale, 2),
            'fat_g' => round((float) $goal->fat_g * $scale, 2),
            'macro_calories' => $effectiveCalories,
        ];
    }

    public function eatenBackCalories(int $burnedCalories, string $eatBack = 'all'): int
    {
        $burned = max($burnedCalories, 0);

        return match ($eatBack) {
            'none' => 0,
            'half' => (int) round($burned / 2),
            default => $burned,
        };
    }

    public function macrosForPortion(FoodProduct $product, float|int|string $quantity): array
    {
        $factor = max((float) $quantity, 0) / 100;

        $protein = round((float) $product->protein_per_100 * $factor, 2);
        $carbs = round((float) $product->carbs_per_100 * $factor, 2);
        $fat = round((float) $product->fat_per_100 * $factor, 2);

        return [
            'calories' => (int) round((float) $product->calories_per_100 * $factor),
            'protein_g' => $protein,
            'carbs_g' => $carbs,
            'fat_g' => $fat,
        ];
    }
}
