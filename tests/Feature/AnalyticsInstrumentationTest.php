<?php

use App\Models\BodyMetric;
use App\Models\FoodProduct;
use App\Models\MealEntry;
use App\Models\PendingAnalyticsEvent;
use App\Models\Recipe;
use App\Models\WorkoutEntry;
use App\Services\BuffCredentialStore;
use App\Services\BuffSyncService;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    app(BuffCredentialStore::class)->store('analytics-token', [
        'id' => '1',
        'name' => 'Mason',
        'email' => 'mason@example.com',
        'email_verified' => true,
    ]);
    Http::preventStrayRequests();
    Http::fake(['*/sync' => Http::failedConnection()]);
});

it('records manual workouts and explicit progress logs without treating record creation or sync snapshots as actions', function (): void {
    $meal = MealEntry::query()->create([
        'date' => '2026-09-25',
        'meal_type' => 'lunch',
        'source_type' => MealEntry::SOURCE_CUSTOM,
        'name' => 'Lunch',
        'calories' => 400,
        'protein_g' => 30,
        'carbs_g' => 45,
        'fat_g' => 10,
    ]);
    $meal->update(['name' => 'Edited lunch']);

    WorkoutEntry::query()->create([
        'date' => '2026-09-25',
        'title' => 'Run',
        'calories_burned' => 220,
        'logged_at' => '2026-09-25 08:00:00',
        'source_type' => WorkoutEntry::SOURCE_MANUAL,
    ]);
    WorkoutEntry::query()->create([
        'date' => '2026-09-25',
        'title' => 'Imported walk',
        'calories_burned' => 80,
        'logged_at' => '2026-09-25 09:00:00',
        'source_type' => WorkoutEntry::SOURCE_HEALTH_CONNECT,
    ]);

    $metric = BodyMetric::query()->create(['date' => '2026-09-25', 'weight_kg' => 80]);
    $metric->update(['weight_kg' => 79.9]);
    $this->post('/progress/body-metrics', [
        'date' => '2026-09-24',
        'weight_kg' => 79.8,
    ])->assertRedirect('/progress?range=30');
    app(BuffSyncService::class)->queueExistingRecords();

    expect(PendingAnalyticsEvent::query()->pluck('name')->all())
        ->toBe(['workout_logged', 'progress_logged']);
});

it('records custom and photo meal adds separately', function (?string $analysisId, string $event): void {
    $this->post('/meals/custom', [
        'date' => '2026-09-25',
        'meal_type' => 'lunch',
        'name' => 'Lunch',
        'portion_quantity' => 200,
        'portion_unit' => 'g',
        'protein_g' => 20,
        'carbs_g' => 30,
        'fat_g' => 10,
        'analysis_id' => $analysisId,
    ])->assertRedirect('/?date=2026-09-25');

    $this->assertDatabaseCount('meal_entries', 1);
    expect(PendingAnalyticsEvent::query()->pluck('name')->all())->toBe([$event]);
})->with([
    'custom' => [null, 'meal_added_custom'],
    'photo analysis' => ['10000000-0000-4000-8000-000000000001', 'meal_added_photo'],
]);

it('distinguishes barcode lookup from food search adds', function (?string $addMethod, string $event): void {
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
        'date' => '2026-09-25',
        'meal_type' => 'lunch',
        'food_product_id' => $product->id,
        'portion_quantity' => 100,
        'portion_unit' => 'g',
        ...($addMethod === null ? [] : ['add_method' => $addMethod]),
    ])->assertRedirect('/?date=2026-09-25');

    $this->assertDatabaseCount('meal_entries', 1);
    expect(PendingAnalyticsEvent::query()->pluck('name')->all())->toBe([$event]);
})->with([
    'barcode lookup or scan' => [null, 'meal_added_barcode'],
    'explicit barcode method' => ['barcode', 'meal_added_barcode'],
    'food search' => ['search', 'meal_added_search'],
]);

it('records a recipe add', function (): void {
    $recipe = Recipe::query()->create([
        'name' => 'Oats',
        'servings' => 1,
        'items' => [[
            'name' => 'Oats',
            'calories' => 300,
            'protein_g' => 10,
            'carbs_g' => 50,
            'fat_g' => 5,
        ]],
    ]);

    $this->post('/meals/recipe', [
        'date' => '2026-09-25',
        'meal_type' => 'lunch',
        'recipe_id' => $recipe->id,
        'servings' => 1,
    ])->assertRedirect('/?date=2026-09-25');

    $this->assertDatabaseCount('meal_entries', 1);
    expect(PendingAnalyticsEvent::query()->pluck('name')->all())->toBe(['meal_added_recipe']);
});

