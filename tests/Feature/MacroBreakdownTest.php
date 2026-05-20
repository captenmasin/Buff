<?php

namespace Tests\Feature;

use App\Models\DailyGoal;
use App\Models\MealEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class MacroBreakdownTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_lists_foods_ordered_by_selected_macro_and_reports_macro_split(): void
    {
        DailyGoal::query()->create([
            'calories' => 2000,
            'protein_g' => 170,
            'carbs_g' => 195,
            'fat_g' => 60,
            'macro_calories' => 2000,
        ]);

        MealEntry::query()->create([
            'date' => '2026-05-20',
            'meal_type' => 'lunch',
            'source_type' => MealEntry::SOURCE_CUSTOM,
            'name' => 'Rice bowl',
            'calories' => 289,
            'protein_g' => 10,
            'carbs_g' => 60,
            'fat_g' => 1,
        ]);

        MealEntry::query()->create([
            'date' => '2026-05-20',
            'meal_type' => 'dinner',
            'source_type' => MealEntry::SOURCE_CUSTOM,
            'name' => 'Chicken',
            'calories' => 227,
            'protein_g' => 45,
            'carbs_g' => 5,
            'fat_g' => 3,
        ]);

        MealEntry::query()->create([
            'date' => '2026-05-20',
            'meal_type' => 'snacks',
            'source_type' => MealEntry::SOURCE_CUSTOM,
            'name' => 'Avocado',
            'calories' => 228,
            'protein_g' => 4,
            'carbs_g' => 12,
            'fat_g' => 20,
        ]);

        $this->get('/macros/protein?date=2026-05-20')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('MacroBreakdown')
                ->where('date', '2026-05-20')
                ->where('macro.slug', 'protein')
                ->where('macro.key', 'protein_g')
                ->where('macro.current_percentage', 31)
                ->where('macro.goal_percentage', 34)
                ->has('entries', 3)
                ->where('entries.0.name', 'Chicken')
                ->where('entries.0.protein_g', 45)
                ->where('entries.1.name', 'Rice bowl')
                ->where('entries.2.name', 'Avocado')
            );
    }

    public function test_it_rejects_unknown_macros(): void
    {
        $this->get('/macros/fiber?date=2026-05-20')
            ->assertNotFound();
    }
}
