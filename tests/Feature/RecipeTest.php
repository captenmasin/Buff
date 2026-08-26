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
