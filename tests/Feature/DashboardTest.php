<?php

use App\Models\AppPreference;
use App\Models\DailyGoal;
use App\Models\MealEntry;
use App\Models\WorkoutEntry;
use Inertia\Testing\AssertableInertia as Assert;

it('reports daily calorie and macro remaining totals', function (): void {
    DailyGoal::query()->create([
        'calories' => 2000,
        'protein_g' => 170,
        'carbs_g' => 195,
        'fat_g' => 60,
        'macro_calories' => 2000,
    ]);

    WorkoutEntry::query()->create([
        'date' => '2026-05-19',
        'title' => 'Bike ride',
        'calories_burned' => 300,
        'logged_at' => '2026-05-19 18:30:00',
    ]);

    MealEntry::query()->create([
        'date' => '2026-05-19',
        'meal_type' => 'breakfast',
        'source_type' => MealEntry::SOURCE_CUSTOM,
        'name' => 'Breakfast bowl',
        'calories' => 500,
        'protein_g' => 40,
        'carbs_g' => 60,
        'fat_g' => 10,
    ]);

    $this->get('/?date=2026-05-19')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Today')
            ->where('summary.goal.calories', 2300)
            ->where('summary.goal.protein_g', 195.5)
            ->where('summary.goal.carbs_g', 224.25)
            ->where('summary.goal.fat_g', 69)
            ->where('summary.totals.calories', 500)
            ->where('summary.totals.calories_remaining', 1800)
            ->where('summary.totals.protein_remaining', 155.5)
            ->where('summary.totals.carbs_remaining', 164.25)
            ->where('summary.totals.fat_remaining', 59)
            ->where('summary.log.burned_calories', 300)
            ->has('summary.entries.breakfast', 1)
            ->has('summary.workouts', 1)
            ->where('summary.workouts.0.title', 'Bike ride')
            ->where('summary.workouts.0.logged_time', '18:30')
        );
});

it('uses eat-back none for remaining calories while keeping burned', function (): void {
    AppPreference::current()->update(['eat_back' => 'none']);

    DailyGoal::query()->create([
        'calories' => 2000,
        'protein_g' => 170,
        'carbs_g' => 195,
        'fat_g' => 60,
        'macro_calories' => 2000,
    ]);

    WorkoutEntry::query()->create([
        'date' => '2026-05-19',
        'title' => 'Bike ride',
        'calories_burned' => 300,
        'logged_at' => '2026-05-19 18:30:00',
    ]);

    MealEntry::query()->create([
        'date' => '2026-05-19',
        'meal_type' => 'breakfast',
        'source_type' => MealEntry::SOURCE_CUSTOM,
        'name' => 'Breakfast bowl',
        'calories' => 500,
        'protein_g' => 40,
        'carbs_g' => 60,
        'fat_g' => 10,
    ]);

    $this->get('/?date=2026-05-19')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('summary.goal.calories', 2000)
            ->where('summary.totals.calories_remaining', 1500)
            ->where('summary.log.burned_calories', 300)
        );
});

it('uses eat-back half for remaining calories', function (): void {
    AppPreference::current()->update(['eat_back' => 'half']);

    DailyGoal::query()->create([
        'calories' => 2000,
        'protein_g' => 170,
        'carbs_g' => 195,
        'fat_g' => 60,
        'macro_calories' => 2000,
    ]);

    WorkoutEntry::query()->create([
        'date' => '2026-05-19',
        'title' => 'Bike ride',
        'calories_burned' => 300,
        'logged_at' => '2026-05-19 18:30:00',
    ]);

    MealEntry::query()->create([
        'date' => '2026-05-19',
        'meal_type' => 'breakfast',
        'source_type' => MealEntry::SOURCE_CUSTOM,
        'name' => 'Breakfast bowl',
        'calories' => 500,
        'protein_g' => 40,
        'carbs_g' => 60,
        'fat_g' => 10,
    ]);

    $this->get('/?date=2026-05-19')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('summary.goal.calories', 2150)
            ->where('summary.totals.calories_remaining', 1650)
        );
});

