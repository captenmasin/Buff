<?php

use App\Models\FoodProduct;
use App\Models\MealEntry;
use App\Models\Recipe;
use Inertia\Testing\AssertableInertia as Assert;

it('creates a recipe and logs it as one meal entry', function (): void {
    $this->post('/recipes', [
        'date' => '2026-05-19',
        'name' => 'Overnight oats',
        'servings' => 1,
        'items' => [
            [
                'name' => 'Oats',
                'food_product_id' => null,
                'portion_quantity' => 80,
                'portion_unit' => 'g',
                'calories' => 300,
                'protein_g' => 10,
                'carbs_g' => 50,
                'fat_g' => 5,
            ],
            [
                'name' => 'Milk',
                'food_product_id' => null,
                'portion_quantity' => 200,
                'portion_unit' => 'ml',
                'calories' => 90,
                'protein_g' => 7,
                'carbs_g' => 10,
                'fat_g' => 2,
            ],
        ],
    ])->assertRedirect('/add?mode=recipe&date=2026-05-19');

    $recipe = Recipe::query()->first();

    expect($recipe->name)->toBe('Overnight oats')
        ->and($recipe->totals()['calories'])->toBe(390);

    $this->post('/meals/recipe', [
        'date' => '2026-05-19',
        'meal_type' => 'dinner',
        'recipe_id' => $recipe->id,
        'servings' => 1,
    ])->assertRedirect('/?date=2026-05-19');

    $entry = MealEntry::query()->first();

    expect($entry->source_type)->toBe(MealEntry::SOURCE_RECIPE)
        ->and($entry->name)->toBe('Overnight oats')
        ->and($entry->calories)->toBe(390)
        ->and((float) $entry->protein_g)->toBe(17.0)
        ->and((float) $entry->portion_quantity)->toBe(1.0)
        ->and($entry->portion_unit)->toBeNull()
        ->and($entry->recipe_id)->toBe($recipe->id);
});

it('rejects an invalid redirect date before persisting a recipe', function (): void {
    $this->from('/add?mode=recipe')
        ->post('/recipes', [
            'date' => 'not-a-date',
            'name' => 'Overnight oats',
            'servings' => 1,
            'items' => [[
                'name' => 'Oats',
                'food_product_id' => null,
                'portion_quantity' => 80,
                'portion_unit' => 'g',
                'calories' => 300,
                'protein_g' => 10,
                'carbs_g' => 50,
                'fat_g' => 5,
            ]],
        ])
        ->assertRedirect('/add?mode=recipe')
        ->assertSessionHasErrors('date');

    $this->assertDatabaseEmpty('recipes');
});

it('requires a name yield and ingredients before creating a recipe', function (): void {
    $this->from('/add?mode=recipe&date=2026-05-19')
        ->post('/recipes', [])
        ->assertRedirect('/add?mode=recipe&date=2026-05-19')
        ->assertSessionHasErrors([
            'name' => 'The name field is required.',
            'servings' => 'The servings field is required.',
            'items' => 'The items field is required.',
        ])
        ->assertOnlyInvalid(['name', 'servings', 'items']);

    $this->assertDatabaseEmpty('recipes');
});

