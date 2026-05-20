<?php

namespace Tests\Feature;

use App\Models\HealthConnectIgnoredWorkout;
use App\Models\HealthConnectSyncState;
use App\Models\WorkoutEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HealthConnectImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_imports_health_connect_workouts(): void
    {
        $this->artisan('health-connect:import', ['payload' => $this->payloadFile([
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
        ])])->assertSuccessful();

        $workout = WorkoutEntry::query()->first();

        $this->assertSame(WorkoutEntry::SOURCE_HEALTH_CONNECT, $workout->source_type);
        $this->assertSame('hc-1', $workout->external_id);
        $this->assertSame('Morning run', $workout->title);
        $this->assertSame(420, $workout->calories_burned);
        $this->assertSame('Google Fit', $workout->external_source);
        $this->assertSame('com.google.android.apps.fitness', $workout->external_source_package);
        $this->assertSame(2700, $workout->duration_seconds);
        $this->assertSame('success', HealthConnectSyncState::healthConnect()->last_status);
    }

    public function test_it_accepts_payload_option_from_native_runtime(): void
    {
        $this->artisan('health-connect:import', ['--payload' => $this->payloadFile([
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
        ])])->assertSuccessful();

        $this->assertDatabaseHas('workout_entries', [
            'external_id' => 'total-calories-1',
            'title' => 'Samsung Health workout',
            'calories_burned' => 24,
            'source_type' => WorkoutEntry::SOURCE_HEALTH_CONNECT,
        ]);
    }

    public function test_it_updates_existing_health_connect_workouts(): void
    {
        WorkoutEntry::query()->create([
            'date' => '2026-05-20',
            'title' => 'Old title',
            'calories_burned' => 100,
            'logged_at' => '2026-05-20 07:00:00',
            'source_type' => WorkoutEntry::SOURCE_HEALTH_CONNECT,
            'external_id' => 'hc-1',
            'started_at' => '2026-05-20 07:00:00',
        ]);

        $this->artisan('health-connect:import', ['payload' => $this->payloadFile([
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

        $this->assertSame(1, WorkoutEntry::query()->count());
        $workout = WorkoutEntry::query()->first();

        $this->assertSame('Updated ride', $workout->title);
        $this->assertSame(350, $workout->calories_burned);
        $this->assertSame('2026-05-20 09:00:00', $workout->logged_at->format('Y-m-d H:i:s'));
    }

    public function test_it_deletes_imported_workouts_missing_from_the_sync_window(): void
    {
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

        $this->artisan('health-connect:import', ['payload' => $this->payloadFile([
            'records' => [],
        ])])->assertSuccessful();

        $this->assertDatabaseMissing('workout_entries', ['external_id' => 'hc-deleted']);
        $this->assertDatabaseHas('workout_entries', ['title' => 'Manual workout']);
    }

    public function test_it_does_not_reimport_locally_ignored_health_connect_workouts(): void
    {
        HealthConnectIgnoredWorkout::query()->create([
            'external_id' => 'hc-ignored',
            'ignored_at' => now(),
        ]);

        $this->artisan('health-connect:import', ['payload' => $this->payloadFile([
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

        $this->assertSame(0, WorkoutEntry::query()->count());
    }

    public function test_deleting_imported_workout_creates_ignore_record(): void
    {
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

        $this->assertDatabaseHas('health_connect_ignored_workouts', ['external_id' => 'hc-1']);
        $this->assertDatabaseMissing('workout_entries', ['id' => $workout->id]);
    }

    private function payloadFile(array $overrides): string
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
}
