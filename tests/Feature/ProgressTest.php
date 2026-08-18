<?php

use App\Models\AppPreference;
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
    AppPreference::current()->update([
        'weight_unit' => 'lb',
        'height_unit' => 'in',
    ]);

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
            ->where('preferences.weight_unit', 'lb')
            ->where('preferences.height_unit', 'in')
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

it('updates the body profile from progress', function (): void {
    $this->put('/progress/body-profile', [
        'height_cm' => 178,
        'target_weight_kg' => 82,
        'target_body_fat_percent' => 15,
    ])->assertRedirect();

    $this->assertDatabaseHas('daily_goals', [
        'height_cm' => 178,
        'target_weight_kg' => 82,
        'target_body_fat_percent' => 15,
    ]);
});

it('updates only the latest goal body profile and preserves nutrition targets', function (): void {
    $older = DailyGoal::query()->create([
        'calories' => 1800, 'protein_g' => 120, 'carbs_g' => 180, 'fat_g' => 62, 'macro_calories' => 1800,
    ]);
    $latest = DailyGoal::query()->create([
        'calories' => 2200, 'protein_g' => 180, 'carbs_g' => 220, 'fat_g' => 68.89, 'macro_calories' => 2200,
    ]);
    $older->forceFill(['updated_at' => now()->subDay()])->save();

    $this->put('/progress/body-profile', [
        'height_cm' => 178, 'target_weight_kg' => 82, 'target_body_fat_percent' => 15,
    ])->assertRedirect();

    expect($older->fresh()->height_cm)->toBeNull()
        ->and($latest->fresh()->height_cm)->toBe('178.00')
        ->and($latest->fresh()->calories)->toBe(2200)
        ->and((float) $latest->fresh()->protein_g)->toBe(180.0);
});

it('allows clearing every body profile value', function (): void {
    DailyGoal::query()->create([
        'calories' => 2000, 'protein_g' => 170, 'carbs_g' => 195, 'fat_g' => 60, 'macro_calories' => 2000,
        'height_cm' => 178, 'target_weight_kg' => 80, 'target_body_fat_percent' => 15,
    ]);

    $this->put('/progress/body-profile', [
        'height_cm' => '', 'target_weight_kg' => '', 'target_body_fat_percent' => '',
    ])->assertRedirect();

    $this->assertDatabaseHas('daily_goals', [
        'height_cm' => null, 'target_weight_kg' => null, 'target_body_fat_percent' => null,
    ]);
});

it('requires and bounds each body profile field', function (): void {
    foreach (['height_cm', 'target_weight_kg', 'target_body_fat_percent'] as $field) {
        $payload = ['height_cm' => 178, 'target_weight_kg' => 82, 'target_body_fat_percent' => 15];
        unset($payload[$field]);
        $this->put('/progress/body-profile', $payload)->assertSessionHasErrors($field);
    }

    $this->put('/progress/body-profile', [
        'height_cm' => 49, 'target_weight_kg' => 0, 'target_body_fat_percent' => 81,
    ])->assertSessionHasErrors(['height_cm', 'target_weight_kg', 'target_body_fat_percent']);
});

it('removes the old body profile endpoints', function (): void {
    $this->put('/settings/body-targets')->assertNotFound();
    $this->put('/settings/height')->assertNotFound();
    $this->put('/progress/height')->assertNotFound();
});
