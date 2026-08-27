<?php

use App\Models\AppPreference;
use App\Models\BodyProfile;
use App\Models\DailyGoal;
use App\Models\MealEntry;
use App\Models\SyncOutbox;
use App\Services\AppleHealthBridge;
use App\Services\HealthConnectBridge;
use App\Services\MealReminderBridge;
use Inertia\Testing\AssertableInertia as Assert;

it('renders the settings hub and nested section pages', function (): void {
    app()->instance(HealthConnectBridge::class, new HealthConnectBridge(
        androidDetector: fn (): bool => false,
    ));
    app()->instance(AppleHealthBridge::class, new AppleHealthBridge(
        iosDetector: fn (): bool => false,
    ));

    BodyProfile::current()->update([
        'height_cm' => 178,
        'age' => 32,
        'sex' => 'male',
        'activity_level' => 'moderate',
    ]);

    DailyGoal::query()->create([
        'calories' => 2000,
        'protein_g' => 170,
        'carbs_g' => 195,
        'fat_g' => 60,
        'macro_calories' => 2000,
        'target_weight_kg' => 80,
        'target_body_fat_percent' => 15,
    ]);

    $this->get('/settings')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Settings')
            ->missing('settings')
            ->missing('bodyProfile')
            ->missing('timezones')
            ->where('preferences.weight_unit', 'kg')
            ->where('preferences.height_unit', 'cm')
            ->where('preferences.measurement_unit', 'cm')
            ->where('preferences.eat_back', 'all')
            ->where('mealReminders.breakfast.enabled', false)
            ->where('mealReminders.breakfast.time', '08:00')
            ->where('mealReminders.lunch.enabled', false)
            ->where('mealReminders.lunch.time', '12:00')
            ->where('mealReminders.dinner.enabled', false)
            ->where('mealReminders.dinner.time', '18:00')
            ->where('healthConnect.is_android', false)
            ->where('healthConnect.supported', false)
            ->where('appleHealth.is_ios', false)
            ->where('appleHealth.supported', false)
        );

    $this->get('/settings/account')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Settings/Account')
            ->where('timezones', timezone_identifiers_list(DateTimeZone::ALL))
        );

    $this->get('/settings/password')->assertOk()->assertInertia(fn (Assert $page) => $page->component('Settings/Password'));

    $this->get('/settings/appearance')->assertOk()->assertInertia(fn (Assert $page) => $page->component('Settings/Appearance'));

    $this->get('/settings/reminders')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Settings/Reminders')
            ->where('mealReminders.breakfast.time', '08:00')
        );

    $this->get('/settings/body-profile')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Settings/BodyProfile')
            ->where('bodyProfile.height_cm', 178)
            ->where('bodyProfile.age', 32)
            ->where('bodyProfile.sex', 'male')
            ->where('bodyProfile.activity_level', 'moderate')
            ->where('preferences.height_unit', 'cm')
        );

    $this->get('/settings/units')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Settings/Units')
            ->where('preferences.weight_unit', 'kg')
            ->where('preferences.height_unit', 'cm')
            ->where('preferences.measurement_unit', 'cm')
        );

    $this->get('/settings/exercise')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Settings/Exercise')
            ->where('preferences.eat_back', 'all')
        );

    $this->get('/settings/health')->assertRedirect('/settings');
});

it('renders health settings on Android', function (): void {
    app()->instance(HealthConnectBridge::class, new HealthConnectBridge(
        androidDetector: fn (): bool => true,
    ));
    app()->instance(AppleHealthBridge::class, new AppleHealthBridge(
        iosDetector: fn (): bool => false,
    ));

    $this->get('/settings/health')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Settings/Health')
            ->where('healthConnect.is_android', true)
            ->where('appleHealth.is_ios', false)
        );
});

it('updates unit preferences from settings', function (): void {
    AppPreference::current();

    $this->put('/settings/units', [
        'weight_unit' => 'lb',
        'height_unit' => 'in',
        'measurement_unit' => 'in',
    ])->assertRedirect()
        ->assertSessionHas('message', 'Units saved.');

    $this->assertDatabaseHas('app_preferences', [
        'weight_unit' => 'lb',
        'height_unit' => 'in',
        'measurement_unit' => 'in',
    ]);

    expect(SyncOutbox::query()->where('record_id', AppPreference::ID)->sole()->payload['measurement_unit'])->toBe('in');
});

it('rejects invalid measurement units', function (): void {
    $this->put('/settings/units', [
        'weight_unit' => 'kg',
        'height_unit' => 'cm',
        'measurement_unit' => 'feet',
    ])->assertSessionHasErrors('measurement_unit');
});

