<?php

namespace Tests\Feature;

use App\Models\FoodProduct;
use App\Models\MealEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class MealEntryTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_a_custom_meal_and_calculates_calories(): void
    {
        $this->post('/meals/custom', [
            'date' => '2026-05-19',
            'meal_type' => 'lunch',
            'name' => 'Chicken bowl',
            'protein_g' => 45,
            'carbs_g' => 50,
            'fat_g' => 12,
        ])->assertRedirect('/?date=2026-05-19');

        $entry = MealEntry::query()->first();

        $this->assertSame(488, $entry->calories);
        $this->assertSame(MealEntry::SOURCE_CUSTOM, $entry->source_type);
    }

    public function test_it_creates_a_barcode_meal_from_a_product_portion(): void
    {
        $product = FoodProduct::query()->create([
            'barcode' => '1234567890123',
            'name' => 'Yoghurt',
            'nutrition_unit' => 'g',
            'calories_per_100' => 120,
            'protein_per_100' => 8,
            'carbs_per_100' => 12,
            'fat_per_100' => 4,
        ]);

        $this->post('/meals/barcode', [
            'date' => '2026-05-19',
            'meal_type' => 'breakfast',
            'food_product_id' => $product->id,
            'portion_quantity' => 150,
            'portion_unit' => 'g',
        ])->assertRedirect('/?date=2026-05-19');

        $entry = MealEntry::query()->first();

        $this->assertSame(180, $entry->calories);
        $this->assertSame(12.0, (float) $entry->protein_g);
        $this->assertSame(MealEntry::SOURCE_BARCODE, $entry->source_type);
    }

    public function test_it_passes_unique_recent_previous_custom_meals_to_add_page(): void
    {
        $olderDuplicate = MealEntry::query()->create([
            'date' => '2026-05-17',
            'meal_type' => 'breakfast',
            'source_type' => MealEntry::SOURCE_CUSTOM,
            'name' => 'Chicken bowl',
            'calories' => 488,
            'protein_g' => 45,
            'carbs_g' => 50,
            'fat_g' => 12,
        ]);
        $olderDuplicate->forceFill(['created_at' => now()->subDays(2), 'updated_at' => now()->subDays(2)])->save();

        $newerDuplicate = MealEntry::query()->create([
            'date' => '2026-05-18',
            'meal_type' => 'lunch',
            'source_type' => MealEntry::SOURCE_CUSTOM,
            'name' => 'Chicken bowl',
            'calories' => 488,
            'protein_g' => 45,
            'carbs_g' => 50,
            'fat_g' => 12,
        ]);
        $newerDuplicate->forceFill(['created_at' => now()->subDay(), 'updated_at' => now()->subDay()])->save();

        MealEntry::query()->create([
            'date' => '2026-05-19',
            'meal_type' => 'snacks',
            'source_type' => MealEntry::SOURCE_CUSTOM,
            'name' => 'Protein snack',
            'calories' => 200,
            'protein_g' => 30,
            'carbs_g' => 5,
            'fat_g' => 4,
        ]);

        $this->get('/add?mode=custom')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Add')
                ->has('previousCustomMeals', 2)
                ->where('previousCustomMeals.0.name', 'Protein snack')
                ->where('previousCustomMeals.1.name', 'Chicken bowl')
            );
    }
}
