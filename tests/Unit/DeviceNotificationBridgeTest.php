<?php

use App\Services\DeviceNotificationBridge;

it('sends a local notification through the native bridge', function (): void {
    $call = null;
    $bridge = new DeviceNotificationBridge(function (string $method, string $payload) use (&$call): string {
        $call = [$method, json_decode($payload, true)];

        return json_encode(['status' => 'sent']);
    });

    expect($bridge->send(
        title: 'Workout saved',
        body: 'Your workout has been logged.',
        url: '/workouts',
    ))->toBe(['status' => 'sent'])
        ->and($call)->toBe([
            'BackgroundTasks.SendNotification',
            [
                'title' => 'Workout saved',
                'body' => 'Your workout has been logged.',
                'url' => '/workouts',
            ],
        ]);
});

it('omits a missing notification url', function (): void {
    $payload = null;
    $bridge = new DeviceNotificationBridge(function (string $method, string $encoded) use (&$payload): string {
        $payload = json_decode($encoded, true);

        return json_encode(['status' => 'sent']);
    });

    expect($bridge->send('Sync complete', 'Your data is up to date.'))
        ->toBe(['status' => 'sent'])
        ->and($payload)->toBe([
            'title' => 'Sync complete',
            'body' => 'Your data is up to date.',
        ]);
});

it('rejects malformed native responses', function (): void {
    $bridge = new DeviceNotificationBridge(fn (): string => '{bad');

    expect($bridge->send('Sync complete', 'Your data is up to date.'))
        ->toBe(['status' => 'error']);
});

it('registers and validates the notification bridge on both platforms', function (): void {
    $manifest = json_decode(file_get_contents(
        __DIR__.'/../../native-plugins/background-tasks/nativephp.json'
    ), true, flags: JSON_THROW_ON_ERROR);
    $bridge = collect($manifest['bridge_functions'])
        ->firstWhere('name', 'BackgroundTasks.SendNotification');
    $android = file_get_contents(
        __DIR__.'/../../native-plugins/background-tasks/resources/android/src/com/buff/backgroundtasks/BackgroundTaskFunctions.kt'
    );
    $ios = file_get_contents(
        __DIR__.'/../../native-plugins/background-tasks/resources/ios/Sources/BackgroundTaskFunctions.swift'
    );

    expect($bridge)
        ->toMatchArray([
            'android' => 'com.buff.backgroundtasks.BackgroundTaskFunctions.SendNotification',
            'ios' => 'BackgroundTaskFunctions.SendNotification',
        ])
        ->and($android)
        ->toContain('class SendNotification(private val activity: FragmentActivity)')
        ->toContain('setSmallIcon(R.drawable.buff_notification)')
        ->toContain('title must be between 1 and 120 characters')
        ->toContain('url must be an internal path')
        ->and($ios)
        ->toContain('class SendNotification: BridgeFunction')
        ->toContain('trigger: nil')
        ->toContain('title must be between 1 and 120 characters')
        ->toContain('url must be an internal path');
});
