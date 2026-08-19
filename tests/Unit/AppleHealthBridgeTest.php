<?php

use App\Services\AppleHealthBridge;

it('does not support apple health off ios', function (): void {
    $bridge = new AppleHealthBridge(
        fn (): string => throw new RuntimeException('Apple Health should not be called off iOS.'),
        fn (): bool => false,
    );

    expect($bridge->call('AppleHealth.Status'))
        ->toMatchArray([
            'is_ios' => false,
            'supported' => false,
            'available' => false,
            'status' => 'unsupported',
            'message' => 'Apple Health is only available in the iOS app.',
        ]);
});

it('reports an unavailable bridge when native call returns empty', function (): void {
    $bridge = new AppleHealthBridge(fn (): ?string => null);

    expect($bridge->call('AppleHealth.Status'))
        ->toMatchArray([
            'supported' => true,
            'available' => false,
            'status' => 'unavailable',
            'message' => 'Apple Health bridge is not registered.',
        ]);
});

it('reports invalid json from the native bridge', function (): void {
    $bridge = new AppleHealthBridge(fn (): string => '{bad');

    expect($bridge->call('AppleHealth.Status'))
        ->toMatchArray([
            'supported' => true,
            'available' => false,
            'status' => 'error',
            'message' => 'Apple Health returned an invalid response.',
        ]);
});

it('reports non object json from the native bridge', function (): void {
    $bridge = new AppleHealthBridge(fn (): string => '"ok"');

    expect($bridge->call('AppleHealth.Status'))
        ->toMatchArray([
            'supported' => true,
            'available' => false,
            'status' => 'error',
            'message' => 'Apple Health returned an invalid response.',
        ]);
});

it('unwraps data responses from the native bridge', function (): void {
    $bridge = new AppleHealthBridge(fn (): string => json_encode([
        'data' => [
            'available' => true,
            'status' => 'connected',
        ],
    ]));

    expect($bridge->call('AppleHealth.Status'))
        ->toMatchArray([
            'is_ios' => true,
            'supported' => true,
            'available' => true,
            'status' => 'connected',
        ]);
});
