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
        );
});

it('renders the goals editor with the saved daily target', function (): void {
    DailyGoal::query()->create([
        'calories' => 2100,
        'protein_g' => 157.5,
        'carbs_g' => 210,
        'fat_g' => 70,
        'macro_calories' => 2100,
    ]);

    $this->get('/goals')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Goals')
            ->where('goal.calories', 2100)
            ->where('goal.protein_g', 157.5)
            ->where('goal.carbs_g', 210)
            ->where('goal.fat_g', 70)
        );
});

it('saves a goal when macro calories match', function (): void {
    $this->put('/goals', [
        'calories' => 2000,
        'protein_g' => 170,
        'carbs_g' => 195,
        'fat_g' => 60,
    ])->assertRedirect('/goals');

    $goal = DailyGoal::query()->first();

    expect($goal->macro_calories)->toBe(2000);
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
    ])->assertRedirect('/goals');

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

it('saves preset and odd-calorie custom macro payloads', function (): void {
    $this->put('/goals', [
        'calories' => 2000,
        'protein_g' => 150,
        'carbs_g' => 200,
        'fat_g' => 66.67,
    ])->assertRedirect('/goals');

    $this->put('/goals', [
        'calories' => 1999,
        'protein_g' => 174.91,
        'carbs_g' => 224.89,
        'fat_g' => 44.42,
    ])->assertRedirect('/goals');

    $this->assertDatabaseCount('daily_goals', 1);
    $this->assertDatabaseHas('daily_goals', ['calories' => 1999]);
});
