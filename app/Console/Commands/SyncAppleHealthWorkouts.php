<?php

namespace App\Console\Commands;

use App\Models\SyncState;
use App\Services\AppleHealthBridge;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('apple-health:sync')]
#[Description('Queue an Apple Health workout sync')]
class SyncAppleHealthWorkouts extends Command
{
    public function handle(AppleHealthBridge $bridge): int
    {
        if (SyncState::query()->doesntExist()) {
            $this->info('Apple Health sync skipped while signed out.');

            return self::SUCCESS;
        }

        $result = $bridge->call('AppleHealth.SyncNow');
        $status = $result['status'] ?? null;

        if (! in_array($status, ['sync_queued', 'permission_required', 'unavailable', 'unsupported'], true)) {
            $this->error($result['message'] ?? 'Apple Health sync failed.');

            return self::FAILURE;
        }

        $this->info($result['message'] ?? $status);

        return self::SUCCESS;
    }
}
