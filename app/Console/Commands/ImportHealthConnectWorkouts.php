<?php

namespace App\Console\Commands;

use App\Models\SyncState;
use App\Services\HealthConnectWorkoutImporter;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Throwable;

class ImportHealthConnectWorkouts extends Command
{
    protected $signature = 'health-connect:import
        {payload? : JSON payload or absolute payload file path}
        {--payload= : JSON payload or absolute payload file path}';

    protected $description = 'Import normalized Android Health Connect workout records.';

    public function handle(HealthConnectWorkoutImporter $importer): int
    {
        if (SyncState::query()->doesntExist()) {
            $this->line('BUFF_HEALTH_CONNECT_IMPORT_SKIPPED');
            $this->line('BUFF_HEALTH_CONNECT_IMPORT_OK');

            return self::SUCCESS;
        }

        try {
            $argument = $this->argument('payload') ?: $this->option('payload');

            if (! is_string($argument) || $argument === '') {
                throw new \InvalidArgumentException('Health Connect payload is required.');
            }

            $payload = $this->readPayload($argument);
            $result = $importer->import($payload);

            $this->components->info("Imported {$result['imported']} Health Connect workouts; deleted {$result['deleted']}.");
            $this->line('BUFF_HEALTH_CONNECT_IMPORT_OK');

            return self::SUCCESS;
        } catch (Throwable $e) {
            $importer->recordFailure($e->getMessage());
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }
    }

    private function readPayload(string $argument): array
    {
        $json = File::isFile($argument) ? File::get($argument) : $argument;
        $payload = json_decode($json, true);

        if (! is_array($payload)) {
            throw new \InvalidArgumentException('Health Connect payload must be valid JSON.');
        }

        return $payload;
    }
}