it('rejects invalid recipe fields without creating a recipe', function (string $field, mixed $value, string $message): void {
    $payload = [
        'date' => '2026-05-19',
        'name' => 'Overnight oats',
        'servings' => 1,
        'items' => [[
            'name' => 'Oats',
            'food_product_id' => null,
            'portion_quantity' => 80,
            'portion_unit' => 'g',
            'calories' => 300,
            'protein_g' => 10,
            'carbs_g' => 50,
            'fat_g' => 5,
        ]],
    ];
    data_set($payload, $field, $value);

    $this->from('/add?mode=recipe&date=2026-05-19')
        ->post('/recipes', $payload)
        ->assertRedirect('/add?mode=recipe&date=2026-05-19')
        ->assertSessionHasErrors([$field => $message])
        ->assertOnlyInvalid([$field]);

    $this->assertDatabaseEmpty('recipes');
})->with([
    'blank name' => ['name', ' ', 'The name field is required.'],
    'name over 120 characters' => ['name', str_repeat('a', 121), 'The name field must not be greater than 120 characters.'],
    'blank yield' => ['servings', '', 'The servings field is required.'],
    'nonnumeric yield' => ['servings', 'many', 'The servings field must be a number.'],
    'yield below 0.1' => ['servings', 0.09, 'The servings field must be at least 0.1.'],
    'yield above 100' => ['servings', 100.1, 'The servings field must not be greater than 100.'],
    'empty ingredient list' => ['items', [], 'The items field is required.'],
    'non-array ingredients' => ['items', 'oats', 'The items field must be an array.'],
    'blank ingredient name' => ['items.0.name', '', 'The items.0.name field is required.'],
    'ingredient name over 120 characters' => ['items.0.name', str_repeat('a', 121), 'The items.0.name field must not be greater than 120 characters.'],
    'blank ingredient quantity' => ['items.0.portion_quantity', '', 'The items.0.portion_quantity field is required.'],
    'nonnumeric ingredient quantity' => ['items.0.portion_quantity', 'many', 'The items.0.portion_quantity field must be a number.'],
    'ingredient quantity below 0.1' => ['items.0.portion_quantity', 0.09, 'The items.0.portion_quantity field must be at least 0.1.'],
    'ingredient quantity above 10000' => ['items.0.portion_quantity', 10000.1, 'The items.0.portion_quantity field must not be greater than 10000.'],
    'blank ingredient unit' => ['items.0.portion_unit', '', 'The items.0.portion_unit field is required.'],
    'unsupported ingredient unit' => ['items.0.portion_unit', 'oz', 'The selected items.0.portion_unit is invalid.'],
    'fractional ingredient calories' => ['items.0.calories', 1.5, 'The items.0.calories field must be an integer.'],
    'negative ingredient calories' => ['items.0.calories', -1, 'The items.0.calories field must be at least 0.'],
    'ingredient calories above 20000' => ['items.0.calories', 20001, 'The items.0.calories field must not be greater than 20000.'],
    'blank ingredient protein' => ['items.0.protein_g', '', 'The items.0.protein_g field is required.'],
    'negative ingredient protein' => ['items.0.protein_g', -0.1, 'The items.0.protein_g field must be at least 0.'],
    'ingredient carbs above 1000' => ['items.0.carbs_g', 1000.1, 'The items.0.carbs_g field must not be greater than 1000.'],
    'nonnumeric ingredient fat' => ['items.0.fat_g', 'many', 'The items.0.fat_g field must be a number.'],
    'malformed product reference' => ['items.0.food_product_id', 'not-a-uuid', 'The items.0.food_product_id field must be a valid UUID.'],
    'missing product reference' => ['items.0.food_product_id', '60000000-0000-4000-8000-000000000006', 'The selected items.0.food_product_id is invalid.'],
]);

it('saves recipe yield and name boundaries with their ingredient snapshot', function (float $servings, string $name): void {
    $item = [
        'name' => 'Oats',
        'food_product_id' => null,
        'portion_quantity' => 80,
        'portion_unit' => 'g',
        'calories' => 300,
        'protein_g' => 10,
        'carbs_g' => 50,
        'fat_g' => 5,
    ];

    $this->post('/recipes', [
        'date' => '2026-05-19',
        'name' => $name,
        'servings' => $servings,
        'items' => [$item],
    ])->assertRedirect('/add?mode=recipe&date=2026-05-19')
        ->assertSessionHasNoErrors()
        ->assertSessionHas('message', $name.' saved.');

    $this->assertDatabaseCount('recipes', 1);
    $this->assertDatabaseHas('recipes', ['name' => $name, 'servings' => $servings]);
    expect(Recipe::query()->sole()->items)->toBe([$item]);
    $this->assertDatabaseEmpty('meal_entries');
})->with([
    'minimum yield' => [0.1, 'Small batch'],
    'one serving with a 120-character name' => [1.0, str_repeat('a', 120)],
    'maximum yield' => [100.0, 'Large batch'],
]);

it('requires a product ingredient to use its nutrition unit', function (): void {
    $product = FoodProduct::query()->create([
        'barcode' => '1234567890123',
        'name' => 'Yoghurt',
        'nutrition_unit' => 'g',
        'calories_per_100' => 120,
        'protein_per_100' => 8,
        'carbs_per_100' => 12,
        'fat_per_100' => 4,
    ]);

    $this->post('/recipes', [
        'date' => '2026-05-19',
        'name' => 'Yoghurt bowl',
        'servings' => 1,
        'items' => [[
            'name' => 'Yoghurt',
            'food_product_id' => $product->id,
            'portion_quantity' => 150,
            'portion_unit' => 'ml',
            'calories' => 180,
            'protein_g' => 12,
            'carbs_g' => 18,
            'fat_g' => 6,
        ]],
    ])->assertSessionHasErrors('items.0.portion_unit');

    $this->assertDatabaseEmpty('recipes');
});

