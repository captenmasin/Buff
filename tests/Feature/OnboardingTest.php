<?php

use App\Models\DailyGoal;
use Inertia\Testing\AssertableInertia as Assert;

it('renders onboarding for a new local user', function (): void {
    $this->get('/onboarding')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Onboarding')
            ->where('defaults.calories', 2000)
            ->where('defaults.weight_unit', 'kg')
        );
});

it('stores the initial profile and preferences', function (): void {
    $this->post('/onboarding', [
        'calories' => 2000,
        'protein_g' => 150,
        'carbs_g' => 200,
        'fat_g' => 66.6667,
        'height_cm' => 180,
        'target_weight_kg' => 82,
        'target_body_fat_percent' => 15,
        'weight_unit' => 'lb',
        'height_unit' => 'in',
    ])->assertRedirect('/');

    $this->assertDatabaseHas('daily_goals', [
        'calories' => 2000,
        'height_cm' => 180,
        'target_weight_kg' => 82,
    ]);

    $this->assertDatabaseHas('app_preferences', [
        'weight_unit' => 'lb',
        'height_unit' => 'in',
    ]);
});

it('redirects onboarding away once goals exist', function (): void {
    DailyGoal::query()->create([
        'calories' => 2000,
        'protein_g' => 170,
        'carbs_g' => 195,
        'fat_g' => 60,
        'macro_calories' => 2000,
    ]);

    $this->get('/onboarding')
        ->assertRedirect('/');
});
