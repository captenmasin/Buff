<?php

use App\Models\DailyGoal;
use App\Models\FoodProduct;
use App\Services\NutritionCalculator;

it('calculates macro calories', function (): void {
    $calculator = new NutritionCalculator;

    expect($calculator->macroCalories(170, 195, 60))->toBe(2000)
        ->and($calculator->goalMatchesCalories(2000, 170, 195, 60))->toBeTrue()
        ->and($calculator->goalMatchesCalories(1900, 170, 195, 60))->toBeFalse();
});

it('matches rounded odd-calorie macro totals', function (): void {
    $calculator = new NutritionCalculator;

    expect($calculator->goalMatchesCalories(1999, 174.91, 224.89, 44.42))->toBeTrue();
});

it('calculates product portions from per 100 values', function (): void {
    $calculator = new NutritionCalculator;
    $product = new FoodProduct([
        'calories_per_100' => 250,
        'protein_per_100' => 10,
        'carbs_per_100' => 30,
        'fat_per_100' => 5,
    ]);

    expect($calculator->macrosForPortion($product, 50))->toBe([
        'calories' => 125,
        'protein_g' => 5.0,
        'carbs_g' => 15.0,
        'fat_g' => 2.5,
    ]);
});

it('scales daily macro goals from burned calories', function (): void {
    $calculator = new NutritionCalculator;
    $goal = new DailyGoal([
        'calories' => 2000,
        'protein_g' => 170,
        'carbs_g' => 195,
        'fat_g' => 60,
        'macro_calories' => 2000,
    ]);

    expect($calculator->effectiveDailyGoal($goal, 300))->toBe([
        'calories' => 2300,
        'protein_g' => 195.5,
        'carbs_g' => 224.25,
        'fat_g' => 69.0,
        'macro_calories' => 2300,
    ]);
});
