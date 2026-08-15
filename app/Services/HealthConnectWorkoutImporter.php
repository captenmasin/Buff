<?php

namespace App\Services;

use App\Models\HealthConnectIgnoredWorkout;
use App\Models\HealthConnectSyncState;
use App\Models\WorkoutEntry;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class HealthConnectWorkoutImporter
{
    public function import(array $payload): array
    {
        return DB::transaction(function () use ($payload): array {
            $syncedAt = $this->dateTime($payload['synced_at'] ?? null) ?? now();
            $windowStart = $this->dateTime($payload['window_start'] ?? null) ?? now()->subDays(30);
            $windowEnd = $this->dateTime($payload['window_end'] ?? null) ?? now();
            $records = collect($payload['records'] ?? []);
            $ignoredIds = HealthConnectIgnoredWorkout::query()->pluck('external_id')->all();
            $importedIds = [];
            $importedCount = 0;

            foreach ($records as $record) {
                $externalId = trim((string) ($record['external_id'] ?? ''));
                $calories = (int) round((float) ($record['calories_burned'] ?? 0));

                if ($externalId === '' || $calories < 1 || in_array($externalId, $ignoredIds, true)) {
                    continue;
                }

                $startedAt = $this->dateTime($record['started_at'] ?? null);
                $endedAt = $this->dateTime($record['ended_at'] ?? null);

                if (! $startedAt) {
                    continue;
                }

                $importedIds[] = $externalId;
                $title = trim((string) ($record['title'] ?? '')) ?: 'Health Connect workout';

                WorkoutEntry::query()->updateOrCreate(
                    [
                        'source_type' => WorkoutEntry::SOURCE_HEALTH_CONNECT,
                        'external_id' => $externalId,
                    ],
                    [
                        'date' => $record['date'] ?? $startedAt->toDateString(),
                        'title' => $title,
                        'calories_burned' => $calories,
                        'logged_at' => $startedAt,
                        'external_source' => $record['source_name'] ?? null,
                        'external_source_package' => $record['source_package'] ?? null,
                        'started_at' => $startedAt,
                        'ended_at' => $endedAt,
                        'duration_seconds' => $record['duration_seconds'] ?? ($endedAt ? $startedAt->diffInSeconds($endedAt) : null),
                        'imported_at' => $syncedAt,
                    ],
                );

                $importedCount++;
            }

            $deleteQuery = WorkoutEntry::query()
                ->where('source_type', WorkoutEntry::SOURCE_HEALTH_CONNECT)
                ->whereNotNull('external_id')
                ->whereBetween('started_at', [$windowStart, $windowEnd]);

            if ($importedIds !== []) {
                $deleteQuery->whereNotIn('external_id', array_values(array_unique($importedIds)));
            }

            $deletedCount = (clone $deleteQuery)->count();
            $deleteQuery->get()->each->delete();

            HealthConnectSyncState::query()->updateOrCreate(
                ['source_type' => HealthConnectSyncState::SOURCE_TYPE],
                [
                    'last_synced_at' => $syncedAt,
                    'last_successful_sync_at' => $syncedAt,
                    'last_status' => 'success',
                    'last_error' => null,
                    'synced_records' => $importedCount,
                    'deleted_records' => $deletedCount,
                ],
            );

            return [
                'imported' => $importedCount,
                'deleted' => $deletedCount,
                'ignored' => count($ignoredIds),
            ];
        });
    }

    public function recordFailure(string $message): void
    {
        HealthConnectSyncState::query()->updateOrCreate(
            ['source_type' => HealthConnectSyncState::SOURCE_TYPE],
            [
                'last_synced_at' => now(),
                'last_status' => 'error',
                'last_error' => mb_substr($message, 0, 2000),
            ],
        );
    }

    private function dateTime(mixed $value): ?Carbon
    {
        if ($value === null || $value === '') {
            return null;
        }

        return Carbon::parse($value);
    }
}