it('updates a saved recipe and its ingredients', function (): void {
    $recipe = Recipe::query()->create([
        'name' => 'Overnight oats',
        'servings' => 1,
        'items' => [[
            'name' => 'Oats',
            'food_product_id' => null,
            'portion_quantity' => 80,
            'portion_unit' => 'g',
            'calories' => 300,
            'protein_g' => 10,
            'carbs_g' => 50,
            'fat_g' => 5,
        ]],
    ]);
    $meal = MealEntry::query()->create([
        'date' => '2026-05-18',
        'meal_type' => 'breakfast',
        'source_type' => MealEntry::SOURCE_RECIPE,
        'recipe_id' => $recipe->id,
        'name' => 'Overnight oats',
        'portion_quantity' => 1,
        'portion_unit' => null,
        'calories' => 300,
        'protein_g' => 10,
        'carbs_g' => 50,
        'fat_g' => 5,
    ]);
    $originalMeal = $meal->fresh()->getRawOriginal();
    $updatedItem = [
        'name' => 'Protein yoghurt',
        'food_product_id' => null,
        'portion_quantity' => 150,
        'portion_unit' => 'g',
        'calories' => 200,
        'protein_g' => 25,
        'carbs_g' => 10,
        'fat_g' => 6,
    ];

    $this->from('/add?mode=recipe&date=2026-05-19')
        ->put("/recipes/{$recipe->id}", [
            'name' => 'Protein oats',
            'servings' => 2,
            'items' => [$updatedItem],
        ])
        ->assertRedirect('/add?mode=recipe&date=2026-05-19')
        ->assertSessionHasNoErrors()
        ->assertSessionHas('message', 'Recipe updated.');

    $this->assertDatabaseCount('recipes', 1);
    $this->assertDatabaseHas('recipes', [
        'id' => $recipe->id,
        'name' => 'Protein oats',
        'servings' => 2,
    ]);
    expect($recipe->refresh()->items)->toBe([$updatedItem]);
    $this->assertDatabaseCount('meal_entries', 1);
    expect($meal->refresh()->getRawOriginal())->toBe($originalMeal);
});

it('preserves a recipe and its logged meal when an update is invalid', function (string $field, mixed $value, string $message): void {
    $recipe = Recipe::query()->create([
        'name' => 'Overnight oats',
        'servings' => 1,
        'items' => [[
            'name' => 'Oats',
            'food_product_id' => null,
            'portion_quantity' => 80,
            'portion_unit' => 'g',
            'calories' => 300,
            'protein_g' => 10,
            'carbs_g' => 50,
            'fat_g' => 5,
        ]],
    ]);
    $meal = MealEntry::query()->create([
        'date' => '2026-05-18',
        'meal_type' => 'breakfast',
        'source_type' => MealEntry::SOURCE_RECIPE,
        'recipe_id' => $recipe->id,
        'name' => 'Overnight oats',
        'portion_quantity' => 1,
        'portion_unit' => null,
        'calories' => 300,
        'protein_g' => 10,
        'carbs_g' => 50,
        'fat_g' => 5,
    ]);
    $originalRecipe = $recipe->fresh()->getRawOriginal();
    $originalMeal = $meal->fresh()->getRawOriginal();
    $payload = [
        'name' => 'Protein oats',
        'servings' => 2,
        'items' => [[
            'name' => 'Protein yoghurt',
            'food_product_id' => null,
            'portion_quantity' => 150,
            'portion_unit' => 'g',
            'calories' => 200,
            'protein_g' => 25,
            'carbs_g' => 10,
            'fat_g' => 6,
        ]],
    ];
    data_set($payload, $field, $value);

    $this->from('/add?mode=recipe&date=2026-05-19')
        ->put("/recipes/{$recipe->id}", $payload)
        ->assertRedirect('/add?mode=recipe&date=2026-05-19')
        ->assertSessionHasErrors([$field => $message])
        ->assertOnlyInvalid([$field])
        ->assertSessionMissing('message');

    $this->assertDatabaseCount('recipes', 1);
    expect($recipe->refresh()->getRawOriginal())->toBe($originalRecipe);
    $this->assertDatabaseCount('meal_entries', 1);
    expect($meal->refresh()->getRawOriginal())->toBe($originalMeal);
})->with([
    'invalid recipe name' => ['name', '', 'The name field is required.'],
    'invalid nested ingredient quantity' => ['items.0.portion_quantity', 0, 'The items.0.portion_quantity field must be at least 0.1.'],
]);

it('scales recipe macros by logged servings', function (): void {
    $recipe = Recipe::query()->create([
        'name' => 'Overnight oats',
        'servings' => 1,
        'items' => [
            [
                'name' => 'Oats',
                'food_product_id' => null,
                'portion_quantity' => 80,
                'portion_unit' => 'g',
                'calories' => 300,
                'protein_g' => 10,
                'carbs_g' => 50,
                'fat_g' => 5,
            ],
        ],
    ]);

    $this->post('/meals/recipe', [
        'date' => '2026-05-19',
        'meal_type' => 'breakfast',
        'recipe_id' => $recipe->id,
        'servings' => 2,
    ])->assertRedirect('/?date=2026-05-19');

    $entry = MealEntry::query()->first();

    expect($entry->calories)->toBe(600)
        ->and((float) $entry->protein_g)->toBe(20.0)
        ->and((float) $entry->portion_quantity)->toBe(2.0);
});

