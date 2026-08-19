<?php

use App\Services\AppleHealthBridge;

it('queues a native apple health workout sync', function (): void {
    $calls = [];

    app()->instance(AppleHealthBridge::class, new AppleHealthBridge(
        function (string $method, string $payload) use (&$calls): string {
            $calls[] = [$method, $payload];

            return json_encode([
                'data' => [
                    'supported' => true,
                    'available' => true,
                    'has_permissions' => true,
                    'status' => $method === 'AppleHealth.SyncNow' ? 'sync_queued' : 'connected',
                ],
            ], JSON_THROW_ON_ERROR);
        },
    ));

    $this->postJson('/apple-health/sync')
        ->assertSuccessful()
        ->assertJsonPath('native.status', 'sync_queued')
        ->assertJsonPath('status', 'connected');

    expect($calls)->toBe([
        ['AppleHealth.SyncNow', '[]'],
        ['AppleHealth.Status', '[]'],
    ]);
});
