<?php

use App\Models\DailyGoal;
use Inertia\Testing\AssertableInertia as Assert;

it('renders body and height settings', function (): void {
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

    $this->get('/settings')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Settings')
            ->where('settings.height_cm', 178)
            ->where('settings.target_weight_kg', 80)
            ->where('settings.target_body_fat_percent', 15)
        );
});

it('updates body targets from settings', function (): void {
    $this->put('/settings/body-targets', [
        'target_weight_kg' => 82,
        'target_body_fat_percent' => 14.5,
    ])->assertRedirect();

    $this->assertDatabaseHas('daily_goals', [
        'target_weight_kg' => 82,
        'target_body_fat_percent' => 14.5,
    ]);
});

it('updates height from settings', function (): void {
    $this->put('/settings/height', [
        'height_cm' => 178,
    ])->assertRedirect();

    $this->assertDatabaseHas('daily_goals', [
        'height_cm' => 178,
    ]);
});
