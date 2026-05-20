<?php

use App\Models\DailyGoal;
use App\Models\MealEntry;
use App\Models\WorkoutEntry;
use Inertia\Testing\AssertableInertia as Assert;

it('lists foods ordered by selected macro and reports macro split', function (): void {
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
});

it('scales the macro goal when workouts increase the calorie target', function (): void {
    DailyGoal::query()->create([
        'calories' => 2000,
        'protein_g' => 170,
        'carbs_g' => 195,
        'fat_g' => 60,
        'macro_calories' => 2000,
    ]);

    WorkoutEntry::query()->create([
        'date' => '2026-05-20',
        'title' => 'Bike ride',
        'calories_burned' => 300,
        'logged_at' => '2026-05-20 18:30:00',
    ]);

    $this->get('/macros/protein?date=2026-05-20')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('MacroBreakdown')
            ->where('macro.goal_g', 195.5)
            ->where('macro.goal_percentage', 34)
        );
});

it('rejects unknown macros', function (): void {
    $this->get('/macros/fiber?date=2026-05-20')
        ->assertNotFound();
});
