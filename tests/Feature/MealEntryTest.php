<?php

namespace Tests\Feature;

use App\Models\FoodProduct;
use App\Models\MealEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
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
            'portion_quantity' => 350,
            'portion_unit' => 'g',
            'protein_g' => 45,
            'carbs_g' => 50,
            'fat_g' => 12,
        ])->assertRedirect('/?date=2026-05-19');

        $entry = MealEntry::query()->first();

        $this->assertSame(488, $entry->calories);
        $this->assertSame(350.0, (float) $entry->portion_quantity);
        $this->assertSame('g', $entry->portion_unit);
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

    public function test_it_creates_a_barcode_meal_from_a_liquid_product_portion(): void
    {
        $product = FoodProduct::query()->create([
            'barcode' => '5000181036312',
            'name' => 'Milk',
            'nutrition_unit' => 'ml',
            'calories_per_100' => 41.5,
            'protein_per_100' => 4.55,
            'carbs_per_100' => 4.9,
            'fat_per_100' => 0.4,
        ]);

        $this->post('/meals/barcode', [
            'date' => '2026-05-20',
            'meal_type' => 'breakfast',
            'food_product_id' => $product->id,
            'portion_quantity' => 200,
            'portion_unit' => 'ml',
        ])->assertRedirect('/?date=2026-05-20');

        $entry = MealEntry::query()->first();

        $this->assertSame(83, $entry->calories);
        $this->assertSame(9.1, (float) $entry->protein_g);
        $this->assertSame('ml', $entry->portion_unit);
    }

    public function test_it_passes_unique_recent_previous_custom_meals_to_add_page(): void
    {
        $olderDuplicate = MealEntry::query()->create([
            'date' => '2026-05-17',
            'meal_type' => 'breakfast',
            'source_type' => MealEntry::SOURCE_CUSTOM,
            'name' => 'Chicken bowl',
            'portion_quantity' => 350,
            'portion_unit' => 'g',
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
            'portion_quantity' => 350,
            'portion_unit' => 'g',
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
            'portion_quantity' => 90,
            'portion_unit' => 'g',
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
                ->where('previousCustomMeals.0.portion_quantity', 90)
                ->where('previousCustomMeals.0.portion_unit', 'g')
                ->where('previousCustomMeals.1.name', 'Chicken bowl')
                ->where('previousCustomMeals.1.portion_quantity', 350)
            );
    }

    public function test_legacy_add_food_modes_open_the_food_page(): void
    {
        foreach (['barcode', 'search'] as $mode) {
            $this->get("/add?mode={$mode}")
                ->assertOk()
                ->assertInertia(fn (Assert $page) => $page
                    ->component('Add')
                    ->where('mode', 'food')
                );
        }
    }

    public function test_it_passes_recent_breakfast_meals_to_add_page(): void
    {
        MealEntry::query()->create([
            'date' => '2026-05-18',
            'meal_type' => 'breakfast',
            'source_type' => MealEntry::SOURCE_CUSTOM,
            'name' => 'Porridge',
            'calories' => 300,
            'protein_g' => 20,
            'carbs_g' => 40,
            'fat_g' => 6,
        ]);

        $this->get('/add')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Add')
                ->has('previousBreakfastMeals', 1)
                ->where('previousBreakfastMeals.0.name', 'Porridge')
            );
    }

    public function test_it_repeats_a_previous_meal_for_a_date(): void
    {
        $entry = MealEntry::query()->create([
            'date' => '2026-05-18',
            'meal_type' => 'breakfast',
            'source_type' => MealEntry::SOURCE_CUSTOM,
            'name' => 'Porridge',
            'calories' => 300,
            'protein_g' => 20,
            'carbs_g' => 40,
            'fat_g' => 6,
        ]);

        $this->post("/meals/{$entry->id}/repeat", [
            'date' => '2026-05-20',
            'meal_type' => 'breakfast',
        ])->assertRedirect('/?date=2026-05-20');

        $this->assertDatabaseHas('meal_entries', [
            'date' => '2026-05-20 00:00:00',
            'meal_type' => 'breakfast',
            'name' => 'Porridge',
            'calories' => 300,
        ]);
    }

    public function test_it_repeats_a_previous_product_meal_with_a_new_portion(): void
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

        $entry = MealEntry::query()->create([
            'date' => '2026-05-18',
            'meal_type' => 'breakfast',
            'source_type' => MealEntry::SOURCE_BARCODE,
            'food_product_id' => $product->id,
            'name' => 'Yoghurt',
            'portion_quantity' => 150,
            'portion_unit' => 'g',
            'calories' => 180,
            'protein_g' => 12,
            'carbs_g' => 18,
            'fat_g' => 6,
        ]);

        $this->post("/meals/{$entry->id}/repeat", [
            'date' => '2026-05-20',
            'meal_type' => 'snacks',
            'portion_quantity' => 200,
            'portion_unit' => 'g',
        ])->assertRedirect('/?date=2026-05-20');

        $repeated = MealEntry::query()
            ->whereKeyNot($entry->id)
            ->firstOrFail();

        $this->assertSame('snacks', $repeated->meal_type);
        $this->assertSame(200.0, (float) $repeated->portion_quantity);
        $this->assertSame('g', $repeated->portion_unit);
        $this->assertSame(240, $repeated->calories);
        $this->assertSame(16.0, (float) $repeated->protein_g);
    }

    public function test_it_searches_saved_food_products(): void
    {
        Http::fake([
            'world.openfoodfacts.org/cgi/search.pl*' => Http::response(['products' => []]),
        ]);

        FoodProduct::query()->create([
            'barcode' => '1234567890123',
            'name' => 'Greek yoghurt',
            'brand' => 'Dairy Co',
            'nutrition_unit' => 'g',
            'calories_per_100' => 120,
            'protein_per_100' => 8,
            'carbs_per_100' => 12,
            'fat_per_100' => 4,
        ]);

        $this->getJson('/food-products/search?q=yoghurt')
            ->assertOk()
            ->assertJsonPath('products.0.name', 'Greek yoghurt');
    }

    public function test_saved_food_product_search_survives_open_food_facts_connection_failures(): void
    {
        Http::fake([
            'world.openfoodfacts.org/cgi/search.pl*' => Http::failedConnection('Open Food Facts timed out.'),
        ]);

        FoodProduct::query()->create([
            'barcode' => '1234567890123',
            'name' => 'Greek yoghurt',
            'brand' => 'Dairy Co',
            'nutrition_unit' => 'g',
            'calories_per_100' => 120,
            'protein_per_100' => 8,
            'carbs_per_100' => 12,
            'fat_per_100' => 4,
        ]);

        $this->getJson('/food-products/search?q=yoghurt')
            ->assertOk()
            ->assertJsonPath('products.0.name', 'Greek yoghurt');
    }

    public function test_it_searches_previous_custom_meals_before_remote_products(): void
    {
        Http::fake([
            'world.openfoodfacts.org/cgi/search.pl*' => Http::response(['products' => []]),
        ]);

        MealEntry::query()->create([
            'date' => '2026-05-19',
            'meal_type' => 'lunch',
            'source_type' => MealEntry::SOURCE_CUSTOM,
            'name' => 'Chicken bowl',
            'calories' => 488,
            'protein_g' => 45,
            'carbs_g' => 50,
            'fat_g' => 12,
        ]);

        $this->getJson('/food-products/search?q=chicken')
            ->assertOk()
            ->assertJsonPath('products.0.type', 'previous_meal')
            ->assertJsonPath('products.0.name', 'Chicken bowl')
            ->assertJsonPath('products.0.calories', 488);
    }

    public function test_it_updates_a_meal_entry(): void
    {
        $entry = MealEntry::query()->create([
            'date' => '2026-05-19',
            'meal_type' => 'lunch',
            'source_type' => MealEntry::SOURCE_CUSTOM,
            'name' => 'Chicken bowl',
            'calories' => 488,
            'protein_g' => 45,
            'carbs_g' => 50,
            'fat_g' => 12,
        ]);

        $this->put("/meals/{$entry->id}", [
            'date' => '2026-05-19',
            'meal_type' => 'dinner',
            'name' => 'Bigger chicken bowl',
            'protein_g' => 50,
            'carbs_g' => 55,
            'fat_g' => 12,
        ])->assertRedirect('/?date=2026-05-19');

        $entry->refresh();

        $this->assertSame('dinner', $entry->meal_type);
        $this->assertSame('Bigger chicken bowl', $entry->name);
        $this->assertSame(528, $entry->calories);
    }
}
