<?php

namespace Tests\Unit;

use App\Models\FoodProduct;
use App\Services\NutritionCalculator;
use PHPUnit\Framework\TestCase;

class NutritionCalculatorTest extends TestCase
{
    public function test_it_calculates_macro_calories(): void
    {
        $calculator = new NutritionCalculator;

        $this->assertSame(2000, $calculator->macroCalories(170, 195, 60));
        $this->assertTrue($calculator->goalMatchesCalories(2000, 170, 195, 60));
        $this->assertFalse($calculator->goalMatchesCalories(1900, 170, 195, 60));
    }

    public function test_it_calculates_product_portions_from_per_100_values(): void
    {
        $calculator = new NutritionCalculator;
        $product = new FoodProduct([
            'calories_per_100' => 250,
            'protein_per_100' => 10,
            'carbs_per_100' => 30,
            'fat_per_100' => 5,
        ]);

        $this->assertSame([
            'calories' => 125,
            'protein_g' => 5.0,
            'carbs_g' => 15.0,
            'fat_g' => 2.5,
        ], $calculator->macrosForPortion($product, 50));
    }
}
