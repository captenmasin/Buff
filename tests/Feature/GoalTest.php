<?php

use App\Models\DailyGoal;

it('saves a goal when macro calories match', function (): void {
    $this->put('/goals', [
        'calories' => 2000,
        'protein_g' => 170,
        'carbs_g' => 195,
        'fat_g' => 60,
        'target_weight_kg' => 80,
        'target_body_fat_percent' => 15,
    ])->assertRedirect('/');

    $goal = DailyGoal::query()->first();

    expect($goal->macro_calories)->toBe(2000)
        ->and((float) $goal->target_weight_kg)->toBe(80.0)
        ->and((float) $goal->target_body_fat_percent)->toBe(15.0);
});

it('updates the existing goal', function (): void {
    DailyGoal::query()->create([
        'calories' => 1800,
        'protein_g' => 150,
        'carbs_g' => 165,
        'fat_g' => 40,
        'macro_calories' => 1620,
    ]);

    $this->put('/goals', [
        'calories' => 2000,
        'protein_g' => 170,
        'carbs_g' => 195,
        'fat_g' => 60,
    ])->assertRedirect('/');

    $this->assertDatabaseCount('daily_goals', 1);

    expect(DailyGoal::query()->first()->calories)->toBe(2000);
});

it('rejects a goal when macro calories do not match', function (): void {
    $this->from('/goals')->put('/goals', [
        'calories' => 1900,
        'protein_g' => 170,
        'carbs_g' => 195,
        'fat_g' => 60,
    ])->assertRedirect('/goals')
        ->assertSessionHasErrors('calories');

    $this->assertDatabaseCount('daily_goals', 0);
});
