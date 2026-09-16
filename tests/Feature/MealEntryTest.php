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

it('rejects invalid custom food without creating or changing a meal', function (string $field, mixed $value, string $message): void {
    $payload = [
        'date' => '2026-05-19',
        'meal_type' => 'lunch',
        'name' => 'Custom bowl',
        'portion_quantity' => 100,
        'portion_unit' => 'g',
        'protein_g' => 20,
        'carbs_g' => 60,
        'fat_g' => 20,
    ];
    $existingMeal = MealEntry::query()->create([
        ...$payload,
        'date' => '2026-05-18',
        'name' => 'Existing bowl',
        'source_type' => MealEntry::SOURCE_CUSTOM,
        'calories' => 500,
    ]);
    $originalMeal = $existingMeal->fresh()->getRawOriginal();
    $payload[$field] = $value;

    $this->from('/add?mode=custom&date=2026-05-19')
        ->post('/meals/custom', $payload)
        ->assertRedirect('/add?mode=custom&date=2026-05-19')
        ->assertSessionHasErrors([$field => $message])
        ->assertOnlyInvalid([$field])
        ->assertSessionMissing('message');

    $this->assertDatabaseCount('meal_entries', 1);
    expect($existingMeal->refresh()->getRawOriginal())->toBe($originalMeal);
})->with([
    'blank name' => ['name', '', 'The name field is required.'],
    'name over 120 characters' => ['name', str_repeat('a', 121), 'The name field must not be greater than 120 characters.'],
    'zero portion' => ['portion_quantity', 0, 'The portion quantity field must be at least 0.1.'],
    'portion above 10000' => ['portion_quantity', 10000.1, 'The portion quantity field must not be greater than 10000.'],
    'unsupported unit' => ['portion_unit', 'oz', 'The selected portion unit is invalid.'],
    'negative protein' => ['protein_g', -0.1, 'The protein g field must be at least 0.'],
    'protein above 1000' => ['protein_g', 1000.1, 'The protein g field must not be greater than 1000.'],
    'negative carbs' => ['carbs_g', -0.1, 'The carbs g field must be at least 0.'],
    'carbs above 1000' => ['carbs_g', 1000.1, 'The carbs g field must not be greater than 1000.'],
    'negative fat' => ['fat_g', -0.1, 'The fat g field must be at least 0.'],
    'fat above 1000' => ['fat_g', 1000.1, 'The fat g field must not be greater than 1000.'],
]);

it('saves custom food at the name portion and numeric macro boundaries', function (string $name, float $quantity, string $unit, int $macro, int $calories): void {
    $payload = [
        'date' => '2026-05-19',
        'meal_type' => 'lunch',
        'name' => $name,
        'portion_quantity' => $quantity,
        'portion_unit' => $unit,
        'protein_g' => $macro,
        'carbs_g' => $macro,
        'fat_g' => $macro,
    ];

    $this->post('/meals/custom', $payload)
        ->assertRedirect('/?date=2026-05-19')
        ->assertSessionHasNoErrors()
        ->assertSessionHas('message', 'Custom food added.');

    $this->assertDatabaseCount('meal_entries', 1);
    $this->assertDatabaseHas('meal_entries', [
        ...$payload,
        'date' => '2026-05-19 00:00:00',
        'source_type' => MealEntry::SOURCE_CUSTOM,
        'food_product_id' => null,
        'recipe_id' => null,
        'calories' => $calories,
    ]);
})->with([
    '120-character name with minimum portion and numeric zero macros' => [str_repeat('a', 120), 0.1, 'g', 0, 0],
    'maximum portion and macros in millilitres' => ['Maximum bowl', 10000.0, 'ml', 1000, 17000],
]);

