<?php

use App\Services\HealthConnectBridge;

it('does not support health connect off android', function (): void {
    $bridge = new HealthConnectBridge(
        fn (): string => throw new RuntimeException('Health Connect should not be called off Android.'),
        fn (): bool => false,
    );

    expect($bridge->call('HealthConnect.Status'))
        ->toMatchArray([
            'is_android' => false,
            'supported' => false,
            'available' => false,
            'status' => 'unsupported',
            'message' => 'Health Connect is only available in the Android app.',
        ]);
});

it('reports an unavailable bridge when native call returns empty', function (): void {
    $bridge = new HealthConnectBridge(fn (): ?string => null);

    expect($bridge->call('HealthConnect.Status'))
        ->toMatchArray([
            'supported' => true,
            'available' => false,
            'status' => 'unavailable',
            'message' => 'Health Connect bridge is not registered.',
        ]);
});

it('reports invalid json from the native bridge', function (): void {
    $bridge = new HealthConnectBridge(fn (): string => '{bad');

    expect($bridge->call('HealthConnect.Status'))
        ->toMatchArray([
            'supported' => true,
            'available' => false,
            'status' => 'error',
            'message' => 'Health Connect returned an invalid response.',
        ]);
});

it('reports non object json from the native bridge', function (): void {
    $bridge = new HealthConnectBridge(fn (): string => '"ok"');

    expect($bridge->call('HealthConnect.Status'))
        ->toMatchArray([
            'supported' => true,
            'available' => false,
            'status' => 'error',
            'message' => 'Health Connect returned an invalid response.',
        ]);
});

it('unwraps data responses from the native bridge', function (): void {
    $bridge = new HealthConnectBridge(fn (): string => json_encode([
        'data' => [
            'available' => true,
            'status' => 'connected',
        ],
    ]));

    expect($bridge->call('HealthConnect.Status'))
        ->toMatchArray([
            'available' => true,
            'status' => 'connected',
        ]);
});
