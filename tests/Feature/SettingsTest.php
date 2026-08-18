<?php

use App\Models\AppPreference;
use App\Models\DailyGoal;
use App\Models\MealEntry;
use App\Services\HealthConnectBridge;
use App\Services\MealReminderBridge;
use Inertia\Testing\AssertableInertia as Assert;

it('renders unit and reminder settings without body profile data', function (): void {
    app()->instance(HealthConnectBridge::class, new HealthConnectBridge(
        androidDetector: fn (): bool => false,
    ));

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
            ->missing('settings')
            ->where('preferences.weight_unit', 'kg')
            ->where('preferences.height_unit', 'cm')
            ->where('mealReminders.breakfast.enabled', false)
            ->where('mealReminders.breakfast.time', '08:00')
            ->where('mealReminders.lunch.enabled', false)
            ->where('mealReminders.lunch.time', '12:00')
            ->where('mealReminders.dinner.enabled', false)
            ->where('mealReminders.dinner.time', '18:00')
            ->where('healthConnect.is_android', false)
            ->where('healthConnect.supported', false)
        );
});

it('updates unit preferences from settings', function (): void {
    AppPreference::current();

    $this->put('/settings/units', [
        'weight_unit' => 'lb',
        'height_unit' => 'in',
    ])->assertRedirect();

    $this->assertDatabaseHas('app_preferences', [
        'weight_unit' => 'lb',
        'height_unit' => 'in',
    ]);
});

it('updates and schedules meal reminders', function (): void {
    $calls = [];
    app()->instance(MealReminderBridge::class, new MealReminderBridge(
        function (string $method, string $payload) use (&$calls): string {
            $calls[] = [$method, json_decode($payload, true, 512, JSON_THROW_ON_ERROR)];

            return json_encode(['status' => 'scheduled'], JSON_THROW_ON_ERROR);
        },
    ));

    $reminders = [
        'breakfast' => ['enabled' => true, 'time' => '07:30'],
        'lunch' => ['enabled' => false, 'time' => '12:15'],
        'dinner' => ['enabled' => true, 'time' => '19:00'],
    ];

    $this->put('/settings/meal-reminders', $reminders)
        ->assertRedirect()
        ->assertSessionHas('message', 'Meal reminders saved.');

    expect(AppPreference::current()->fresh()->mealReminders())->toBe($reminders)
        ->and($calls)->toBe([[
            'BackgroundTasks.RegisterMealReminders',
            ['reminders' => $reminders],
        ]]);
});

it('validates every meal reminder setting', function (): void {
    $this->put('/settings/meal-reminders', [
        'breakfast' => ['enabled' => 'yes', 'time' => '25:00'],
        'lunch' => ['enabled' => 'yes', 'time' => '25:00'],
        'dinner' => ['enabled' => 'yes', 'time' => '25:00'],
    ])->assertSessionHasErrors([
        'breakfast.enabled',
        'breakfast.time',
        'lunch.enabled',
        'lunch.time',
        'dinner.enabled',
        'dinner.time',
    ]);
});

it('normalizes malformed imported meal reminders', function (): void {
    AppPreference::current()->update(['meal_reminders' => [
        'breakfast' => 'invalid',
        'lunch' => ['enabled' => 1, 'time' => '25:00'],
        'dinner' => ['enabled' => true, 'time' => '19:30'],
    ]]);

    expect(AppPreference::current()->mealReminders())->toBe([
        'breakfast' => ['enabled' => false, 'time' => '08:00'],
        'lunch' => ['enabled' => false, 'time' => '12:00'],
        'dinner' => ['enabled' => true, 'time' => '19:30'],
    ]);
});

it('only marks a meal reminder due when that meal is not logged', function (): void {
    $date = today();

    MealEntry::query()->create([
        'date' => $date->copy()->subDay(),
        'meal_type' => 'breakfast',
        'source_type' => MealEntry::SOURCE_CUSTOM,
        'name' => 'Yesterday oats',
        'calories' => 300,
        'protein_g' => 10,
        'carbs_g' => 50,
        'fat_g' => 5,
    ]);

    $this->artisan('meal-reminder:check', ['meal' => 'breakfast', 'date' => $date->toDateString()])
        ->expectsOutputToContain('BUFF_MEAL_REMINDER_DUE:breakfast')
        ->assertSuccessful();

    MealEntry::query()->create([
        'date' => $date,
        'meal_type' => 'breakfast',
        'source_type' => MealEntry::SOURCE_CUSTOM,
        'name' => 'Oats',
        'calories' => 300,
        'protein_g' => 10,
        'carbs_g' => 50,
        'fat_g' => 5,
    ]);

    $this->artisan('meal-reminder:check', ['meal' => 'breakfast', 'date' => $date->toDateString()])
        ->expectsOutputToContain('BUFF_MEAL_REMINDER_LOGGED:breakfast')
        ->assertSuccessful();

    $this->artisan('meal-reminder:check', ['meal' => 'lunch', 'date' => $date->toDateString()])
        ->expectsOutputToContain('BUFF_MEAL_REMINDER_DUE:lunch')
        ->assertSuccessful();
});
