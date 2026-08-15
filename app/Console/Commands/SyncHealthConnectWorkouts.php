<?php

namespace App\Console\Commands;

use App\Models\SyncState;
use App\Services\HealthConnectBridge;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('health-connect:sync')]
#[Description('Queue a Health Connect workout sync')]
class SyncHealthConnectWorkouts extends Command
{
    public function handle(HealthConnectBridge $bridge): int
    {
        if (SyncState::query()->doesntExist()) {
            $this->info('Health Connect sync skipped while signed out.');

            return self::SUCCESS;
        }

        $result = $bridge->call('HealthConnect.SyncNow');
        $status = $result['status'] ?? null;

        if (! in_array($status, ['sync_queued', 'permission_required', 'unavailable', 'unsupported'], true)) {
            $this->error($result['message'] ?? 'Health Connect sync failed.');

            return self::FAILURE;
        }

        $this->info($result['message'] ?? $status);

        return self::SUCCESS;
    }
}
