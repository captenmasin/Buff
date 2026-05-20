<?php

namespace Tests\Feature;

use App\Models\DailyGoal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GoalTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_saves_a_goal_when_macro_calories_match(): void
    {
        $this->put('/goals', [
            'calories' => 2000,
            'protein_g' => 170,
            'carbs_g' => 195,
            'fat_g' => 60,
            'target_weight_kg' => 80,
            'target_body_fat_percent' => 15,
        ])->assertRedirect('/');

        $goal = DailyGoal::query()->first();

        $this->assertSame(2000, $goal->macro_calories);
        $this->assertSame(80.0, (float) $goal->target_weight_kg);
        $this->assertSame(15.0, (float) $goal->target_body_fat_percent);
    }

    public function test_it_updates_the_existing_goal(): void
    {
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
        $this->assertSame(2000, DailyGoal::query()->first()->calories);
    }

    public function test_it_rejects_a_goal_when_macro_calories_do_not_match(): void
    {
        $this->from('/goals')->put('/goals', [
            'calories' => 1900,
            'protein_g' => 170,
            'carbs_g' => 195,
            'fat_g' => 60,
        ])->assertRedirect('/goals')
            ->assertSessionHasErrors('calories');

        $this->assertDatabaseCount('daily_goals', 0);
    }
}
