<?php

use App\Models\AppPreference;
use App\Models\DailyGoal;
use App\Models\MealEntry;
use App\Services\MealReminderBridge;
use Illuminate\Http\UploadedFile;

it('exports local buff data as json', function (): void {
    AppPreference::current()->update([
        'weight_unit' => 'lb',
        'height_unit' => 'in',
        'meal_reminders' => [
            'breakfast' => ['enabled' => true, 'time' => '07:30'],
            'lunch' => ['enabled' => false, 'time' => '12:00'],
            'dinner' => ['enabled' => true, 'time' => '18:30'],
        ],
    ]);

    DailyGoal::query()->create([
        'calories' => 2000,
        'protein_g' => 170,
        'carbs_g' => 195,
        'fat_g' => 60,
        'macro_calories' => 2000,
    ]);

    MealEntry::query()->create([
        'date' => '2026-05-26',
        'meal_type' => 'breakfast',
        'source_type' => MealEntry::SOURCE_CUSTOM,
        'name' => 'Oats',
        'calories' => 300,
        'protein_g' => 20,
        'carbs_g' => 40,
        'fat_g' => 6,
    ]);

    $response = $this->get('/settings/export')
        ->assertOk()
        ->assertHeader('Content-Type', 'application/json');

    $payload = json_decode($response->getContent(), true);

    expect($payload['version'])->toBe(1)
        ->and($payload['data']['preferences'])->toHaveCount(1)
        ->and($payload['data']['preferences'][0]['meal_reminders']['dinner'])->toBe([
            'enabled' => true,
            'time' => '18:30',
        ])
        ->and($payload['data']['meal_entries'])->toHaveCount(1);
});

it('imports local buff data from json', function (): void {
    $calls = [];
    app()->instance(MealReminderBridge::class, new MealReminderBridge(
        function (string $method, string $payload) use (&$calls): string {
            $calls[] = [$method, json_decode($payload, true, 512, JSON_THROW_ON_ERROR)];

            return json_encode(['status' => 'scheduled'], JSON_THROW_ON_ERROR);
        },
    ));

    $mealReminders = [
        'breakfast' => ['enabled' => true, 'time' => '08:15'],
        'lunch' => ['enabled' => true, 'time' => '12:30'],
        'dinner' => ['enabled' => false, 'time' => '18:00'],
    ];
    $payload = [
        'version' => 1,
        'exported_at' => '2026-05-26T10:00:00+00:00',
        'data' => [
            'preferences' => [
                [
                    'id' => 1,
                    'weight_unit' => 'lb',
                    'height_unit' => 'in',
                    'meal_reminders' => $mealReminders,
                ],
            ],
            'daily_goals' => [
                [
                    'id' => '99c42a83-e22f-4420-bbdf-a2976f68e3d5',
                    'calories' => 2000,
                    'protein_g' => 170,
                    'carbs_g' => 195,
                    'fat_g' => 60,
                    'macro_calories' => 2000,
                ],
            ],
            'food_products' => [],
            'meal_entries' => [],
            'body_metrics' => [
                [
                    'id' => '99c42a83-e22f-4420-bbdf-a2976f68e3d6',
                    'date' => '2026-05-26',
                    'weight_kg' => 82,
                    'body_fat_percent' => 15,
                    'notes' => 'Imported',
                ],
            ],
            'workout_entries' => [],
        ],
    ];

    $file = UploadedFile::fake()->createWithContent('buff-export.json', json_encode($payload));

    $this->post('/settings/import', [
        'export' => $file,
    ])->assertRedirect();

    $this->assertDatabaseHas('app_preferences', [
        'weight_unit' => 'lb',
        'height_unit' => 'in',
    ]);

    expect(AppPreference::current()->mealReminders())->toBe($mealReminders)
        ->and($calls)->toBe([[
            'BackgroundTasks.RegisterMealReminders',
            ['reminders' => $mealReminders],
        ]]);

    $this->assertDatabaseHas('daily_goals', [
        'id' => '99c42a83-e22f-4420-bbdf-a2976f68e3d5',
        'calories' => 2000,
    ]);

    $this->assertDatabaseHas('body_metrics', [
        'date' => '2026-05-26 00:00:00',
        'weight_kg' => 82,
    ]);
});
