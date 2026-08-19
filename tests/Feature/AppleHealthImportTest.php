<?php

use App\Models\HealthConnectIgnoredWorkout;
use App\Models\HealthConnectSyncState;
use App\Models\SyncState;
use App\Models\WorkoutEntry;

it('ignores a background apple health import after the local account is signed out', function (): void {
    SyncState::query()->delete();

    $this->artisan('apple-health:import', ['payload' => appleHealthPayloadFile([
        'records' => [[
            'external_id' => 'signed-out-workout',
            'calories_burned' => 300,
            'started_at' => '2026-05-20T07:00:00+01:00',
        ]],
    ])])
        ->expectsOutputToContain('BUFF_APPLE_HEALTH_IMPORT_SKIPPED')
        ->expectsOutputToContain('BUFF_APPLE_HEALTH_IMPORT_OK')
        ->assertSuccessful();

    $this->assertDatabaseEmpty('workout_entries');
    $this->assertDatabaseEmpty('health_connect_sync_states');
});

it('imports apple health workouts', function (): void {
    $this->artisan('apple-health:import', ['payload' => appleHealthPayloadFile([
        'records' => [
            [
                'external_id' => 'hk-1',
                'title' => 'Morning run',
                'calories_burned' => 420,
                'date' => '2026-05-20',
                'started_at' => '2026-05-20T07:15:00+01:00',
                'ended_at' => '2026-05-20T08:00:00+01:00',
                'duration_seconds' => 2700,
                'source_name' => 'Apple Watch',
                'source_package' => 'com.apple.health',
            ],
        ],
    ])])
        ->expectsOutputToContain('BUFF_APPLE_HEALTH_IMPORT_OK')
        ->assertSuccessful();

    $workout = WorkoutEntry::query()->first();

    expect($workout->source_type)->toBe(WorkoutEntry::SOURCE_APPLE_HEALTH)
        ->and($workout->external_id)->toBe('hk-1')
        ->and($workout->title)->toBe('Morning run')
        ->and($workout->calories_burned)->toBe(420)
        ->and($workout->external_source)->toBe('Apple Watch')
        ->and($workout->external_source_package)->toBe('com.apple.health')
        ->and($workout->duration_seconds)->toBe(2700)
        ->and(HealthConnectSyncState::appleHealth()->last_status)->toBe('success');
});

it('does not reimport locally ignored apple health workouts', function (): void {
    HealthConnectIgnoredWorkout::query()->create([
        'external_id' => 'hk-ignored',
        'ignored_at' => now(),
    ]);

    $this->artisan('apple-health:import', ['payload' => appleHealthPayloadFile([
        'records' => [
            [
                'external_id' => 'hk-ignored',
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

it('creates an ignore record when deleting an imported apple health workout', function (): void {
    $workout = WorkoutEntry::query()->create([
        'date' => '2026-05-20',
        'title' => 'Health workout',
        'calories_burned' => 300,
        'logged_at' => '2026-05-20 07:00:00',
        'source_type' => WorkoutEntry::SOURCE_APPLE_HEALTH,
        'external_id' => 'hk-1',
        'started_at' => '2026-05-20 07:00:00',
    ]);

    $this->delete("/workouts/{$workout->id}")
        ->assertRedirect('/?date=2026-05-20');

    $this->assertDatabaseHas('health_connect_ignored_workouts', ['external_id' => 'hk-1']);
    $this->assertDatabaseMissing('workout_entries', ['id' => $workout->id]);
});

function appleHealthPayloadFile(array $overrides): string
{
    $payload = [
        'synced_at' => '2026-05-20T12:00:00+01:00',
        'window_start' => '2026-05-01T00:00:00+01:00',
        'window_end' => '2026-05-21T00:00:00+01:00',
        'records' => [],
    ];

    $path = sys_get_temp_dir().'/buff-apple-health-'.bin2hex(random_bytes(6)).'.json';
    file_put_contents($path, json_encode([...$payload, ...$overrides], JSON_THROW_ON_ERROR));

    return $path;
}
