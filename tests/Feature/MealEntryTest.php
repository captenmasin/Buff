<?php

use App\Models\FoodProduct;
use App\Models\MealEntry;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;

it('creates a custom meal and calculates calories', function (): void {
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

    expect($entry->calories)->toBe(488)
        ->and((float) $entry->portion_quantity)->toBe(350.0)
        ->and($entry->portion_unit)->toBe('g')
        ->and($entry->source_type)->toBe(MealEntry::SOURCE_CUSTOM);
});

it('creates a barcode meal from a product portion', function (): void {
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

    expect($entry->calories)->toBe(180)
        ->and((float) $entry->protein_g)->toBe(12.0)
        ->and($entry->source_type)->toBe(MealEntry::SOURCE_BARCODE);
});

it('creates a barcode meal from a liquid product portion', function (): void {
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

    expect($entry->calories)->toBe(83)
        ->and((float) $entry->protein_g)->toBe(9.1)
        ->and($entry->portion_unit)->toBe('ml');
});

it('opens the add page in scan mode', function (): void {
    $this->get('/add?mode=food&scan=1')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Add')
            ->where('mode', 'food')
            ->where('autoScan', true)
        );
});

it('passes unique recent previous custom meals to add page', function (): void {
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
});

it('opens legacy add food modes on the food page', function (): void {
    foreach (['barcode', 'search'] as $mode) {
        $this->get("/add?mode={$mode}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Add')
                ->where('mode', 'food')
            );
    }
});

it('passes recent breakfast meals to add page', function (): void {
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
});

it('repeats a previous meal for a date', function (): void {
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
});

it('repeats a previous product meal with a new portion', function (): void {
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

    expect($repeated->meal_type)->toBe('snacks')
        ->and((float) $repeated->portion_quantity)->toBe(200.0)
        ->and($repeated->portion_unit)->toBe('g')
        ->and($repeated->calories)->toBe(240)
        ->and((float) $repeated->protein_g)->toBe(16.0);
});

it('searches saved food products', function (): void {
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
});

it('keeps saved food product search working when open food facts fails', function (): void {
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
});

it('searches previous custom meals before remote products', function (): void {
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
});

it('updates a meal entry', function (): void {
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

    expect($entry->meal_type)->toBe('dinner')
        ->and($entry->name)->toBe('Bigger chicken bowl')
        ->and($entry->calories)->toBe(528);
});
