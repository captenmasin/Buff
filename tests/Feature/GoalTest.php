<?php

use App\Models\DailyGoal;
use Inertia\Testing\AssertableInertia as Assert;

it('renders default calorie and macro targets when none are saved', function (): void {
    $this->get('/goals')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Goals')
            ->where('goal.calories', 2000)
            ->where('goal.protein_g', 170)
            ->where('goal.carbs_g', 195)
            ->where('goal.fat_g', 60)
            ->where('goal.target_weight_kg', null)
            ->where('goal.target_body_fat_percent', null)
            ->where('preferences.weight_unit', 'kg')
        );
});

it('renders the goals editor with the saved daily target', function (): void {
    DailyGoal::query()->create([
        'calories' => 2100,
        'protein_g' => 157.5,
        'carbs_g' => 210,
        'fat_g' => 70,
        'macro_calories' => 2100,
        'target_weight_kg' => 80,
        'target_body_fat_percent' => 15,
    ]);

    $this->get('/goals')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Goals')
            ->where('goal.calories', 2100)
            ->where('goal.protein_g', 157.5)
            ->where('goal.carbs_g', 210)
            ->where('goal.fat_g', 70)
            ->where('goal.target_weight_kg', 80)
            ->where('goal.target_body_fat_percent', 15)
        );
});

it('saves a goal when macro calories match', function (): void {
    $this->put('/goals', [
        'calories' => 2000,
        'protein_g' => 170,
        'carbs_g' => 195,
        'fat_g' => 60,
        'target_weight_kg' => 82,
        'target_body_fat_percent' => 15,
    ])->assertRedirect('/goals');

    $goal = DailyGoal::query()->first();

    expect($goal->macro_calories)->toBe(2000)
        ->and((float) $goal->target_weight_kg)->toBe(82.0)
        ->and((float) $goal->target_body_fat_percent)->toBe(15.0);
});

it('updates the existing goal', function (): void {
    DailyGoal::query()->create([
        'calories' => 1800,
        'protein_g' => 150,
        'carbs_g' => 165,
        'fat_g' => 40,
        'macro_calories' => 1620,
        'target_weight_kg' => 80,
    ]);

    $this->put('/goals', [
        'calories' => 2000,
        'protein_g' => 170,
        'carbs_g' => 195,
        'fat_g' => 60,
        'target_weight_kg' => 78,
        'target_body_fat_percent' => '',
    ])->assertRedirect('/goals');

    $this->assertDatabaseCount('daily_goals', 1);

    $goal = DailyGoal::query()->first();

    expect($goal->calories)->toBe(2000)
        ->and((float) $goal->target_weight_kg)->toBe(78.0)
        ->and($goal->target_body_fat_percent)->toBeNull();
});

it('rejects a goal when macro calories do not match', function (): void {
    $this->from('/goals')->put('/goals', [
        'calories' => 1900,
        'protein_g' => 170,
        'carbs_g' => 195,
        'fat_g' => 60,
        'target_weight_kg' => '',
        'target_body_fat_percent' => '',
    ])->assertRedirect('/goals')
        ->assertSessionHasErrors('calories');

    $this->assertDatabaseCount('daily_goals', 0);
});

it('saves preset and odd-calorie custom macro payloads', function (): void {
    $this->put('/goals', [
        'calories' => 2000,
        'protein_g' => 150,
        'carbs_g' => 200,
        'fat_g' => 66.67,
        'target_weight_kg' => '',
        'target_body_fat_percent' => '',
    ])->assertRedirect('/goals');

    $this->put('/goals', [
        'calories' => 1999,
        'protein_g' => 174.91,
        'carbs_g' => 224.89,
        'fat_g' => 44.42,
        'target_weight_kg' => '',
        'target_body_fat_percent' => '',
    ])->assertRedirect('/goals');

    $this->assertDatabaseCount('daily_goals', 1);
    $this->assertDatabaseHas('daily_goals', ['calories' => 1999]);
});

it('requires body target fields and bounds them', function (): void {
    $payload = [
        'calories' => 2000,
        'protein_g' => 170,
        'carbs_g' => 195,
        'fat_g' => 60,
        'target_weight_kg' => 82,
        'target_body_fat_percent' => 15,
    ];

    foreach (['target_weight_kg', 'target_body_fat_percent'] as $field) {
        $missing = $payload;
        unset($missing[$field]);
        $this->put('/goals', $missing)->assertSessionHasErrors($field);
    }

    $this->put('/goals', [
        ...$payload,
        'target_weight_kg' => 0,
        'target_body_fat_percent' => 81,
    ])->assertSessionHasErrors(['target_weight_kg', 'target_body_fat_percent']);
});
