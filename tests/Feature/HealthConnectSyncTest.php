<?php

use App\Services\HealthConnectBridge;

it('queues a native health connect workout sync', function (): void {
    $calls = [];

    app()->instance(HealthConnectBridge::class, new HealthConnectBridge(
        function (string $method, string $payload) use (&$calls): string {
            $calls[] = [$method, $payload];

            return json_encode([
                'data' => [
                    'supported' => true,
                    'available' => true,
                    'has_permissions' => true,
                    'status' => $method === 'HealthConnect.SyncNow' ? 'sync_queued' : 'connected',
                ],
            ], JSON_THROW_ON_ERROR);
        },
    ));

    $this->postJson('/health-connect/sync')
        ->assertSuccessful()
        ->assertJsonPath('native.status', 'sync_queued')
        ->assertJsonPath('status', 'connected');

    expect($calls)->toBe([
        ['HealthConnect.SyncNow', '[]'],
        ['HealthConnect.Status', '[]'],
    ]);
});

it('revokes native health connect permissions', function (): void {
    $calls = [];

    app()->instance(HealthConnectBridge::class, new HealthConnectBridge(
        function (string $method, string $payload) use (&$calls): string {
            $calls[] = [$method, $payload];

            return json_encode([
                'data' => [
                    'supported' => true,
                    'available' => true,
                    'has_permissions' => false,
                    'foreground_granted' => false,
                    'background_granted' => false,
                    'status' => 'permission_required',
                    ...($method === 'HealthConnect.Disconnect' ? ['message' => 'Health Connect disconnected.'] : []),
                ],
            ], JSON_THROW_ON_ERROR);
        },
    ));

    $this->deleteJson('/health-connect')
        ->assertSuccessful()
        ->assertJsonPath('native.status', 'permission_required')
        ->assertJsonPath('native.message', 'Health Connect disconnected.')
        ->assertJsonPath('status', 'permission_required')
        ->assertJsonPath('has_permissions', false);

    expect($calls)->toBe([
        ['HealthConnect.Disconnect', '[]'],
        ['HealthConnect.Status', '[]'],
    ]);
});

it('opens native health connect access settings', function (): void {
    $calls = [];

    app()->instance(HealthConnectBridge::class, new HealthConnectBridge(
        function (string $method, string $payload) use (&$calls): string {
            $calls[] = [$method, $payload];

            return json_encode([
                'data' => [
                    'supported' => true,
                    'available' => true,
                    'status' => 'permission_required',
                    ...($method === 'HealthConnect.ManageAccess' ? [
                        'manage_access_opened' => true,
                        'message' => "Update Buff's Health Connect access, then return to Buff.",
                    ] : []),
                ],
            ], JSON_THROW_ON_ERROR);
        },
    ));

    $this->postJson('/health-connect/manage-access')
        ->assertSuccessful()
        ->assertJsonPath('native.manage_access_opened', true)
        ->assertJsonPath('native.message', "Update Buff's Health Connect access, then return to Buff.");

    expect($calls)->toBe([
        ['HealthConnect.ManageAccess', '[]'],
        ['HealthConnect.Status', '[]'],
    ]);
});