it('redirects new users to onboarding until goals exist', function (): void {
    $this->get('/')
        ->assertRedirect('/onboarding');

    $this->get('/?skip_onboarding=1')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Today')
            ->where('summary.goal', null)
        );
});

it('returns only populated meal groups', function (): void {
    DailyGoal::query()->create([
        'calories' => 2000,
        'protein_g' => 170,
        'carbs_g' => 195,
        'fat_g' => 60,
        'macro_calories' => 2000,
    ]);

    MealEntry::query()->create([
        'date' => '2026-05-19',
        'meal_type' => 'dinner',
        'source_type' => MealEntry::SOURCE_CUSTOM,
        'name' => 'Dinner',
        'calories' => 500,
        'protein_g' => 30,
        'carbs_g' => 50,
        'fat_g' => 15,
    ]);

    $this->get('/?date=2026-05-19')
        ->assertInertia(fn (Assert $page) => $page
            ->has('summary.entries.dinner', 1)
            ->missing('summary.entries.breakfast')
            ->has('summary.workouts', 0)
        );
});

it('returns an empty goal-backed summary without meal or workout groups', function (): void {
    DailyGoal::query()->create([
        'calories' => 2000,
        'protein_g' => 170,
        'carbs_g' => 195,
        'fat_g' => 60,
        'macro_calories' => 2000,
    ]);

    $this->get('/?date=2026-05-19')
        ->assertInertia(fn (Assert $page) => $page
            ->has('summary.entries', 0)
            ->has('summary.workouts', 0)
        );
});

it('redirects legacy add shortcuts to the canonical add page', function (): void {
    DailyGoal::query()->create([
        'calories' => 2000,
        'protein_g' => 170,
        'carbs_g' => 195,
        'fat_g' => 60,
        'macro_calories' => 2000,
    ]);

    $this->get('/?add=1')->assertRedirect('/add');
    $this->get('/?add=1&date=2026-05-19')->assertRedirect('/add?date=2026-05-19');
});

it('reports monday first week statuses with burned calorie offset', function (): void {
    DailyGoal::query()->create([
        'calories' => 2000,
        'protein_g' => 170,
        'carbs_g' => 195,
        'fat_g' => 60,
        'macro_calories' => 2000,
    ]);

    WorkoutEntry::query()->create([
        'date' => '2026-05-19',
        'title' => 'Bike ride',
        'calories_burned' => 300,
        'logged_at' => '2026-05-19 18:30:00',
    ]);

    MealEntry::query()->create([
        'date' => '2026-05-18',
        'meal_type' => 'breakfast',
        'source_type' => MealEntry::SOURCE_CUSTOM,
        'name' => 'Under day',
        'calories' => 1800,
        'protein_g' => 1,
        'carbs_g' => 1,
        'fat_g' => 1,
    ]);

    MealEntry::query()->create([
        'date' => '2026-05-19',
        'meal_type' => 'breakfast',
        'source_type' => MealEntry::SOURCE_CUSTOM,
        'name' => 'Target day',
        'calories' => 2300,
        'protein_g' => 1,
        'carbs_g' => 1,
        'fat_g' => 1,
    ]);

    MealEntry::query()->create([
        'date' => '2026-05-20',
        'meal_type' => 'breakfast',
        'source_type' => MealEntry::SOURCE_CUSTOM,
        'name' => 'Over day',
        'calories' => 2100,
        'protein_g' => 1,
        'carbs_g' => 1,
        'fat_g' => 1,
    ]);

    $this->get('/?date=2026-05-19')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Today')
            ->has('week', 7)
            ->where('week.0.date', '2026-05-18')
            ->where('week.0.status', 'under')
            ->where('week.1.date', '2026-05-19')
            ->where('week.1.status', 'target')
            ->where('week.1.effective_target', 2300)
            ->where('week.1.protein_g', 1)
            ->where('week.1.is_selected', true)
            ->where('week.2.status', 'over')
            ->where('week.3.status', 'neutral')
            ->missing('weekRoundup')
        );
});
