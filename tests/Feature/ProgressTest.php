<?php

use App\Models\BodyMetric;
use App\Models\DailyGoal;
use Inertia\Testing\AssertableInertia as Assert;

it('creates and updates a body metric for a date', function (): void {
    $this->post('/progress/body-metrics', [
        'date' => '2026-05-19',
        'weight_kg' => 82.4,
        'body_fat_percent' => 18.5,
        'notes' => 'Morning weigh-in',
    ])->assertRedirect();

    $this->assertDatabaseHas('body_metrics', [
        'date' => '2026-05-19 00:00:00',
        'weight_kg' => 82.4,
        'body_fat_percent' => 18.5,
    ]);

    $this->post('/progress/body-metrics', [
        'date' => '2026-05-19',
        'weight_kg' => 82.0,
        'body_fat_percent' => 18.2,
    ])->assertRedirect();

    $this->assertDatabaseCount('body_metrics', 1);
    $this->assertDatabaseHas('body_metrics', [
        'date' => '2026-05-19 00:00:00',
        'weight_kg' => 82.0,
        'body_fat_percent' => 18.2,
    ]);
});

it('renders latest metric delta and history', function (): void {
    BodyMetric::query()->create([
        'date' => '2026-05-18',
        'weight_kg' => 83.0,
        'body_fat_percent' => 19.0,
    ]);

    BodyMetric::query()->create([
        'date' => '2026-05-19',
        'weight_kg' => 82.4,
        'body_fat_percent' => 18.5,
    ]);

    $this->get('/progress')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Progress')
            ->where('latest.weight_kg', 82.4)
            ->where('delta.weight_kg', -0.6)
            ->where('delta.body_fat_percent', -0.5)
            ->has('history', 2)
        );
});

it('passes body goals to progress', function (): void {
    DailyGoal::query()->create([
        'calories' => 2000,
        'protein_g' => 170,
        'carbs_g' => 195,
        'fat_g' => 60,
        'macro_calories' => 2000,
        'height_cm' => 178,
        'target_weight_kg' => 80,
        'target_body_fat_percent' => 15,
    ]);

    $this->get('/progress')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('goals.height_cm', 178)
            ->where('goals.target_weight_kg', 80)
            ->where('goals.target_body_fat_percent', 15)
        );
});

it('deletes a body metric', function (): void {
    $metric = BodyMetric::query()->create([
        'date' => '2026-05-19',
        'weight_kg' => 82.4,
    ]);

    $this->delete("/progress/body-metrics/{$metric->id}")
        ->assertRedirect();

    $this->assertDatabaseMissing('body_metrics', ['id' => $metric->id]);
});

it('updates height from progress', function (): void {
    $this->put('/progress/height', [
        'height_cm' => 178,
    ])->assertRedirect();

    $this->assertDatabaseHas('daily_goals', [
        'height_cm' => 178,
    ]);
});