it('deletes a meal', function (): void {
    $entry = MealEntry::query()->create([
        'date' => '2026-05-19',
        'meal_type' => 'lunch',
        'source_type' => MealEntry::SOURCE_CUSTOM,
        'name' => 'Lunch',
        'calories' => 500,
        'protein_g' => 30,
        'carbs_g' => 50,
        'fat_g' => 15,
    ]);

    $this->delete("/meals/{$entry->id}")->assertRedirect('/?date=2026-05-19');

    $this->assertDatabaseMissing('meal_entries', ['id' => $entry->id]);
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

it('rejects invalid add-screen dates without a server error', function (): void {
    $this->from('/add')
        ->get('/add?date=not-a-date')
        ->assertRedirect('/add')
        ->assertSessionHasErrors('date');
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

it('passes a selected meal type to the add page', function (): void {
    $this->get('/add?mode=food&meal=lunch&date=2026-05-19')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Add')
            ->where('mode', 'food')
            ->where('meal', 'lunch')
            ->where('date', '2026-05-19')
        );
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

it('ignores an invalid meal deep-link value', function (): void {
    $this->get('/add?mode=custom&meal=nonsense')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Add')
            ->where('mode', 'custom')
            ->where('meal', null)
        );
});

it('opens the add launcher with its chooser and preserves dates', function (): void {
    $this->get('/add?date=2026-05-19')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Add')
            ->where('mode', 'choose')
            ->where('date', '2026-05-19')
        );
});

it('passes unique recent previous food entries to add page', function (): void {
    $product = FoodProduct::query()->create([
        'barcode' => '1234567890123',
        'name' => 'Yoghurt',
        'brand' => 'Dairy Co',
        'image_url' => 'https://images.example/yoghurt.jpg',
        'nutrition_unit' => 'g',
        'calories_per_100' => 120,
        'protein_per_100' => 8,
        'carbs_per_100' => 12,
        'fat_per_100' => 4,
    ]);

    MealEntry::query()->create([
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

    $recipeEntry = MealEntry::query()->create([
        'date' => '2026-05-19',
        'meal_type' => 'dinner',
        'source_type' => MealEntry::SOURCE_RECIPE,
        'name' => 'Overnight oats',
        'portion_quantity' => 2,
        'portion_unit' => null,
        'calories' => 390,
        'protein_g' => 17,
        'carbs_g' => 60,
        'fat_g' => 7,
    ]);
    $recipeEntry->forceFill([
        'created_at' => now()->addSecond(),
        'updated_at' => now()->addSecond(),
    ])->save();

    $this->get('/add?mode=food')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Add')
            ->has('previousFoodEntries', 2)
            ->where('previousFoodEntries.0.name', 'Overnight oats')
            ->where('previousFoodEntries.0.source_type', MealEntry::SOURCE_RECIPE)
            ->where('previousFoodEntries.1.name', 'Yoghurt')
            ->where('previousFoodEntries.1.image_url', 'https://images.example/yoghurt.jpg')
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
            ->where('mode', 'custom')
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

    $product = FoodProduct::query()->create([
        'barcode' => '1234567890123',
        'name' => 'Greek yoghurt',
        'brand' => 'Dairy Co',
        'nutrition_unit' => 'g',
        'calories_per_100' => 120,
        'protein_per_100' => 8,
        'carbs_per_100' => 12,
        'fat_per_100' => 4,
    ]);
    MealEntry::query()->create([
        'date' => '2026-05-19',
        'meal_type' => 'breakfast',
        'source_type' => MealEntry::SOURCE_BARCODE,
        'food_product_id' => $product->id,
        'name' => $product->name,
        'calories' => 120,
        'protein_g' => 8,
        'carbs_g' => 12,
        'fat_g' => 4,
    ]);

    $this->getJson('/food-products/search?q=yoghurt')
        ->assertOk()
        ->assertJsonPath('products.0.name', 'Greek yoghurt');
});

it('keeps saved food product search working when open food facts fails', function (): void {
    Http::fake([
        'world.openfoodfacts.org/cgi/search.pl*' => Http::failedConnection('Open Food Facts timed out.'),
    ]);

    $product = FoodProduct::query()->create([
        'barcode' => '1234567890123',
        'name' => 'Greek yoghurt',
        'brand' => 'Dairy Co',
        'nutrition_unit' => 'g',
        'calories_per_100' => 120,
        'protein_per_100' => 8,
        'carbs_per_100' => 12,
        'fat_per_100' => 4,
    ]);
    MealEntry::query()->create([
        'date' => '2026-05-19',
        'meal_type' => 'breakfast',
        'source_type' => MealEntry::SOURCE_BARCODE,
        'food_product_id' => $product->id,
        'name' => $product->name,
        'calories' => 120,
        'protein_g' => 8,
        'carbs_g' => 12,
        'fat_g' => 4,
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

it('updates meal macros without changing its portion', function (): void {
    $entry = MealEntry::query()->create([
        'date' => '2026-05-19',
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

    $this->put("/meals/{$entry->id}", [
        'date' => '2026-05-19',
        'meal_type' => 'dinner',
        'name' => 'Bigger chicken bowl',
        'edit_mode' => 'macros',
        'protein_g' => 50,
        'carbs_g' => 55,
        'fat_g' => 12,
    ])->assertRedirect('/?date=2026-05-19');

    $entry->refresh();

    expect($entry->meal_type)->toBe('dinner')
        ->and($entry->name)->toBe('Bigger chicken bowl')
        ->and((float) $entry->portion_quantity)->toBe(350.0)
        ->and($entry->portion_unit)->toBe('g')
        ->and($entry->calories)->toBe(528)
        ->and((float) $entry->protein_g)->toBe(50.0)
        ->and((float) $entry->carbs_g)->toBe(55.0)
        ->and((float) $entry->fat_g)->toBe(12.0);
});

it('updates a meal portion without requiring macros', function (): void {
    $entry = MealEntry::query()->create([
        'date' => '2026-05-19',
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

    $this->put("/meals/{$entry->id}", [
        'date' => '2026-05-19',
        'meal_type' => 'lunch',
        'name' => 'Chicken bowl',
        'edit_mode' => 'portion',
        'portion_quantity' => 700,
        'portion_unit' => 'g',
    ])->assertRedirect('/?date=2026-05-19');

    $entry->refresh();

    expect((float) $entry->portion_quantity)->toBe(700.0)
        ->and($entry->calories)->toBe(976)
        ->and((float) $entry->protein_g)->toBe(90.0)
        ->and((float) $entry->carbs_g)->toBe(100.0)
        ->and((float) $entry->fat_g)->toBe(24.0);
});

it('preserves edited barcode nutrition when only its meal type and name change', function (): void {
    $product = FoodProduct::query()->create([
        'barcode' => '1234567890123',
        'name' => 'Yoghurt',
        'nutrition_unit' => 'g',
        'calories_per_100' => 200,
        'protein_per_100' => 10,
        'carbs_per_100' => 30,
        'fat_per_100' => 4,
    ]);
    $entry = MealEntry::query()->create([
        'date' => '2026-05-19',
        'meal_type' => 'lunch',
        'source_type' => MealEntry::SOURCE_BARCODE,
        'food_product_id' => $product->id,
        'name' => 'Yoghurt',
        'portion_quantity' => 100,
        'portion_unit' => 'g',
        'calories' => 200,
        'protein_g' => 10,
        'carbs_g' => 30,
        'fat_g' => 4,
    ]);
    $this->put("/meals/{$entry->id}", [
        'date' => '2026-05-19',
        'meal_type' => 'lunch',
        'name' => 'Yoghurt',
        'edit_mode' => 'macros',
        'protein_g' => 20,
        'carbs_g' => 60,
        'fat_g' => 20,
    ])->assertRedirect('/?date=2026-05-19');
    expect($entry->refresh()->calories)->toBe(500);

    $this->put("/meals/{$entry->id}", [
        'date' => '2026-05-19',
        'meal_type' => 'dinner',
        'name' => 'Evening yoghurt',
        'edit_mode' => 'portion',
        'portion_quantity' => '100.00',
        'portion_unit' => 'g',
    ])->assertRedirect('/?date=2026-05-19');

    expect($entry->refresh())
        ->meal_type->toBe('dinner')
        ->name->toBe('Evening yoghurt')
        ->food_product_id->toBe($product->id)
        ->calories->toBe(500)
        ->and((float) $entry->portion_quantity)->toBe(100.0)
        ->and($entry->portion_unit)->toBe('g')
        ->and((float) $entry->protein_g)->toBe(20.0)
        ->and((float) $entry->carbs_g)->toBe(60.0)
        ->and((float) $entry->fat_g)->toBe(20.0);
    $this->assertDatabaseCount('meal_entries', 1);
});

it('preserves saved barcode nutrition during metadata edits after catalogue changes', function (): void {
    $product = FoodProduct::query()->create([
        'barcode' => '1234567890123',
        'name' => 'Yoghurt',
        'nutrition_unit' => 'g',
        'calories_per_100' => 200,
        'protein_per_100' => 10,
        'carbs_per_100' => 30,
        'fat_per_100' => 4,
    ]);
    $entry = MealEntry::query()->create([
        'date' => '2026-05-19',
        'meal_type' => 'lunch',
        'source_type' => MealEntry::SOURCE_BARCODE,
        'food_product_id' => $product->id,
        'name' => 'Yoghurt',
        'portion_quantity' => 100,
        'portion_unit' => 'g',
        'calories' => 200,
        'protein_g' => 10,
        'carbs_g' => 30,
        'fat_g' => 4,
    ]);
    $product->update([
        'calories_per_100' => 350,
        'protein_per_100' => 15,
        'carbs_per_100' => 40,
        'fat_per_100' => 8,
    ]);

    $this->put("/meals/{$entry->id}", [
        'date' => '2026-05-20',
        'meal_type' => 'lunch',
        'name' => 'Yoghurt',
        'edit_mode' => 'portion',
        'portion_quantity' => 100,
        'portion_unit' => 'g',
    ])->assertRedirect('/?date=2026-05-20');

    expect($entry->refresh()->date->toDateString())->toBe('2026-05-20')
        ->and($entry->calories)->toBe(200)
        ->and((float) $entry->protein_g)->toBe(10.0)
        ->and((float) $entry->carbs_g)->toBe(30.0)
        ->and((float) $entry->fat_g)->toBe(4.0);
});

it('recalculates barcode nutrition when the portion actually changes', function (): void {
    $product = FoodProduct::query()->create([
        'barcode' => '1234567890123',
        'name' => 'Yoghurt',
        'nutrition_unit' => 'g',
        'calories_per_100' => 200,
        'protein_per_100' => 10,
        'carbs_per_100' => 30,
        'fat_per_100' => 4,
    ]);
    $entry = MealEntry::query()->create([
        'date' => '2026-05-19',
        'meal_type' => 'lunch',
        'source_type' => MealEntry::SOURCE_BARCODE,
        'food_product_id' => $product->id,
        'name' => 'Yoghurt',
        'portion_quantity' => 100,
        'portion_unit' => 'g',
        'calories' => 500,
        'protein_g' => 20,
        'carbs_g' => 60,
        'fat_g' => 20,
    ]);

    $this->put("/meals/{$entry->id}", [
        'date' => '2026-05-19',
        'meal_type' => 'lunch',
        'name' => 'Yoghurt',
        'edit_mode' => 'portion',
        'portion_quantity' => 200,
        'portion_unit' => 'g',
    ])->assertRedirect('/?date=2026-05-19');

    expect((float) $entry->refresh()->portion_quantity)->toBe(200.0)
        ->and($entry->calories)->toBe(400)
        ->and((float) $entry->protein_g)->toBe(20.0)
        ->and((float) $entry->carbs_g)->toBe(60.0)
        ->and((float) $entry->fat_g)->toBe(8.0);
});

it('rejects a unit-only portion change without saving metadata or nutrition', function (): void {
    $entry = MealEntry::query()->create([
        'date' => '2026-05-19',
        'meal_type' => 'lunch',
        'source_type' => MealEntry::SOURCE_CUSTOM,
        'name' => 'Chicken bowl',
        'portion_quantity' => 100,
        'portion_unit' => 'g',
        'calories' => 500,
        'protein_g' => 20,
        'carbs_g' => 60,
        'fat_g' => 20,
    ]);

    $this->from('/?date=2026-05-19')->put("/meals/{$entry->id}", [
        'date' => '2026-05-19',
        'meal_type' => 'dinner',
        'name' => 'Changed name',
        'edit_mode' => 'portion',
        'portion_quantity' => 100,
        'portion_unit' => 'ml',
    ])->assertRedirect('/?date=2026-05-19')
        ->assertSessionHasErrors(['portion_unit' => 'Use the original portion unit for this saved meal.']);

    expect($entry->refresh())
        ->meal_type->toBe('lunch')
        ->name->toBe('Chicken bowl')
        ->portion_unit->toBe('g')
        ->calories->toBe(500)
        ->and((float) $entry->portion_quantity)->toBe(100.0)
        ->and((float) $entry->protein_g)->toBe(20.0)
        ->and((float) $entry->carbs_g)->toBe(60.0)
        ->and((float) $entry->fat_g)->toBe(20.0);
});

it('preserves an unportioned calorie snapshot when submitted macros are unchanged', function (): void {
    $entry = MealEntry::query()->create([
        'date' => '2026-05-19',
        'meal_type' => 'lunch',
        'source_type' => MealEntry::SOURCE_CUSTOM,
        'name' => 'Saved meal',
        'calories' => 450,
        'protein_g' => 30,
        'carbs_g' => 50,
        'fat_g' => 15,
    ]);

    $this->put("/meals/{$entry->id}", [
        'date' => '2026-05-19',
        'meal_type' => 'dinner',
        'name' => 'Saved dinner',
        'edit_mode' => 'macros',
        'protein_g' => '30.00',
        'carbs_g' => 50,
        'fat_g' => '15.0',
    ])->assertRedirect('/?date=2026-05-19');

    expect($entry->refresh())
        ->meal_type->toBe('dinner')
        ->name->toBe('Saved dinner')
        ->portion_quantity->toBeNull()
        ->portion_unit->toBeNull()
        ->calories->toBe(450)
        ->and((float) $entry->protein_g)->toBe(30.0)
        ->and((float) $entry->carbs_g)->toBe(50.0)
        ->and((float) $entry->fat_g)->toBe(15.0);
});

it('recalculates calories when an individual macro changes', function (string $field, int $value, int $calories): void {
    $entry = MealEntry::query()->create([
        'date' => '2026-05-19',
        'meal_type' => 'lunch',
        'source_type' => MealEntry::SOURCE_CUSTOM,
        'name' => 'Saved meal',
        'calories' => 450,
        'protein_g' => 30,
        'carbs_g' => 50,
        'fat_g' => 15,
    ]);
    $macros = ['protein_g' => 30, 'carbs_g' => 50, 'fat_g' => 15, $field => $value];

    $this->put("/meals/{$entry->id}", [
        'date' => '2026-05-19',
        'meal_type' => 'lunch',
        'name' => 'Saved meal',
        'edit_mode' => 'macros',
        ...$macros,
    ])->assertRedirect('/?date=2026-05-19');

    expect($entry->refresh()->calories)->toBe($calories)
        ->and((float) $entry->protein_g)->toBe((float) $macros['protein_g'])
        ->and((float) $entry->carbs_g)->toBe((float) $macros['carbs_g'])
        ->and((float) $entry->fat_g)->toBe((float) $macros['fat_g']);
})->with([
    'protein only' => ['protein_g', 31, 459],
    'carbohydrate only' => ['carbs_g', 51, 459],
    'fat only' => ['fat_g', 16, 464],
]);

it('repeats a barcode nutrition snapshot when no replacement portion is submitted', function (): void {
    $product = FoodProduct::query()->create([
        'barcode' => '1234567890123',
        'name' => 'Yoghurt',
        'nutrition_unit' => 'g',
        'calories_per_100' => 200,
        'protein_per_100' => 10,
        'carbs_per_100' => 30,
        'fat_per_100' => 4,
    ]);
    $entry = MealEntry::query()->create([
        'date' => '2026-05-19',
        'meal_type' => 'lunch',
        'source_type' => MealEntry::SOURCE_BARCODE,
        'food_product_id' => $product->id,
        'name' => 'Yoghurt',
        'portion_quantity' => 100,
        'portion_unit' => 'g',
        'calories' => 500,
        'protein_g' => 20,
        'carbs_g' => 60,
        'fat_g' => 20,
    ]);

    $this->post("/meals/{$entry->id}/repeat", [
        'date' => '2026-05-20',
        'meal_type' => 'snacks',
    ])->assertRedirect('/?date=2026-05-20');

    $copy = MealEntry::query()->whereKeyNot($entry->id)->firstOrFail();
    expect($copy->date->toDateString())->toBe('2026-05-20')
        ->and($copy->meal_type)->toBe('snacks')
        ->and($copy->food_product_id)->toBe($product->id)
        ->and($copy->calories)->toBe(500)
        ->and((float) $copy->portion_quantity)->toBe(100.0)
        ->and($copy->portion_unit)->toBe('g')
        ->and((float) $copy->protein_g)->toBe(20.0)
        ->and((float) $copy->carbs_g)->toBe(60.0)
        ->and((float) $copy->fat_g)->toBe(20.0)
        ->and($entry->refresh()->date->toDateString())->toBe('2026-05-19')
        ->and($entry->calories)->toBe(500);
    $this->assertDatabaseCount('meal_entries', 2);
});

it('updates recipe servings as a portion', function (): void {
    $entry = MealEntry::query()->create([
        'date' => '2026-05-19',
        'meal_type' => 'dinner',
        'source_type' => MealEntry::SOURCE_RECIPE,
        'name' => 'Pasta bake',
        'portion_quantity' => 2,
        'portion_unit' => null,
        'calories' => 800,
        'protein_g' => 40,
        'carbs_g' => 100,
        'fat_g' => 20,
    ]);

    $this->put("/meals/{$entry->id}", [
        'date' => '2026-05-19',
        'meal_type' => 'dinner',
        'name' => 'Pasta bake',
        'edit_mode' => 'portion',
        'portion_quantity' => 1,
        'portion_unit' => null,
    ])->assertRedirect('/?date=2026-05-19');

    $entry->refresh();

    expect((float) $entry->portion_quantity)->toBe(1.0)
        ->and($entry->calories)->toBe(400)
        ->and((float) $entry->protein_g)->toBe(20.0)
        ->and((float) $entry->carbs_g)->toBe(50.0)
        ->and((float) $entry->fat_g)->toBe(10.0);
});
