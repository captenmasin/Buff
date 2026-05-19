<?php

namespace App\Services;

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