it('updates eat-back preference from settings', function (): void {
    AppPreference::current();

    $this->put('/settings/eat-back', [
        'eat_back' => 'half',
    ])->assertRedirect()
        ->assertSessionHas('message', 'Exercise calorie setting saved.');

    $this->assertDatabaseHas('app_preferences', [
        'eat_back' => 'half',
    ]);
});

it('rejects invalid eat-back values', function (): void {
    $this->put('/settings/eat-back', [
        'eat_back' => 'quarter',
    ])->assertSessionHasErrors('eat_back');
});

it('updates body profile from settings and syncs it separately from goals', function (): void {
    DailyGoal::query()->create([
        'calories' => 2200,
        'protein_g' => 180,
        'carbs_g' => 220,
        'fat_g' => 68.89,
        'macro_calories' => 2200,
        'target_weight_kg' => 80,
        'target_body_fat_percent' => 15,
    ]);

    $this->put('/settings/body-profile', [
        'height_cm' => 178,
        'age' => 32,
        'sex' => 'female',
        'activity_level' => 'light',
    ])->assertRedirect();

    $this->assertDatabaseHas('body_profiles', [
        'id' => BodyProfile::ID,
        'height_cm' => 178,
        'age' => 32,
        'sex' => 'female',
        'activity_level' => 'light',
    ]);

    $this->assertDatabaseHas('daily_goals', [
        'calories' => 2200,
        'target_weight_kg' => 80,
        'target_body_fat_percent' => 15,
    ]);

    $outbox = SyncOutbox::query()->where('record_id', BodyProfile::ID)->firstOrFail();

    expect($outbox->record_type)->toBe('body_profiles')
        ->and($outbox->payload['age'])->toBe(32)
        ->and($outbox->payload['sex'])->toBe('female')
        ->and($outbox->payload['activity_level'])->toBe('light');
});

it('allows clearing body profile without clearing body targets', function (): void {
    DailyGoal::query()->create([
        'calories' => 2000, 'protein_g' => 170, 'carbs_g' => 195, 'fat_g' => 60, 'macro_calories' => 2000,
        'target_weight_kg' => 80, 'target_body_fat_percent' => 15,
    ]);

    BodyProfile::current()->update([
        'height_cm' => 178,
        'age' => 32,
        'sex' => 'male',
        'activity_level' => 'moderate',
    ]);

    $this->put('/settings/body-profile', [
        'height_cm' => '',
        'age' => '',
        'sex' => '',
        'activity_level' => '',
    ])->assertRedirect();

    $this->assertDatabaseHas('body_profiles', [
        'id' => BodyProfile::ID,
        'height_cm' => null,
        'age' => null,
        'sex' => null,
        'activity_level' => null,
    ]);

    $this->assertDatabaseHas('daily_goals', [
        'target_weight_kg' => 80,
        'target_body_fat_percent' => 15,
    ]);
});

it('requires and bounds body profile fields', function (): void {
    $this->put('/settings/body-profile', [])->assertSessionHasErrors(['height_cm', 'age', 'sex', 'activity_level']);

    $this->put('/settings/body-profile', [
        'height_cm' => 49,
        'age' => 12,
        'sex' => 'unknown',
        'activity_level' => 'extreme',
    ])->assertSessionHasErrors(['height_cm', 'age', 'sex', 'activity_level']);
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

it('keeps the previous reminder settings when native scheduling fails', function (): void {
    $previous = AppPreference::current()->mealReminders();

    app()->instance(MealReminderBridge::class, new MealReminderBridge(
        fn (): string => json_encode(['status' => 'error'], JSON_THROW_ON_ERROR),
    ));

    $this->put('/settings/meal-reminders', [
        'breakfast' => ['enabled' => true, 'time' => '07:30'],
        'lunch' => ['enabled' => true, 'time' => '12:15'],
        'dinner' => ['enabled' => true, 'time' => '19:00'],
    ])->assertRedirect();

    expect(AppPreference::current()->fresh()->mealReminders())->toBe($previous);
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

    $this->artisan('meal-reminder:check', ['--meal' => 'breakfast', '--date' => $date->toDateString()])
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

    $this->artisan('meal-reminder:check', ['--meal' => 'breakfast', '--date' => $date->toDateString()])
        ->expectsOutputToContain('BUFF_MEAL_REMINDER_LOGGED:breakfast')
        ->assertSuccessful();

    $this->artisan('meal-reminder:check', ['--meal' => 'lunch', '--date' => $date->toDateString()])
        ->expectsOutputToContain('BUFF_MEAL_REMINDER_DUE:lunch')
        ->assertSuccessful();
});
