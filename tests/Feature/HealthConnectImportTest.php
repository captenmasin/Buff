<?php

use App\Models\HealthConnectIgnoredWorkout;
use App\Models\HealthConnectSyncState;
use App\Models\SyncState;
use App\Models\WorkoutEntry;

it('ignores a background import after the local account is signed out', function (): void {
    SyncState::query()->delete();
    $resultPath = storage_path('framework/testing/health-connect-skip-'.uniqid().'.result');

    $this->artisan('health-connect:import', [
        'payload' => healthConnectPayloadFile([
            'records' => [[
                'external_id' => 'signed-out-workout',
                'calories_burned' => 300,
                'started_at' => '2026-05-20T07:00:00+01:00',
            ]],
        ]),
        '--result' => $resultPath,
    ])
        ->expectsOutputToContain('BUFF_HEALTH_CONNECT_IMPORT_SKIPPED')
        ->expectsOutputToContain('BUFF_HEALTH_CONNECT_IMPORT_OK')
        ->assertSuccessful();

    expect(file_get_contents($resultPath))->toBe('BUFF_HEALTH_CONNECT_IMPORT_OK');
    $this->assertDatabaseEmpty('workout_entries');
    $this->assertDatabaseEmpty('health_connect_sync_states');
    unlink($resultPath);
});

it('imports health connect workouts', function (): void {
    $this->artisan('health-connect:import', ['payload' => healthConnectPayloadFile([
        'records' => [
            [
                'external_id' => 'hc-1',
                'title' => 'Morning run',
                'calories_burned' => 420,
                'date' => '2026-05-20',
                'started_at' => '2026-05-20T07:15:00+01:00',
                'ended_at' => '2026-05-20T08:00:00+01:00',
                'duration_seconds' => 2700,
                'source_name' => 'Google Fit',
                'source_package' => 'com.google.android.apps.fitness',
            ],
        ],
    ])])
        ->expectsOutputToContain('BUFF_HEALTH_CONNECT_IMPORT_OK')
        ->assertSuccessful();

    $workout = WorkoutEntry::query()->first();

    expect($workout->source_type)->toBe(WorkoutEntry::SOURCE_HEALTH_CONNECT)
        ->and($workout->external_id)->toBe('hc-1')
        ->and($workout->title)->toBe('Morning run')
        ->and($workout->calories_burned)->toBe(420)
        ->and($workout->external_source)->toBe('Google Fit')
        ->and($workout->external_source_package)->toBe('com.google.android.apps.fitness')
        ->and($workout->duration_seconds)->toBe(2700)
        ->and(HealthConnectSyncState::query()->whereKey(HealthConnectSyncState::SOURCE_TYPE)->value('last_status'))->toBe('success');
});

it('accepts payload option from native runtime', function (): void {
    $resultPath = storage_path('framework/testing/health-connect-import-'.uniqid().'.result');

    $this->artisan('health-connect:import', [
        '--payload' => healthConnectPayloadFile([
            'records' => [
                [
                    'external_id' => 'total-calories-1',
                    'title' => 'Samsung Health workout',
                    'calories_burned' => 24,
                    'date' => '2026-05-20',
                    'started_at' => '2026-05-20T10:04:00+01:00',
                    'ended_at' => '2026-05-20T10:08:00+01:00',
                    'duration_seconds' => 240,
                    'source_name' => 'com.sec.android.app.shealth',
                    'source_package' => 'com.sec.android.app.shealth',
                ],
            ],
        ]),
        '--result' => $resultPath,
    ])->assertSuccessful();

    $this->assertDatabaseHas('workout_entries', [
        'external_id' => 'total-calories-1',
        'title' => 'Samsung Health workout',
        'calories_burned' => 24,
        'source_type' => WorkoutEntry::SOURCE_HEALTH_CONNECT,
    ]);
    expect(file_get_contents($resultPath))->toBe('BUFF_HEALTH_CONNECT_IMPORT_OK');
    unlink($resultPath);
});

it('does not acknowledge a failed native runtime import', function (): void {
    $resultPath = storage_path('framework/testing/health-connect-failed-'.uniqid().'.result');

    $this->artisan('health-connect:import', [
        '--payload' => 'not-json',
        '--result' => $resultPath,
    ])->assertFailed();

    expect(is_file($resultPath))->toBeFalse();
});

it('updates existing health connect workouts', function (): void {
    WorkoutEntry::query()->create([
        'date' => '2026-05-20',
        'title' => 'Old title',
        'calories_burned' => 100,
        'logged_at' => '2026-05-20 07:00:00',
        'source_type' => WorkoutEntry::SOURCE_HEALTH_CONNECT,
        'external_id' => 'hc-1',
        'started_at' => '2026-05-20 07:00:00',
    ]);

    $this->artisan('health-connect:import', ['payload' => healthConnectPayloadFile([
        'records' => [
            [
                'external_id' => 'hc-1',
                'title' => 'Updated ride',
                'calories_burned' => 350,
                'date' => '2026-05-20',
                'started_at' => '2026-05-20T09:00:00+01:00',
                'ended_at' => '2026-05-20T09:45:00+01:00',
            ],
        ],
    ])])->assertSuccessful();

    expect(WorkoutEntry::query()->count())->toBe(1);

    $workout = WorkoutEntry::query()->first();

    expect($workout->title)->toBe('Updated ride')
        ->and($workout->calories_burned)->toBe(350)
        ->and($workout->logged_at->format('Y-m-d H:i:s'))->toBe('2026-05-20 09:00:00');
});

