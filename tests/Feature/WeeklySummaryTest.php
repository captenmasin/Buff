<?php

use App\Models\AppPreference;
use App\Models\BodyMetric;
use App\Models\DailyGoal;
use App\Models\MealEntry;
use App\Models\WorkoutEntry;
use Inertia\Testing\AssertableInertia as Assert;

it('renders the weekly calorie and macro roundup on its own page', function (): void {
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

    $this->get('/weekly?date=2026-05-19')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Weekly')
            ->where('mode', 'week')
            ->where('selectedDate', '2026-05-19')
            ->where('controls.date', '2026-05-19')
            ->where('controls.start_date', '2026-05-18')
            ->where('controls.end_date', '2026-05-24')
            ->where('roundup.start_date', '2026-05-18')
            ->where('roundup.end_date', '2026-05-24')
            ->where('roundup.calories', 6200)
            ->where('roundup.burned_calories', 300)
            ->where('roundup.effective_target', 14300)
            ->where('roundup.protein_g', 3)
            ->where('roundup.protein_goal_g', 1190)
            ->has('week', 7)
            ->where('week.1.date', '2026-05-19')
            ->where('week.1.status', 'target')
            ->has('insights', 2)
            ->where('insights.0.id', 'adherence')
            ->where('insights.0.text', '1 of 3 logged days on target')
            ->where('insights.1.id', 'average')
            ->where('insights.1.text', 'Averaged 2,067 kcal on logged days vs 2,100 target')
        );
});

it('renders a custom date range roundup', function (): void {
    DailyGoal::query()->create([
        'calories' => 2000,
        'protein_g' => 170,
        'carbs_g' => 195,
        'fat_g' => 60,
        'macro_calories' => 2000,
    ]);

    MealEntry::query()->create([
        'date' => '2026-05-19',
        'meal_type' => 'breakfast',
        'source_type' => MealEntry::SOURCE_CUSTOM,
        'name' => 'Range day one',
        'calories' => 500,
        'protein_g' => 20,
        'carbs_g' => 50,
        'fat_g' => 10,
    ]);

    MealEntry::query()->create([
        'date' => '2026-05-20',
        'meal_type' => 'lunch',
        'source_type' => MealEntry::SOURCE_CUSTOM,
        'name' => 'Range day two',
        'calories' => 700,
        'protein_g' => 30,
        'carbs_g' => 60,
        'fat_g' => 20,
    ]);

    MealEntry::query()->create([
        'date' => '2026-05-21',
        'meal_type' => 'dinner',
        'source_type' => MealEntry::SOURCE_CUSTOM,
        'name' => 'Outside range',
        'calories' => 900,
        'protein_g' => 40,
        'carbs_g' => 70,
        'fat_g' => 30,
    ]);

    $this->get('/weekly?start_date=2026-05-19&end_date=2026-05-20')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Weekly')
            ->where('mode', 'range')
            ->where('controls.start_date', '2026-05-19')
            ->where('controls.end_date', '2026-05-20')
            ->where('roundup.calories', 1200)
            ->where('roundup.protein_g', 50)
            ->where('roundup.protein_goal_g', 340)
            ->has('week', 2)
            ->where('week.0.date', '2026-05-19')
            ->where('week.1.date', '2026-05-20')
        );
});

it('limits custom roundups to 90 inclusive calendar dates', function (): void {
    $this->from('/weekly')
        ->get('/weekly?start_date=2026-01-01&end_date=2026-04-01')
        ->assertRedirect('/weekly')
        ->assertSessionHasErrors('end_date');
});

it('uses eat-back none for the weekly effective target', function (): void {
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

    $this->get('/weekly?date=2026-05-19')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('roundup.effective_target', 14000)
        );
});

it('compares weekend and weekday logged averages', function (): void {
    DailyGoal::query()->create([
        'calories' => 2000,
        'protein_g' => 170,
        'carbs_g' => 195,
        'fat_g' => 60,
        'macro_calories' => 2000,
    ]);

    MealEntry::query()->create([
        'date' => '2026-05-18',
        'meal_type' => 'breakfast',
        'source_type' => MealEntry::SOURCE_CUSTOM,
        'name' => 'Monday',
        'calories' => 1800,
        'protein_g' => 1,
        'carbs_g' => 1,
        'fat_g' => 1,
    ]);

    MealEntry::query()->create([
        'date' => '2026-05-23',
        'meal_type' => 'dinner',
        'source_type' => MealEntry::SOURCE_CUSTOM,
        'name' => 'Saturday',
        'calories' => 2500,
        'protein_g' => 1,
        'carbs_g' => 1,
        'fat_g' => 1,
    ]);

    $this->get('/weekly?date=2026-05-19')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('insights.2.id', 'weekend')
            ->where('insights.2.text', 'Weekends averaged 700 kcal more than weekdays')
        );
});

it('reports weight change inside the selected period', function (): void {
    DailyGoal::query()->create([
        'calories' => 2000,
        'protein_g' => 170,
        'carbs_g' => 195,
        'fat_g' => 60,
        'macro_calories' => 2000,
    ]);

    BodyMetric::query()->create([
        'date' => '2026-05-18',
        'weight_kg' => 82.4,
    ]);

    BodyMetric::query()->create([
        'date' => '2026-05-24',
        'weight_kg' => 82.0,
    ]);

    BodyMetric::query()->create([
        'date' => '2026-05-10',
        'weight_kg' => 84.0,
    ]);

    $this->get('/weekly?date=2026-05-19')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('insights.0.id', 'weight')
            ->where('insights.0.text', 'Weight down 0.4 kg this period')
        );
});

it('omits the weight insight when only one weigh-in is in range', function (): void {
    BodyMetric::query()->create([
        'date' => '2026-05-19',
        'weight_kg' => 82.4,
    ]);

    $this->get('/weekly?date=2026-05-19')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('insights', 0)
        );
});

it('changes adherence when eat-back is none', function (): void {
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

    $this->get('/weekly?date=2026-05-19')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('week.1.status', 'over')
            ->where('insights.0.text', '0 of 3 logged days on target')
        );
});
