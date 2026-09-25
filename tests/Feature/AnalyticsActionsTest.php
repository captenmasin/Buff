<?php

use App\Models\BodyMetric;
use App\Models\DailyGoal;
use App\Models\PendingAnalyticsEvent;
use App\Models\Recipe;
use App\Models\WorkoutEntry;
use App\Services\BuffCredentialStore;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    app(BuffCredentialStore::class)->store('analytics-token', ['id' => 'account-one']);
    Http::preventStrayRequests();
    Http::fake([
        '*/analytics/events' => Http::failedConnection(),
        '*/sync' => Http::failedConnection(),
    ]);
});

it('records onboarding completion once after a successful first save', function (): void {
    $payload = [
        'calories' => 2000,
        'protein_g' => 170,
        'carbs_g' => 195,
        'fat_g' => 60,
        'current_weight_kg' => 80,
        'weight_unit' => 'kg',
        'height_unit' => 'cm',
    ];

    $this->post('/onboarding', $payload)->assertRedirect('/');
    $this->post('/onboarding', $payload)->assertRedirect('/');

    $this->assertDatabaseCount('daily_goals', 1);
    expect(PendingAnalyticsEvent::query()->pluck('name')->all())->toBe(['onboarding_completed']);

    $this->post('/onboarding', [...$payload, 'current_weight_kg' => 19])
        ->assertSessionHasErrors('current_weight_kg');

    expect(PendingAnalyticsEvent::query()->pluck('name')->all())->toBe(['onboarding_completed']);
});

it('records saved daily goals only after valid persistence', function (): void {
    $payload = [
        'calories' => 2000,
        'protein_g' => 170,
        'carbs_g' => 195,
        'fat_g' => 60,
        'target_weight_kg' => null,
        'target_body_fat_percent' => null,
    ];

    $this->put('/goals', $payload)->assertRedirect('/goals');
    $this->put('/goals', [...$payload, 'calories' => 1900])->assertSessionHasErrors('calories');

    expect(DailyGoal::query()->sole()->calories)->toBe(2000);
    expect(PendingAnalyticsEvent::query()->pluck('name')->all())->toBe(['daily_goals_saved']);
});

it('records workout edits and deletion without counting a rejected edit', function (): void {
    $this->post('/workouts', [
        'date' => '2026-05-19',
        'title' => 'Run',
        'calories_burned' => 200,
        'time' => '08:00',
    ])->assertRedirect('/?date=2026-05-19');
    $workout = WorkoutEntry::query()->sole();

    $this->put("/workouts/{$workout->id}", [
        'date' => '2026-05-19',
        'title' => 'Long run',
        'calories_burned' => 250,
        'time' => '08:00',
    ])->assertRedirect('/?date=2026-05-19');
    $this->put("/workouts/{$workout->id}", ['title' => 'Invalid'])
        ->assertSessionHasErrors(['date', 'calories_burned', 'time']);
    $this->delete("/workouts/{$workout->id}")->assertRedirect('/?date=2026-05-19');

    $this->assertDatabaseEmpty('workout_entries');
    expect(PendingAnalyticsEvent::query()->pluck('name')->all())
        ->toBe(['workout_logged', 'workout_updated', 'workout_deleted']);
});

it('records new, edited, and deleted progress entries once each', function (): void {
    $this->post('/progress/body-metrics', [
        'date' => '2026-05-19',
        'weight_kg' => 80,
    ])->assertRedirect('/progress?range=30');
    $metric = BodyMetric::query()->sole();

    $this->post('/progress/body-metrics', [
        'date' => '2026-05-19',
        'weight_kg' => 79.5,
    ])->assertRedirect('/progress?range=30');
    $this->post('/progress/body-metrics', [
        'date' => '2026-05-19',
        'weight_kg' => 19,
    ])->assertSessionHasErrors('weight_kg');
    $this->delete("/progress/body-metrics/{$metric->id}")
        ->assertRedirect('/progress?range=30');

    $this->assertDatabaseEmpty('body_metrics');
    expect(PendingAnalyticsEvent::query()->pluck('name')->all())
        ->toBe(['progress_logged', 'progress_updated', 'progress_deleted']);
});

it('records recipe saves, edits, and deletion after persistence', function (): void {
    $payload = [
        'date' => '2026-05-19',
        'name' => 'Oats',
        'servings' => 1,
        'items' => [[
            'name' => 'Oats',
            'portion_quantity' => 80,
            'portion_unit' => 'g',
            'calories' => 300,
            'protein_g' => 10,
            'carbs_g' => 50,
            'fat_g' => 5,
        ]],
    ];

    $this->post('/recipes', $payload)->assertRedirect('/add?mode=recipe&date=2026-05-19');
    $recipe = Recipe::query()->sole();
    $this->put("/recipes/{$recipe->id}", [...$payload, 'name' => 'Overnight oats'])
        ->assertRedirect();
    $this->put("/recipes/{$recipe->id}", ['name' => 'Invalid'])
        ->assertSessionHasErrors(['servings', 'items']);
    $this->delete("/recipes/{$recipe->id}")->assertRedirect();

    $this->assertDatabaseEmpty('recipes');
    expect(PendingAnalyticsEvent::query()->pluck('name')->all())
        ->toBe(['recipe_saved', 'recipe_updated', 'recipe_deleted']);
});
