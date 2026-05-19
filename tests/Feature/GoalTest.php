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
            'starts_on' => '2026-05-19',
            'calories' => 2000,
            'protein_g' => 170,
            'carbs_g' => 195,
            'fat_g' => 60,
        ])->assertRedirect('/');

        $goal = DailyGoal::query()->first();

        $this->assertSame(2000, $goal->macro_calories);
    }

    public function test_it_rejects_a_goal_when_macro_calories_do_not_match(): void
    {
        $this->from('/goals')->put('/goals', [
            'starts_on' => '2026-05-19',
            'calories' => 1900,
            'protein_g' => 170,
            'carbs_g' => 195,
            'fat_g' => 60,
        ])->assertRedirect('/goals')
            ->assertSessionHasErrors('calories');

        $this->assertDatabaseCount('daily_goals', 0);
    }
}