it('deletes imported workouts missing from the sync window', function (): void {
    WorkoutEntry::query()->create([
        'date' => '2026-05-20',
        'title' => 'Deleted upstream',
        'calories_burned' => 200,
        'logged_at' => '2026-05-20 07:00:00',
        'source_type' => WorkoutEntry::SOURCE_HEALTH_CONNECT,
        'external_id' => 'hc-deleted',
        'started_at' => '2026-05-20 07:00:00',
    ]);

    WorkoutEntry::query()->create([
        'date' => '2026-05-20',
        'title' => 'Manual workout',
        'calories_burned' => 250,
        'logged_at' => '2026-05-20 12:00:00',
        'source_type' => WorkoutEntry::SOURCE_MANUAL,
    ]);

    $this->artisan('health-connect:import', ['payload' => healthConnectPayloadFile([
        'records' => [],
    ])])->assertSuccessful();

    $this->assertDatabaseMissing('workout_entries', ['external_id' => 'hc-deleted']);
    $this->assertDatabaseHas('workout_entries', ['title' => 'Manual workout']);
});

it('reconciles positive-offset workouts in UTC while preserving their local date and time', function (): void {
    $window = [
        'window_start' => '2026-05-20T22:00:00Z',
        'window_end' => '2026-05-21T00:15:00Z',
    ];

    $this->artisan('health-connect:import', ['payload' => healthConnectPayloadFile([
        ...$window,
        'records' => [[
            'external_id' => 'hc-positive-offset',
            'calories_burned' => 300,
            'started_at' => '2026-05-21T00:16:00+01:00',
            'ended_at' => '2026-05-21T00:46:00+01:00',
        ]],
    ])])->assertSuccessful();

    $workout = WorkoutEntry::query()->where('external_id', 'hc-positive-offset')->sole();

    expect($workout->date->toDateString())->toBe('2026-05-21')
        ->and($workout->logged_at->format('Y-m-d H:i:s'))->toBe('2026-05-21 00:16:00')
        ->and($workout->started_at->format('Y-m-d H:i:s'))->toBe('2026-05-20 23:16:00')
        ->and($workout->ended_at->format('Y-m-d H:i:s'))->toBe('2026-05-20 23:46:00');

    $this->artisan('health-connect:import', ['payload' => healthConnectPayloadFile([
        ...$window,
        'records' => [],
    ])])->assertSuccessful();

    $this->assertDatabaseMissing('workout_entries', ['external_id' => 'hc-positive-offset']);
});

it('does not reimport locally ignored health connect workouts', function (): void {
    HealthConnectIgnoredWorkout::query()->create([
        'source_type' => WorkoutEntry::SOURCE_HEALTH_CONNECT,
        'external_id' => 'hc-ignored',
        'ignored_at' => now(),
    ]);

    $this->artisan('health-connect:import', ['payload' => healthConnectPayloadFile([
        'records' => [
            [
                'external_id' => 'hc-ignored',
                'title' => 'Ignored run',
                'calories_burned' => 300,
                'date' => '2026-05-20',
                'started_at' => '2026-05-20T07:00:00+01:00',
                'ended_at' => '2026-05-20T07:30:00+01:00',
            ],
        ],
    ])])->assertSuccessful();

    expect(WorkoutEntry::query()->count())->toBe(0);
});

it('does not apply an apple health ignore to a health connect workout with the same id', function (): void {
    HealthConnectIgnoredWorkout::query()->create([
        'source_type' => WorkoutEntry::SOURCE_APPLE_HEALTH,
        'external_id' => 'shared-provider-id',
        'ignored_at' => now(),
    ]);

    $this->artisan('health-connect:import', ['payload' => healthConnectPayloadFile([
        'records' => [[
            'external_id' => 'shared-provider-id',
            'title' => 'Health Connect run',
            'calories_burned' => 300,
            'date' => '2026-05-20',
            'started_at' => '2026-05-20T07:00:00+01:00',
            'ended_at' => '2026-05-20T07:30:00+01:00',
        ]],
    ])])->assertSuccessful();

    expect(WorkoutEntry::query()->where('source_type', WorkoutEntry::SOURCE_HEALTH_CONNECT)->count())->toBe(1);
});

it('creates an ignore record when deleting an imported workout', function (): void {
    $workout = WorkoutEntry::query()->create([
        'date' => '2026-05-20',
        'title' => 'Health workout',
        'calories_burned' => 300,
        'logged_at' => '2026-05-20 07:00:00',
        'source_type' => WorkoutEntry::SOURCE_HEALTH_CONNECT,
        'external_id' => 'hc-1',
        'started_at' => '2026-05-20 07:00:00',
    ]);

    $this->delete("/workouts/{$workout->id}")
        ->assertRedirect('/?date=2026-05-20');

    $this->assertDatabaseHas('health_connect_ignored_workouts', [
        'source_type' => WorkoutEntry::SOURCE_HEALTH_CONNECT,
        'external_id' => 'hc-1',
    ]);
    $this->assertDatabaseMissing('workout_entries', ['id' => $workout->id]);
});

function healthConnectPayloadFile(array $overrides): string
{
    $payload = [
        'synced_at' => '2026-05-20T12:00:00+01:00',
        'window_start' => '2026-05-01T00:00:00+01:00',
        'window_end' => '2026-05-21T00:00:00+01:00',
        'records' => [],
    ];

    $path = sys_get_temp_dir().'/buff-health-connect-'.bin2hex(random_bytes(6)).'.json';
    file_put_contents($path, json_encode([...$payload, ...$overrides], JSON_THROW_ON_ERROR));

    return $path;
}
