<?php

namespace App\Console\Commands;

use App\Models\SyncState;
use App\Models\WorkoutEntry;
use App\Services\HealthConnectWorkoutImporter;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Throwable;

class ImportAppleHealthWorkouts extends Command
{
    protected $signature = 'apple-health:import
        {payload? : JSON payload or absolute payload file path}
        {--payload= : JSON payload or absolute payload file path}';

    protected $description = 'Import normalized Apple Health workout records.';

    public function handle(HealthConnectWorkoutImporter $importer): int
    {
        if (SyncState::query()->doesntExist()) {
            $this->line('BUFF_APPLE_HEALTH_IMPORT_SKIPPED');
            $this->line('BUFF_APPLE_HEALTH_IMPORT_OK');

            return self::SUCCESS;
        }

        try {
            $argument = $this->argument('payload') ?: $this->option('payload');

            if (! is_string($argument) || $argument === '') {
                throw new \InvalidArgumentException('Apple Health payload is required.');
            }

            $payload = $this->readPayload($argument);
            $result = $importer->import($payload, WorkoutEntry::SOURCE_APPLE_HEALTH);

            $this->components->info("Imported {$result['imported']} Apple Health workouts; deleted {$result['deleted']}.");
            $this->line('BUFF_APPLE_HEALTH_IMPORT_OK');

            return self::SUCCESS;
        } catch (Throwable $e) {
            $importer->recordFailure($e->getMessage(), WorkoutEntry::SOURCE_APPLE_HEALTH);
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }
    }

    private function readPayload(string $argument): array
    {
        $json = File::isFile($argument) ? File::get($argument) : $argument;
        $payload = json_decode($json, true);

        if (! is_array($payload)) {
            throw new \InvalidArgumentException('Apple Health payload must be valid JSON.');
        }

        return $payload;
    }
}