it('records repeats without recounting the original meal source', function (): void {
    $meal = MealEntry::query()->create([
        'date' => '2026-09-24',
        'meal_type' => 'lunch',
        'source_type' => MealEntry::SOURCE_CUSTOM,
        'name' => 'Lunch',
        'calories' => 400,
        'protein_g' => 30,
        'carbs_g' => 45,
        'fat_g' => 10,
    ]);

    $this->post("/meals/{$meal->id}/repeat", [
        'date' => '2026-09-25',
    ])->assertRedirect('/?date=2026-09-25');

    $this->assertDatabaseCount('meal_entries', 2);
    expect(PendingAnalyticsEvent::query()->pluck('name')->all())->toBe(['meal_repeated']);
});

it('records successful meal edits and deletes', function (): void {
    $meal = MealEntry::query()->create([
        'date' => '2026-09-25',
        'meal_type' => 'lunch',
        'source_type' => MealEntry::SOURCE_CUSTOM,
        'name' => 'Lunch',
        'calories' => 400,
        'protein_g' => 30,
        'carbs_g' => 45,
        'fat_g' => 10,
    ]);

    $this->put("/meals/{$meal->id}", [
        'date' => '2026-09-25',
        'meal_type' => 'lunch',
        'name' => 'Updated lunch',
        'protein_g' => 30,
        'carbs_g' => 45,
        'fat_g' => 10,
    ])->assertRedirect('/?date=2026-09-25');

    expect($meal->refresh()->name)->toBe('Updated lunch');
    expect(PendingAnalyticsEvent::query()->pluck('name')->all())->toBe(['meal_updated']);

    $this->delete("/meals/{$meal->id}")->assertRedirect('/?date=2026-09-25');

    $this->assertDatabaseMissing('meal_entries', ['id' => $meal->id]);
    expect(PendingAnalyticsEvent::query()->pluck('name')->all())->toBe(['meal_updated', 'meal_deleted']);
});

it('does not record rejected meal edits', function (): void {
    $meal = MealEntry::query()->create([
        'date' => '2026-09-25',
        'meal_type' => 'lunch',
        'source_type' => MealEntry::SOURCE_CUSTOM,
        'name' => 'Lunch',
        'calories' => 400,
        'protein_g' => 30,
        'carbs_g' => 45,
        'fat_g' => 10,
    ]);

    $this->from('/')->put("/meals/{$meal->id}", [
        'date' => '2026-09-25',
        'meal_type' => 'lunch',
        'name' => '',
        'protein_g' => 30,
        'carbs_g' => 45,
        'fat_g' => 10,
    ])->assertSessionHasErrors('name');

    expect($meal->refresh()->name)->toBe('Lunch');
    $this->assertDatabaseEmpty('pending_analytics_events');
});

it('does not record rejected meal adds', function (): void {
    $this->from('/add')->post('/meals/custom', [
        'date' => '2026-09-25',
        'meal_type' => 'lunch',
        'name' => '',
        'portion_quantity' => 200,
        'portion_unit' => 'g',
        'protein_g' => 20,
        'carbs_g' => 30,
        'fat_g' => 10,
    ])->assertSessionHasErrors('name');

    $this->assertDatabaseEmpty('meal_entries');
    $this->assertDatabaseEmpty('pending_analytics_events');
});

it('rejects an unknown food add method without recording a meal', function (): void {
    $product = FoodProduct::query()->create([
        'barcode' => '1234567890123',
        'name' => 'Yoghurt',
        'nutrition_unit' => 'g',
        'calories_per_100' => 120,
        'protein_per_100' => 8,
        'carbs_per_100' => 12,
        'fat_per_100' => 4,
    ]);

    $this->from('/add')->post('/meals/barcode', [
        'date' => '2026-09-25',
        'meal_type' => 'lunch',
        'food_product_id' => $product->id,
        'portion_quantity' => 100,
        'portion_unit' => 'g',
        'add_method' => 'unknown',
    ])->assertSessionHasErrors('add_method');

    $this->assertDatabaseEmpty('meal_entries');
    $this->assertDatabaseEmpty('pending_analytics_events');
});

it('records subscription plan views as separate events', function (): void {
    $this->get('/settings/subscription')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Settings/Subscription'));

    $this->get('/settings/subscription')->assertOk();

    expect(PendingAnalyticsEvent::query()->where('name', 'subscription_plans_viewed')->count())->toBe(2);
});