it('scales fractional servings from the full recipe batch before rounding', function (int $servings, int $calories, float $protein, float $carbs, float $fat): void {
    $recipe = Recipe::query()->create([
        'name' => 'Four-serving recipe',
        'servings' => 4,
        'items' => [[
            'name' => 'Batch ingredient',
            'food_product_id' => null,
            'portion_quantity' => 100,
            'portion_unit' => 'g',
            'calories' => 281,
            'protein_g' => 17,
            'carbs_g' => 33,
            'fat_g' => 9,
        ]],
    ]);

    $this->post('/meals/recipe', [
        'date' => '2026-05-19',
        'meal_type' => 'dinner',
        'recipe_id' => $recipe->id,
        'servings' => $servings,
    ])->assertRedirect('/?date=2026-05-19')
        ->assertSessionHas('message', 'Recipe logged.');

    $this->assertDatabaseCount('meal_entries', 1);
    $this->assertDatabaseHas('meal_entries', [
        'date' => '2026-05-19 00:00:00',
        'meal_type' => 'dinner',
        'source_type' => MealEntry::SOURCE_RECIPE,
        'recipe_id' => $recipe->id,
        'food_product_id' => null,
        'name' => 'Four-serving recipe',
        'portion_quantity' => $servings,
        'portion_unit' => null,
        'calories' => $calories,
        'protein_g' => $protein,
        'carbs_g' => $carbs,
        'fat_g' => $fat,
    ]);
})->with([
    'one serving rounds a quarter batch down' => [1, 70, 4.25, 8.25, 2.25],
    'two servings round the half batch up, not two rounded quarters' => [2, 141, 8.5, 16.5, 4.5],
    'four servings retain the entire batch' => [4, 281, 17.0, 33.0, 9.0],
]);

it('rejects blank servings without logging the recipe', function (): void {
    $recipe = Recipe::query()->create([
        'name' => 'Overnight oats',
        'servings' => 0.1,
        'items' => [[
            'name' => 'Oats',
            'food_product_id' => null,
            'portion_quantity' => 80,
            'portion_unit' => 'g',
            'calories' => 39,
            'protein_g' => 1,
            'carbs_g' => 6,
            'fat_g' => 1,
        ]],
    ]);

    $this->post('/meals/recipe', [
        'date' => '2026-05-19',
        'meal_type' => 'breakfast',
        'recipe_id' => $recipe->id,
        'servings' => '',
    ])->assertSessionHasErrors('servings');

    $this->assertDatabaseEmpty('meal_entries');
});

it('keeps logged meals after a recipe is deleted', function (): void {
    $recipe = Recipe::query()->create([
        'name' => 'Overnight oats',
        'servings' => 1,
        'items' => [
            [
                'name' => 'Oats',
                'food_product_id' => null,
                'portion_quantity' => 80,
                'portion_unit' => 'g',
                'calories' => 300,
                'protein_g' => 10,
                'carbs_g' => 50,
                'fat_g' => 5,
            ],
        ],
    ]);

    $entry = MealEntry::query()->create([
        'date' => '2026-05-19',
        'meal_type' => 'breakfast',
        'source_type' => MealEntry::SOURCE_RECIPE,
        'recipe_id' => $recipe->id,
        'name' => 'Overnight oats',
        'portion_quantity' => 1,
        'calories' => 300,
        'protein_g' => 10,
        'carbs_g' => 50,
        'fat_g' => 5,
    ]);

    $this->delete("/recipes/{$recipe->id}")->assertRedirect();

    $this->assertDatabaseMissing('recipes', ['id' => $recipe->id]);
    $this->assertDatabaseHas('meal_entries', [
        'id' => $entry->id,
        'recipe_id' => null,
        'calories' => 300,
    ]);
});

it('passes recipes to the add recipe mode', function (): void {
    $recipe = Recipe::query()->create([
        'name' => 'Overnight oats',
        'servings' => 1,
        'items' => [
            [
                'name' => 'Oats',
                'food_product_id' => null,
                'portion_quantity' => 80,
                'portion_unit' => 'g',
                'calories' => 300,
                'protein_g' => 10,
                'carbs_g' => 50,
                'fat_g' => 5,
            ],
        ],
    ]);

    $this->get('/add?mode=recipe')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Add')
            ->where('mode', 'recipe')
            ->has('recipes', 1)
            ->where('recipes.0.id', $recipe->id)
            ->where('recipes.0.name', 'Overnight oats')
            ->where('recipes.0.calories', 300)
        );
});
